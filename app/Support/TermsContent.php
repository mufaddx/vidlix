<?php

namespace App\Support;

use App\Models\CommissionRule;

/**
 * Role-specific terms shown before sign-up.
 *
 * An influencer, an editor and a brand are agreeing to genuinely different
 * things, so showing all three one generic wall of text would mean nobody
 * reads the part that applies to them.
 *
 * These are plain-language summaries written to be understood, not the binding
 * agreement. The full policies live in the CMS at /p/terms and still need a
 * lawyer's text before real money moves.
 */
final class TermsContent
{
    /**
     * @return array<string, array{label: string, intro: string, points: array<int, array{title: string, body: string}>}>
     */
    public static function all(): array
    {
        return [
            'creator' => [
                'label' => 'Influencer',
                'intro' => 'What you agree to when you take on brand work through Vidlix.',
                'points' => [
                    ['title' => 'Deliverables', 'body' => 'What you agree to produce — formats, counts and deadlines — is whatever is written in the accepted proposal. Changing it later needs both sides to agree in writing on the platform.'],
                    ['title' => 'Content rights', 'body' => 'You keep ownership of what you make. The brand receives the usage licence stated in the proposal — the platforms, the territory and the duration. Anything beyond that is a separate agreement.'],
                    ['title' => 'Sponsored posts', 'body' => 'Paid partnerships must be disclosed as the law and the platform require. Vidlix will not ask you to hide a commercial relationship.'],
                    ['title' => 'Payouts', 'body' => 'Money a brand pays is held against your project and released when the work is accepted. Withdrawals go to your verified bank account and are only marked paid once the payment provider confirms the transfer.'],
                    ['title' => 'Your Instagram data', 'body' => 'Connecting Instagram uses the official Meta API and only reads what you authorise. Vidlix never scrapes, never posts on your behalf without permission, and shows only figures the API actually returned.'],
                ],
            ],
            'editor' => [
                'label' => 'Editor',
                'intro' => 'What you agree to when you take on editing work through Vidlix.',
                'points' => [
                    ['title' => 'Project timelines', 'body' => 'Delivery dates and the number of revision rounds come from the accepted proposal. If footage arrives late, the timeline moves with it.'],
                    ['title' => 'Copyright handover', 'body' => 'On final payment, rights in the edit pass to the client as set out in the proposal. Until then the work remains yours. You may keep the piece in your portfolio unless the proposal says otherwise.'],
                    ['title' => 'File security', 'body' => 'Client footage is confidential. Files are stored in Vidlix object storage and shared through short-lived signed links, never public URLs. Do not redistribute material you were given to edit.'],
                    ['title' => 'Milestone payments', 'body' => 'Advance and balance amounts follow the proposal. Money is held against the project and released on acceptance; it is never released on a promise.'],
                    ['title' => 'Source files', 'body' => 'Whether project files and assets are handed over is agreed in the proposal before work starts, not argued about at delivery.'],
                ],
            ],
            'brand' => [
                'label' => 'Brand',
                'intro' => 'What you agree to when you hire through Vidlix.',
                'points' => [
                    ['title' => 'Campaign terms', 'body' => 'The brief, the deliverables and the fee are fixed by the proposal both sides accept. Extra requests are a new proposal, not an assumption.'],
                    ['title' => 'Billing', 'body' => 'You pay through the platform. A payment counts only when the payment provider confirms it — opening a checkout page is not a payment, and Vidlix will never show one as settled before the provider says so.'],
                    ['title' => 'Usage licences', 'body' => 'You receive exactly the rights written in the proposal: the platforms, the territory and the duration. Paid amplification and extended terms are agreed and priced separately.'],
                    ['title' => 'Confidentiality', 'body' => 'Anything you share for a brief — products, plans, unreleased material — is confidential to the people working on it.'],
                    ['title' => 'Fair dealing', 'body' => 'Work delivered to brief is paid for. Disputes go through the platform dispute process rather than withheld payment.'],
                ],
            ],
        ];
    }

