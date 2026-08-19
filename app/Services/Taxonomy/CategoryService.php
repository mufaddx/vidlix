<?php

namespace App\Services\Taxonomy;

use App\Models\Category;
use App\Models\CategoryAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    /** The list a profile picks from: approved, plus their own pending proposals. */
    public function selectable(string $type, ?Model $profile = null): Collection
    {
        return Category::query()
            ->ofType($type)
            ->where(function ($q) use ($profile) {
                $q->where('status', 'approved');
                if ($profile) {
                    $q->orWhereIn('id', CategoryAssignment::query()
                        ->where('categorizable_type', $profile::class)
                        ->where('categorizable_id', $profile->getKey())
                        ->select('category_id'));
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Find an existing category or propose a new one.
     *
     * A proposal is usable immediately — making someone wait for moderation to
     * describe their own work would be worse than a slightly untidy list — but
     * stays out of the public filter list until an admin approves it.
     */
    public function findOrPropose(string $type, string $name, ?User $proposedBy = null): Category
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            throw ValidationException::withMessages(['category' => __('Enter a category name.')]);
        }
        if (mb_strlen($name) > 48) {
            throw ValidationException::withMessages(['category' => __('Category names must be 48 characters or fewer.')]);
        }

        $slug = Str::slug($name);
        if ($slug === '') {
            throw ValidationException::withMessages(['category' => __('That category name cannot be used.')]);
        }

        $existing = Category::query()->ofType($type)->where('slug', $slug)->first();
        if ($existing) {
            return $existing;
        }

        return Category::query()->create([
            'type' => $type,
            'name' => $name,
            'slug' => $slug,
            'status' => 'pending',
            'proposed_by_user_id' => $proposedBy?->id,
        ]);
    }

    /**
     * Replace a profile's categories.
     *
     * @param  array<int, int|string>  $categoryIds  ids of existing categories
     * @param  array<int, string>  $newNames  free-typed names to propose
     */
    public function sync(Model $profile, string $type, array $categoryIds, array $newNames = [], ?User $actor = null, ?int $max = null): Collection
    {
        $resolved = Category::query()
            ->ofType($type)
            ->whereIn('id', array_filter($categoryIds))
            ->get();

        foreach ($newNames as $name) {
            if (trim((string) $name) === '') {
                continue;
            }
            $category = $this->findOrPropose($type, (string) $name, $actor);
            if (! $resolved->contains('id', $category->id)) {
                $resolved->push($category);
            }
        }

        if ($max !== null && $resolved->count() > $max) {
            throw ValidationException::withMessages([
                'categories' => __('Choose at most :max categories.', ['max' => $max]),
            ]);
        }

        DB::transaction(function () use ($profile, $resolved) {
            CategoryAssignment::query()
                ->where('categorizable_type', $profile::class)
                ->where('categorizable_id', $profile->getKey())
                ->delete();

            foreach ($resolved as $category) {
                CategoryAssignment::query()->create([
                    'category_id' => $category->id,
                    'categorizable_type' => $profile::class,
                    'categorizable_id' => $profile->getKey(),
                ]);
            }
        });

        return $resolved;
    }

    /** @return Collection<int, Category> */
    public function forProfile(Model $profile): Collection
    {
        return Category::query()
            ->whereIn('id', CategoryAssignment::query()
                ->where('categorizable_type', $profile::class)
                ->where('categorizable_id', $profile->getKey())
                ->select('category_id'))
            ->orderBy('name')
            ->get();
    }

    public function approve(Category $category): void
    {
        $category->update(['status' => 'approved']);
    }
}
