<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Role;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\AccountProvisioner;
use App\Services\Taxonomy\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Applying for a role after the account already exists.
 *
 * Signup no longer asks what you are, so this is where somebody becomes a
 * creator, an editor, a brand — or several at once. Manager is deliberately
 * not offered: that role is only ever granted by an account holder appointing
 * you, never by applying.
 */
class RoleApplicationController extends Controller
{
    private const APPLICABLE = ['creator', 'editor', 'brand'];

    public function index(Request $request, CategoryService $categories): View
    {
        $user = $request->user()->fresh();

        return view('app.roles', [
            'held' => $user->roleSlugs(),
            'applicable' => self::APPLICABLE,
            'creatorCategories' => $categories->selectable('creator', $user->creatorProfile),
            'creatorSelected' => $user->creatorProfile
                ? $categories->forProfile($user->creatorProfile)->pluck('id')->all()
                : [],
            'maxCreatorCategories' => Category::MAX_PER_CREATOR,
        ]);
    }

    public function apply(Request $request, AccountProvisioner $provisioner, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', self::APPLICABLE)],
        ]);
        $user = $request->user();

        if (in_array($data['role'], $user->roleSlugs(), true)) {
            return back()->with('status', __('You already have that role.'));
        }

        $role = Role::query()->where('slug', $data['role'])->firstOrFail();
        $user->roles()->attach($role);
        $provisioner->provisionRole($user, $role->slug);
        $audit->record('role.applied', $user, ['role' => $role->slug]);

        return redirect()->route($this->landingFor($role->slug))
            ->with('status', $this->nextStepMessage($role->slug));
    }

    /** Creator picks the categories brands will search them by. */
    public function saveCreatorCategories(Request $request, CategoryService $categories): RedirectResponse
    {
        // Read the relation rather than the possibly-cached attribute: the role
        // may have been granted moments ago on the same user instance.
        $profile = $request->user()->creatorProfile()->first();
        abort_unless($profile, 403);

        $data = $request->validate([
            'category_ids' => ['array'],
            'category_ids.*' => ['integer'],
            'new_categories' => ['array'],
            'new_categories.*' => ['string', 'max:48'],
        ]);

        $categories->sync(
            $profile,
            'creator',
            $data['category_ids'] ?? [],
            $data['new_categories'] ?? [],
            $request->user(),
            Category::MAX_PER_CREATOR,
        );

        return back()->with('status', __('Categories saved. Brands can now find you by these.'));
    }

    private function landingFor(string $role): string
    {
        return match ($role) {
            'editor' => 'app.editors',
            'brand' => 'app.brand',
            default => 'app.roles',
        };
    }

    private function nextStepMessage(string $role): string
    {
        return match ($role) {
            'creator' => __('Creator account created. Connect Instagram and choose up to :max categories so brands can find you.', ['max' => Category::MAX_PER_CREATOR]),
            'editor' => __('Editor account created. Complete your profile and apply for verification.'),
            'brand' => __('Brand account created. Complete your company details for verification.'),
            default => __('Role added.'),
        };
    }
}
