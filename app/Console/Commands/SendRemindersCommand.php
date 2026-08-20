<?php

namespace App\Console\Commands;

use App\Models\CampaignApplication;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Console\Command;

/**
 * Nudges for things that are waiting on somebody.
 *
 * Each reminder is sent once per item per day. The guard is the notifications
 * table itself rather than a flag column: if a member already has today's
 * reminder for this item, they do not get another. That keeps a scheduler that
 * fires twice - which this HTTP-triggered one can - from sending twice.
 */
class SendRemindersCommand extends Command
{
    protected $signature = 'vidlix:reminders';

    protected $description = 'Remind members about overdue invoices and stalled work';

    public function handle(Notifier $notifier): int
    {
        $sent = 0;

        $sent += $this->overdueInvoices($notifier);
        $sent += $this->stalledProjects($notifier);
        $sent += $this->waitingApplications($notifier);

        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }

    private function overdueInvoices(Notifier $notifier): int
    {
        $sent = 0;

        $invoices = Invoice::query()
            ->whereIn('status', ['issued', 'sent', 'overdue'])
            ->whereDate('due_date', '<', now())
            ->limit(200)
            ->get();

        foreach ($invoices as $invoice) {
            $user = User::query()->find($invoice->buyer_user_id);

            if ($user === null || $this->alreadyRemindedToday($user, 'invoice', $invoice->getKey())) {
                continue;
            }

            $notifier->send(
                $user,
                'withdrawal_updated',
                'Invoice '.$invoice->invoice_number.' is past its due date',
                'It was due on '.$invoice->due_date?->toFormattedDateString().'.',
                ['reminder' => 'invoice', 'id' => (string) $invoice->getKey()],
            );
            $sent++;
        }

        return $sent;
    }

    private function stalledProjects(Notifier $notifier): int
    {
        $sent = 0;

        // Waiting on a person, not on us: these are the states where nothing
        // moves until somebody acts.
        $waitingOn = ['proposal_sent', 'draft_submitted', 'revision_submitted', 'final_submitted'];

        $projects = Project::query()
            ->whereIn('status', $waitingOn)
            ->where('updated_at', '<', now()->subDays(3))
            ->limit(200)
            ->get();

        foreach ($projects as $project) {
            foreach (array_filter([$project->owner_user_id, $project->counterparty_user_id]) as $userId) {
                $user = User::query()->find($userId);

                if ($user === null || $this->alreadyRemindedToday($user, 'project', $project->getKey())) {
                    continue;
                }

                $notifier->send(
                    $user,
                    'project_updated',
                    $project->name.' has been waiting three days',
                    'It is still at '.str_replace('_', ' ', (string) $project->status).'.',
                    ['reminder' => 'project', 'id' => (string) $project->getKey()],
                );
                $sent++;
            }
        }

        return $sent;
    }

    private function waitingApplications(Notifier $notifier): int
    {
        $sent = 0;

        $applications = CampaignApplication::query()
            ->whereIn('status', ['applied', 'viewed'])
            ->where('updated_at', '<', now()->subDays(5))
            ->with('campaign.brand')
            ->limit(200)
            ->get();

        foreach ($applications as $application) {
            $brandUserId = $application->campaign?->brand?->user_id;
            $user = $brandUserId === null ? null : User::query()->find($brandUserId);

            if ($user === null || $this->alreadyRemindedToday($user, 'application', $application->getKey())) {
                continue;
            }

            $notifier->send(
                $user,
                'application_decided',
                'An application has been waiting five days',
                'A creator applied to '.$application->campaign->name.' and has had no answer.',
                ['reminder' => 'application', 'id' => (string) $application->getKey()],
            );
            $sent++;
        }

        return $sent;
    }

    private function alreadyRemindedToday(User $user, string $kind, int|string $id): bool
    {
        return $user->notifications()
            ->whereDate('created_at', now()->toDateString())
            ->get()
            ->contains(fn ($notification) => ($notification->data['payload']['reminder'] ?? null) === $kind
                && (string) ($notification->data['payload']['id'] ?? '') === (string) $id);
    }
}
