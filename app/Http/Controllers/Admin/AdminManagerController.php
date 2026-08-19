<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManagerAssignment;
use App\Models\ManagerInvitation;
use App\Models\User;
use App\Services\Managers\ManagerDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Who manages whom, and assigning a manager on the company's behalf.
 *
 * A company-provided assignment is marked as such everywhere it appears, so
 * neither side is left thinking the account holder chose this person.
 */
class AdminManagerController extends Controller
{
    public function index(): View
    {
        $assignments = ManagerAssignment::query()
            ->with(['owner:id,name,email', 'manager:id,name,email'])
            ->latest()
            ->get();

        // How many accounts each manager currently holds, split by scope.
        $byManager = $assignments
            ->where('status', 'active')
            ->groupBy('manager_user_id')
            ->map(fn ($rows) => [
                'manager' => $rows->first()->manager,
                'total' => $rows->count(),
                'creators' => $rows->where('scope', 'creator')->count(),
                'editors' => $rows->where('scope', 'editor')->count(),
                'brands' => $rows->where('scope', 'brand')->count(),
                'company_provided' => $rows->where('source', 'company')->count(),
            ])
            ->sortByDesc('total')
            ->values();

        return view('admin.managers', [
            'assignments' => $assignments,
            'byManager' => $byManager,
            'pending' => ManagerInvitation::query()->where('status', 'invited')->with('owner:id,name,email')->latest()->get(),
            'summary' => [
                'active' => $assignments->where('status', 'active')->count(),
                'company' => $assignments->where('status', 'active')->where('source', 'company')->count(),
                'managers' => $byManager->count(),
            ],
        ]);
    }

    public function assign(Request $request, ManagerDirectory $directory): RedirectResponse
    {
        $data = $request->validate([
            'owner_email' => ['required', 'email'],
            'manager_email' => ['required', 'email'],
            'scope' => ['required', 'in:creator,brand,editor'],
            'manager_name' => ['nullable', 'string', 'max:120'],
            'manager_mobile' => ['nullable', 'string', 'max:20'],
        ]);

        $owner = User::query()->where('email', $data['owner_email'])->first();
        if (! $owner) {
            return back()->withErrors(['owner_email' => __('No member with that email.')])->withInput();
        }

        $directory->invite(
            $owner,
            $data['scope'],
            [
                'email' => $data['manager_email'],
                'name' => $data['manager_name'] ?? null,
                'mobile' => $data['manager_mobile'] ?? null,
            ],
            source: 'company',
            invitedBy: $request->user(),
        );

        return back()->with('status', __('Invitation sent. It is marked as provided by Vidlix, and only takes effect once they accept.'));
    }
}