    /**
     * The money terms, which are the same whoever you are.
     *
     * Kept apart from the role sections because every person on the platform
     * agrees to these, and because the fee is read from the live commission
     * rule rather than typed in here: terms that quote a rate the system does
     * not actually charge are worse than terms that quote none.
     *
     * @return array{label: string, intro: string, points: array<int, array{title: string, body: string}>}
     */
    public static function payments(): array
    {
        return [
            'label' => 'Fees and payments',
            'intro' => 'How money moves, what Vidlix charges, and what happens when something goes wrong.',
            'points' => [
                [
                    'title' => 'What Vidlix charges',
                    'body' => 'Vidlix charges a platform fee of '.self::feeLabel().' on the value of work booked through the platform. The fee is shown on every invoice before you commit to anything, and it is the only charge Vidlix makes: there is no joining fee, no listing fee and no charge for holding an account. If the rate ever changes, the new rate applies only to work booked after we have told you.',
                ],
                [
                    'title' => 'Paying and being paid',
                    'body' => 'Payments run through a licensed payment provider. Money a brand pays is held against the project and released to the other side when the work is accepted. A payment counts as made only when the provider confirms it to us — opening a checkout page is not a payment, and nothing in Vidlix will show as paid before that confirmation arrives.',
                ],
                [
                    'title' => 'Taxes',
                    'body' => 'Prices agreed between two members are exclusive of tax unless the proposal says otherwise. Each side is responsible for its own tax position, including GST registration and returns where they apply. Vidlix issues an invoice for its own fee and applies tax on that fee as the law requires.',
                ],
                [
                    'title' => 'Cancellation and refunds',
                    'body' => 'A project cancelled before work starts is refunded in full, less any payment-provider charge that cannot be recovered. Once work has started, what is refundable is what has not yet been delivered; delivered work is payable. The platform fee is refunded in the same proportion as the work.',
                ],
                [
                    'title' => 'Late payment',
                    'body' => 'Invoices are due on the date shown on them. An overdue account may have new bookings paused until it is settled. We will always tell you before pausing anything.',
                ],
                [
                    'title' => 'Disputes and chargebacks',
                    'body' => 'If the two sides disagree, the platform dispute process decides what is owed, and both sides agree to use it before going anywhere else. Raising a chargeback with your bank instead, on work that was delivered, may result in the account being suspended while it is resolved.',
                ],
                [
                    'title' => 'Withdrawals',
                    'body' => 'You withdraw your balance to a bank account you have verified. A withdrawal is marked paid only once the payment provider confirms the transfer, and the balance you see is added up from the ledger rather than typed in by anyone.',
                ],
            ],
        ];
    }

    /** The platform fee as a percentage, taken from the active commission rule. */
    public static function feeLabel(): string
    {
        $bps = CommissionRule::query()
            ->where('is_active', true)
            ->where('slug', 'platform')
            ->value('bps');

        if (! $bps) {
            // Said plainly rather than quoting a number nobody configured.
            return 'a percentage set out in your invoice';
        }

        return rtrim(rtrim(number_format($bps / 100, 2), '0'), '.').'%';
    }

    /**
     * Every role's complete terms.
     *
     * This is what both the website and the app render. all() returns only the
     * role-specific half, and rendering that on its own is how the site ended
     * up showing an agreement with no money terms in it while the phone showed
     * the full one.
     *
     * @return array<string, array{label: string, intro: string, points: array<int, array{title: string, body: string}>}>
     */
    public static function complete(): array
    {
        $complete = [];

        foreach (array_keys(self::all()) as $role) {
            $complete[$role] = self::forRole($role);
        }

        return $complete;
    }

    /** A role's own terms, with the money terms appended. */
    public static function forRole(string $role): ?array
    {
        $terms = self::all()[$role] ?? null;

        if ($terms === null) {
            return null;
        }

        $terms['points'] = [...$terms['points'], ...self::payments()['points']];

        return $terms;
    }
}
