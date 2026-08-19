<?php

namespace App\Support;

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

    public static function forRole(string $role): ?array
    {
        return self::all()[$role] ?? null;
    }
}
