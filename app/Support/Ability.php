<?php

namespace App\Support;

/**
 * What an employee is allowed to do in the admin panel.
 *
 * Each admin route names exactly one of these. Granting "answer the help desk"
 * must not also grant "approve a payout" — that was the previous behaviour and
 * it meant a CMS editor could move real money.
 */
final class Ability
{
    public const SUPPORT_VIEW = 'support.view';

    public const SUPPORT_REPLY = 'support.reply';

    public const VERIFICATION_DECIDE = 'verification.decide';

    public const FINANCE_VIEW = 'finance.view';

    public const FINANCE_APPROVE_PAYOUTS = 'finance.approve_payouts';

    public const DISPUTES_RESOLVE = 'disputes.resolve';

    public const CMS_MANAGE = 'cms.manage';

    public const USERS_VIEW = 'users.view';

    public const CATEGORIES_APPROVE = 'categories.approve';

    public const MANAGERS_ASSIGN = 'managers.assign';

    public const MANAGERS_VIEW = 'managers.view';

    /** Granting abilities is itself an ability, and only a super admin has it. */
    public const EMPLOYEES_MANAGE = 'employees.manage';

    /**
     * Grouped for the grant screen, with a plain description of what each one
     * actually lets somebody do.
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        return [
            'Help desk' => [
                self::SUPPORT_VIEW => 'Read help desk messages',
                self::SUPPORT_REPLY => 'Reply to people from the help desk',
            ],
            'Verification' => [
                self::VERIFICATION_DECIDE => 'Approve or reject editors, brands and campaigns',
                self::CATEGORIES_APPROVE => 'Approve categories people have proposed',
            ],
            'Money' => [
                self::FINANCE_VIEW => 'See withdrawals and the ledger',
                self::FINANCE_APPROVE_PAYOUTS => 'Approve withdrawals — this instructs a real bank transfer',
            ],
            'Disputes' => [
                self::DISPUTES_RESOLVE => 'Resolve disputes between members',
            ],
            'Content' => [
                self::CMS_MANAGE => 'Edit website copy and pages',
            ],
            'Members' => [
                self::USERS_VIEW => 'Browse member accounts',
                self::MANAGERS_VIEW => 'See who manages whom',
                self::MANAGERS_ASSIGN => 'Assign a manager to a member on the company\'s behalf',
            ],
            'Staff' => [
                self::EMPLOYEES_MANAGE => 'Add employees and grant abilities (super admin only)',
            ],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        $abilities = [];
        foreach (self::grouped() as $group) {
            foreach (array_keys($group) as $ability) {
                $abilities[] = $ability;
            }
        }

        return $abilities;
    }

    public static function label(string $ability): string
    {
        foreach (self::grouped() as $group) {
            if (isset($group[$ability])) {
                return $group[$ability];
            }
        }

        return $ability;
    }

    /** Abilities an ordinary employee may be granted. */
    public static function grantable(): array
    {
        return array_values(array_diff(self::all(), [self::EMPLOYEES_MANAGE]));
    }
}
