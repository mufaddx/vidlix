<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retires the manager system.
 *
 * The tables are renamed rather than dropped. Renaming keeps every row, every
 * column and every foreign key exactly as it was, so the audit trail of who
 * managed whom survives — and reversing it is a rename back rather than a
 * restore from somewhere else. Nothing in the application reads these names, so
 * once renamed they are inert.
 *
 * A live management subscription is a financial obligation, not a row to tidy
 * away. If one exists the migration stops and says so, so somebody settles it
 * deliberately instead of discovering it was cancelled by a deploy.
 */
return new class extends Migration
{
    /** Renamed in dependency order: children before the tables they point at. */
    private const TABLES = [
        'manager_invitations' => 'archived_manager_invitations',
        'management_subscriptions' => 'archived_management_subscriptions',
        'management_plans' => 'archived_management_plans',
        'manager_activity_logs' => 'archived_manager_activity_logs',
        'manager_assignments' => 'archived_manager_assignments',
        'manager_profiles' => 'archived_manager_profiles',
    ];

    public function up(): void
    {
        $this->guardLiveSubscriptions();

        foreach (self::TABLES as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }

        // The role itself. Memberships go with it, so nobody is left holding a
        // role that no longer means anything.
        if (Schema::hasTable('roles')) {
            $roleId = DB::table('roles')->where('slug', 'manager')->value('id');

            if ($roleId !== null) {
                if (Schema::hasTable('role_user')) {
                    DB::table('role_user')->where('role_id', $roleId)->delete();
                }
                DB::table('roles')->where('id', $roleId)->delete();
            }
        }

        // Abilities that only ever gated the manager admin pages.
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('slug', ['managers.view', 'managers.assign'])->delete();
        }

        if (Schema::hasTable('employee_abilities')) {
            DB::table('employee_abilities')->whereIn('ability', ['managers.view', 'managers.assign'])->delete();
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES, true) as $from => $to) {
            if (Schema::hasTable($to) && ! Schema::hasTable($from)) {
                Schema::rename($to, $from);
            }
        }

        // The role and its permissions are deliberately not recreated. Bringing
        // the tables back is a data-recovery step; bringing the feature back is
        // a decision, and it should be made explicitly rather than by rollback.
    }

    private function guardLiveSubscriptions(): void
    {
        if (! Schema::hasTable('management_subscriptions')) {
            return;
        }

        $live = DB::table('management_subscriptions')
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->count();

        if ($live > 0) {
            throw new RuntimeException(
                "Refusing to retire the manager system: {$live} management subscription(s) are still live. "
                .'Settle, refund or expire them first, then run this migration again.'
            );
        }
    }
};
