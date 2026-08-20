<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Role;
use App\Services\Profiles\ProfileApplications;
use App\Services\Profiles\ProfileDirectory;
use App\Services\Taxonomy\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Applying for a role after the account already exists.
 *
 * Signup no longer asks what you are, so this is where somebody becomes a
 * creator, an editor, a brand — or several at once, on the one account they
 * already have.
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

    public function apply(Request $request, ProfileApplications $applications): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', self::APPLICABLE)],
        ]);
        $user = $request->user();

        $result = $applications->apply($user, $data['role']);

        // A profile awaiting review has no dashboard to land on yet.
        if ($result['status'] !== ProfileDirectory::ACTIVE) {
            return back()->with('status', $result['message']);
        }

        return redirect()->route($this->landingFor($data['role']))
            ->with('status', $this->nextStepMessage($data['role']));
    }

    /** Creator picks the categories brands will search them by. */
    public function saveCreatorCategories(Request $request, CategoryService $categories): RedirectResponse
    {
        // Read the relation rather than the possibly-cached attribute: the role
        // may have been granted moments ago on the same user instance.
        $profile = $request->user()->creatorProfile()->first();
        abort_unless($profile !== null, 403);

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
