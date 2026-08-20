<?php

namespace App\Support;

use App\Models\User;

/**
 * The admin sidebar, grouped by who you are looking after.
 *
 * The panel is organised by audience — influencers, brands, editors — because
 * that is how the work actually arrives. Picking one collapses the others, so
 * the sidebar shows the tools for that audience and nothing else. Operations
 * holds the cross-cutting work that belongs to no single audience.
 *
 * Every item names the ability it needs, and items the person cannot use are
 * not rendered at all rather than shown and refused.
 */
final class AdminNavigation
{
    /**
     * @return array<string, array{label: string, blurb: string, items: array<int, array{route: string, label: string, ability: ?string}>}>
     */
    public static function sections(): array
    {
        return [
            'influencers' => [
                'label' => 'Influencers',
                'blurb' => 'Creator accounts and their reach',
                'items' => [
                    ['route' => 'admin.influencers', 'label' => 'All influencers', 'ability' => Ability::USERS_VIEW],
                    ['route' => 'admin.members', 'label' => 'Member accounts', 'ability' => Ability::USERS_VIEW],
                    ['route' => 'admin.influencers.categories', 'label' => 'Categories', 'ability' => Ability::CATEGORIES_APPROVE],
                ],
            ],
            'brands' => [
                'label' => 'Brands',
                'blurb' => 'Brand accounts, verification and their campaigns',
                'items' => [
                    ['route' => 'admin.brands', 'label' => 'All brands', 'ability' => Ability::USERS_VIEW],
                    ['route' => 'admin.brands.verification', 'label' => 'Verification', 'ability' => Ability::VERIFICATION_DECIDE],
                    ['route' => 'admin.brands.campaigns', 'label' => 'Campaigns', 'ability' => Ability::VERIFICATION_DECIDE],
                ],
            ],
            'editors' => [
                'label' => 'Editors',
                'blurb' => 'Editor accounts, their specialisms and verification',
                'items' => [
                    ['route' => 'admin.editors', 'label' => 'All editors', 'ability' => Ability::USERS_VIEW],
                    ['route' => 'admin.editors.verification', 'label' => 'Verification', 'ability' => Ability::VERIFICATION_DECIDE],
                    ['route' => 'admin.editors.categories', 'label' => 'Categories', 'ability' => Ability::CATEGORIES_APPROVE],
                ],
            ],
            'operations' => [
                'label' => 'Operations',
                'blurb' => 'Work that belongs to no single audience',
                'items' => [
                    ['route' => 'admin.help-desk', 'label' => 'Help desk', 'ability' => Ability::SUPPORT_VIEW],
                    ['route' => 'admin.finance', 'label' => 'Finance', 'ability' => Ability::FINANCE_VIEW],
                    ['route' => 'admin.disputes', 'label' => 'Disputes', 'ability' => Ability::DISPUTES_RESOLVE],
                    ['route' => 'admin.cms', 'label' => 'Website copy', 'ability' => Ability::CMS_MANAGE],
                    ['route' => 'admin.employees', 'label' => 'Employees', 'ability' => Ability::EMPLOYEES_MANAGE],
                    ['route' => 'admin.health', 'label' => 'System health', 'ability' => Ability::PLATFORM_MANAGE],
                    ['route' => 'admin.platform', 'label' => 'Feature switches', 'ability' => Ability::PLATFORM_MANAGE],
                ],
            ],
        ];
    }

    /** Sections with at least one item this person may open. */
    public static function visibleFor(User $user): array
    {
        $visible = [];

        foreach (self::sections() as $key => $section) {
            $items = array_values(array_filter(
                $section['items'],
                fn (array $item) => $item['ability'] === null || $user->hasAbility($item['ability']),
            ));

            if ($items !== []) {
                $visible[$key] = ['label' => $section['label'], 'blurb' => $section['blurb'], 'items' => $items];
            }
        }

        return $visible;
    }

    /** Which section a route name belongs to, so the sidebar opens on the right one. */
    public static function sectionForRoute(?string $routeName): string
    {
        foreach (self::sections() as $key => $section) {
            foreach ($section['items'] as $item) {
                if ($item['route'] === $routeName) {
                    return $key;
                }
            }
        }

        return 'operations';
    }
}
