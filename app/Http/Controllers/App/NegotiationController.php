<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Negotiation;
use App\Models\User;
use App\Services\Deals\NegotiationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Offers and counter-offers.
 *
 * Every route here loads the negotiation through participation, so a
 * negotiation somebody is not part of is a 404 rather than a 403 — a stranger
 * should not learn that a deal between two other people exists, let alone what
 * it is worth.
 */
class NegotiationController extends Controller
{
    public function __construct(private NegotiationService $negotiations) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('app.negotiations', [
            'negotiations' => Negotiation::query()
                ->where(fn ($q) => $q
                    ->where('initiator_user_id', $user->id)
                    ->orWhere('counterparty_user_id', $user->id))
                ->with(['initiator:id,name', 'counterparty:id,name'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function show(Request $request, string $uuid): View
    {
        $negotiation = $this->mine($request, $uuid);

        return view('app.negotiation', [
            'negotiation' => $negotiation->load(['offers.offeredBy:id,name', 'initiator:id,name', 'counterparty:id,name']),
            'latest' => $negotiation->latestOffer(),
            // You may answer the offer on the table unless you are the one who
            // put it there.
            'canAccept' => $negotiation->isOpen()
                && $negotiation->latestOffer() !== null
                && $negotiation->latestOffer()->offered_by_user_id !== $request->user()->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'counterparty_user_id' => ['required', 'integer', 'exists:users,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'counterparty_scope' => ['nullable', 'in:creator,editor,brand'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'deadline' => ['nullable', 'date'],
            'revision_limit' => ['nullable', 'integer', 'min:0', 'max:20'],
            'usage_rights' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'deliverables' => ['nullable', 'string', 'max:2000'],
        ]);

        $negotiation = $this->negotiations->open(
            $request->user(),
            User::query()->findOrFail($data['counterparty_user_id']),
            $this->terms($data),
            isset($data['campaign_id']) ? Campaign::query()->find($data['campaign_id']) : null,
            $data['counterparty_scope'] ?? null,
        );

        return redirect()
            ->route('app.negotiations.show', $negotiation->uuid)
            ->with('status', __('Offer sent.'));
    }

    public function counter(Request $request, string $uuid): RedirectResponse
    {
        $negotiation = $this->mine($request, $uuid);

        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'deadline' => ['nullable', 'date'],
            'revision_limit' => ['nullable', 'integer', 'min:0', 'max:20'],
            'usage_rights' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'deliverables' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->negotiations->counter($negotiation, $request->user(), $this->terms($data));

        return back()->with('status', __('Counter-offer sent.'));
    }

    public function accept(Request $request, string $uuid): RedirectResponse
    {
        $negotiation = $this->mine($request, $uuid);

        // No offer id is taken from the request: accepting is always accepting
        // what is currently on the table, so a stale offer cannot be accepted
        // after it has been countered.
        $project = $this->negotiations->accept($negotiation, $request->user());

        return redirect()
            ->route('app.projects.show', $project)
            ->with('status', __('Agreed. The project has started.'));
    }

    public function reject(Request $request, string $uuid): RedirectResponse
    {
        $negotiation = $this->mine($request, $uuid);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->negotiations->reject($negotiation, $request->user(), $data['reason'] ?? null);

        return back()->with('status', __('Offer declined.'));
    }

    public function cancel(Request $request, string $uuid): RedirectResponse
    {
        $this->negotiations->cancel($this->mine($request, $uuid), $request->user());

        return back()->with('status', __('Cancelled.'));
    }

    /**
     * Deliverables arrive as one per line, which is how people write a list.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function terms(array $data): array
    {
        $deliverables = array_values(array_filter(array_map(
            'trim',
            preg_split('/\r\n|\r|\n/', (string) ($data['deliverables'] ?? '')) ?: [],
        )));

        return [
            'amount_minor' => $data['amount_minor'],
            'deadline' => $data['deadline'] ?? null,
            'revision_limit' => $data['revision_limit'] ?? null,
            'usage_rights' => $data['usage_rights'] ?? null,
            'note' => $data['note'] ?? null,
            'deliverables' => $deliverables ?: null,
        ];
    }

    private function mine(Request $request, string $uuid): Negotiation
    {
        $negotiation = Negotiation::query()->where('uuid', $uuid)->first();

        abort_unless(
            $negotiation !== null && $request->user()->can('view', $negotiation),
            404,
        );

        return $negotiation;
    }
}
