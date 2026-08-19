<?php

namespace App\Services\Marketplace;

use App\Models\Category;
use App\Models\CategoryAssignment;
use App\Models\CreatorProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Brand-side search over published creators.
 *
 * Follower filters read `creator_profiles.follower_count`, which is only ever
 * written from a live Instagram sync. A creator who has not connected Instagram
 * has a null count and is therefore excluded from a follower-filtered search —
 * they are not shown as having zero followers, because we do not know.
 */
class CreatorDiscovery
{
    /**
     * @param  array{categories?: array<int, int>, min_followers?: ?int, max_followers?: ?int, q?: ?string}  $filters
     */
    public function search(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        $query = CreatorProfile::query()
            ->where('visibility', 'public')
            ->with('user:id,name');

        $categoryIds = array_filter($filters['categories'] ?? []);
        if ($categoryIds !== []) {
            // Match any of the chosen categories, not all — a brand looking for
            // "Food or Travel" wants both, not only creators tagged with both.
            $query->whereIn('id', CategoryAssignment::query()
                ->where('categorizable_type', CreatorProfile::class)
                ->whereIn('category_id', $categoryIds)
                ->select('categorizable_id'));
        }

        $min = $filters['min_followers'] ?? null;
        $max = $filters['max_followers'] ?? null;
        if ($min !== null || $max !== null) {
            $query->whereNotNull('follower_count');
            if ($min !== null) {
                $query->where('follower_count', '>=', (int) $min);
            }
            if ($max !== null) {
                $query->where('follower_count', '<=', (int) $max);
            }
        }

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('display_name', 'like', '%'.$term.'%')
                    ->orWhere('username', 'like', '%'.$term.'%');
            });
        }

        return $query
            ->orderByRaw('follower_count is null')
            ->orderByDesc('follower_count')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return Collection<int, Category> */
    public function filterableCategories()
    {
        return Category::query()->ofType('creator')->approved()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Categories for a set of profiles, keyed by profile id, in one query.
     *
     * @return array<int, array<int, string>>
     */
    public function categoriesFor(iterable $profiles): array
    {
        $ids = collect($profiles)->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $rows = CategoryAssignment::query()
            ->where('categorizable_type', CreatorProfile::class)
            ->whereIn('categorizable_id', $ids)
            ->with('category:id,name')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->categorizable_id][] = $row->category?->name ?? '';
        }

        return $map;
    }
}
