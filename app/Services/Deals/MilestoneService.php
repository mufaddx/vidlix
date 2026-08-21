<?php

namespace App\Services\Deals;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Moving a milestone along.
 *
 * The transitions are explicit rather than "set whatever status you were sent",
 * because the order is the whole point: work is submitted before it is
 * approved, and approved before it is paid. A status column somebody can write
 * freely is not a workflow, it is a text field with opinions.
 */
class MilestoneService
{
    /** What may follow what. Anything not listed here is refused. */
    private const TRANSITIONS = [
        ProjectMilestone::PENDING => [ProjectMilestone::IN_PROGRESS, ProjectMilestone::CANCELLED],
        ProjectMilestone::IN_PROGRESS => [ProjectMilestone::SUBMITTED, ProjectMilestone::CANCELLED],
        ProjectMilestone::SUBMITTED => [ProjectMilestone::APPROVED, ProjectMilestone::IN_PROGRESS],
        ProjectMilestone::APPROVED => [ProjectMilestone::PAID],
        ProjectMilestone::PAID => [],
        ProjectMilestone::CANCELLED => [],
    ];

    public function __construct(private AuditLogger $audit) {}

    public function transition(ProjectMilestone $milestone, User $actor, string $to): ProjectMilestone
    {
        $from = $milestone->status;
        $allowed = self::TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('A milestone cannot go from :from to :to.', [
                    'from' => str_replace('_', ' ', $from),
                    'to' => str_replace('_', ' ', $to),
                ]),
            ]);
        }

        $milestone->status = $to;

        // Each stamp is set once, when it happens. Re-submitting after a
        // rejection must not rewrite when the work was first delivered.
        match ($to) {
            ProjectMilestone::SUBMITTED => $milestone->submitted_at ??= now(),
            ProjectMilestone::APPROVED => $milestone->approved_at ??= now(),
            ProjectMilestone::PAID => $milestone->paid_at ??= now(),
            default => null,
        };

        $milestone->save();

        $this->audit->record('milestone.transitioned', $milestone, [
            'from' => $from,
            'to' => $to,
        ], $actor->id);

        return $milestone;
    }

    /**
     * What is left to pay on a project.
     *
     * Summed from the milestones rather than stored, for the same reason
     * balances are summed from the ledger: a stored total is a number that can
     * drift away from the rows it claims to describe.
     */
    public function remainingMinor(Project $project): int
    {
        return (int) ProjectMilestone::query()
            ->where('project_id', $project->id)
            ->whereNotIn('status', [ProjectMilestone::PAID, ProjectMilestone::CANCELLED])
            ->sum('amount_minor');
    }

    public function paidMinor(Project $project): int
    {
        return (int) ProjectMilestone::query()
            ->where('project_id', $project->id)
            ->where('status', ProjectMilestone::PAID)
            ->sum('amount_minor');
    }
}
