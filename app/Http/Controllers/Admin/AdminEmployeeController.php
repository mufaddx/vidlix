<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAbility;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Ability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Staff accounts and what each of them may do.
 *
 * Abilities are granted one at a time. There is deliberately no "all access"
 * shortcut short of the super admin role, because the point of this screen is
 * that answering the help desk and approving a payout are different jobs.
 */
class AdminEmployeeController extends Controller
{
    public function index(): View
    {
        return view('admin.employees', [
            'employees' => Employee::query()->with('user:id,name,email')->latest()->get(),
            'groups' => Ability::grouped(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'title' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'abilities' => ['array'],
            'abilities.*' => ['string', 'in:'.implode(',', Ability::grantable())],
        ]);

        if (User::query()->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => __('An account already exists for that email.')])->withInput();
        }

        $employee = DB::transaction(function () use ($data, $request) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'] ?? null,
                'password' => $data['password'],
                'status' => 'active',
            ]);
            // Staff accounts are created by a super admin who already verified
            // the person out of band, so no email round-trip is required.
            $user->forceFill(['email_verified_at' => now()])->save();

            $operations = Role::query()->where('slug', 'operations')->first();
            if ($operations) {
                $user->roles()->attach($operations);
            }

            $employee = Employee::query()->create([
                'user_id' => $user->id,
                'employee_code' => Employee::generateCode(),
                'title' => $data['title'] ?? null,
                'status' => 'active',
                'created_by_user_id' => $request->user()->id,
                'joined_at' => now(),
            ]);

            $this->grant($employee, $data['abilities'] ?? [], $request->user());

            return $employee;
        });

        $audit->record('employee.created', $employee, ['abilities' => $employee->abilityList()]);

        return back()->with('status', __('Employee :code created with :n abilities.', [
            'code' => $employee->employee_code,
            'n' => count($employee->abilityList()),
        ]));
    }

    public function updateAbilities(Request $request, Employee $employee, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'abilities' => ['array'],
            'abilities.*' => ['string', 'in:'.implode(',', Ability::grantable())],
        ]);

        $before = $employee->abilityList();
        DB::transaction(fn () => $this->grant($employee, $data['abilities'] ?? [], $request->user(), true));

        $audit->record('employee.abilities_changed', $employee, [
            'before' => $before,
            'after' => $employee->fresh()->abilityList(),
        ]);

        return back()->with('status', __('Abilities updated. They take effect on their next request.'));
    }

    public function updateStatus(Request $request, Employee $employee, AuditLogger $audit): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', 'in:active,suspended']])['status'];
        $employee->update(['status' => $status]);
        $audit->record('employee.status_changed', $employee, ['status' => $status]);

        // Suspension keeps the grants on file but makes every one of them fail.
        return back()->with('status', __('Employee :code is now :status.', [
            'code' => $employee->employee_code,
            'status' => $status,
        ]));
    }

    /** @param  array<int, string>  $abilities */
    private function grant(Employee $employee, array $abilities, User $grantedBy, bool $replace = false): void
    {
        if ($replace) {
            EmployeeAbility::query()->where('employee_id', $employee->id)->delete();
        }

        foreach (array_unique($abilities) as $ability) {
            EmployeeAbility::query()->firstOrCreate(
                ['employee_id' => $employee->id, 'ability' => $ability],
                ['granted_by_user_id' => $grantedBy->id],
            );
        }
    }

    /** Placeholder so a generated code is visible before saving. */
    public function previewCode(): string
    {
        return 'VX-'.now()->format('y').'-'.Str::upper(Str::random(5));
    }
}
