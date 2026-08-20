<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One namespace for every public username.
 *
 * Creators and editors each had their own unique index, which meant a creator
 * and an editor could both be "asif". That was harmless while the URLs were
 * /u/asif and /editors/asif — the prefix told them apart. It stops being
 * harmless the moment the address is vidlix.in/asif, because then whichever row
 * the resolver happens to find first owns the name, and somebody's audience
 * lands on a stranger's page and writes to a stranger's inbox.
 *
 * So the registry is the authority: one row per name, one unique index across
 * both kinds of profile. The per-profile username columns stay for now because
 * plenty of code still reads them; the registry is what resolution goes
 * through.
 *
 * Collisions are reported, never silently renamed. Somebody's handle is theirs,
 * and a deploy is not entitled to change it — see username_collisions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usernames', function (Blueprint $table) {
            $table->id();

            // Stored already normalised (lowercase, trimmed). The unique index
            // is what actually prevents two people holding one name, so it must
            // sit on the normalised form rather than on what was typed.
            $table->string('username', 64)->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Which profile the name resolves to. Not a polymorphic morph,
            // because there are exactly two kinds and naming them keeps the
            // resolver readable and the index useful.
            $table->string('profile_type', 16);
            $table->unsignedBigInteger('profile_id');

            // active   — resolves to a live profile
            // reserved — held, resolves to nothing (rename grace periods)
            // retired  — previously used; kept so an old link can redirect
            //            rather than 404, and so the name is not handed
            //            straight to somebody else
            $table->string('status', 16)->default('active');

            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['username', 'status']);
            $table->index(['user_id']);
            $table->index(['profile_type', 'profile_id']);
        });

        // Names the router owns, plus the ones people would expect to mean
        // something other than a person. Seeded rather than hard-coded so a new
        // top-level route can reserve its path without a deploy.
        Schema::create('reserved_usernames', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->unique();
            $table->string('reason', 120)->nullable();
            $table->timestamps();
        });

        // Every name a person has held, so a changed username can redirect
        // instead of breaking links that are already printed on things.
        Schema::create('username_history', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('profile_type', 16);
            $table->timestamp('held_from')->nullable();
            $table->timestamp('held_until')->nullable();
            $table->timestamps();

            $table->index(['username']);
        });

        /*
         | Names that could not be migrated because two profiles already claim
         | them. Nothing is renamed automatically: the row records both
         | claimants so a human can decide, tell them, and give the losing side
         | time to pick something else.
         */
        Schema::create('username_collisions', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64);
            $table->foreignId('kept_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('conflicting_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('conflicting_profile_type', 16);
            $table->unsignedBigInteger('conflicting_profile_id');
            $table->string('status', 16)->default('open');
            $table->timestamps();

            $table->index(['username', 'status']);
        });

        $this->seedReserved();
        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('username_collisions');
        Schema::dropIfExists('username_history');
        Schema::dropIfExists('reserved_usernames');
        Schema::dropIfExists('usernames');
    }

    /**
     * Everything vidlix.in/<something> already means, plus the words a visitor
     * would read as the platform speaking rather than a person.
     */
    private function seedReserved(): void
    {
        $router = [
            // Real top-level routes today.
            'admin', 'api', 'app', 'blog', 'brands', 'campaigns', 'creators',
            'editor', 'editors', 'p', 'pricing', 'u', 'up', 'download',
            'webhooks', 'login', 'logout', 'register', 'two-factor',
            'forgot-password', 'verify-email', 'dashboard', 'inbox', 'chat',
            'projects', 'applications', 'portfolio', 'proposals', 'invoices',
            'earnings', 'notifications', 'automations', 'instagram', 'disputes',
            'roles', 'brand', 'discover', 'settings', 'support', 'workspace',
            'integrations', 'project-files', 'withdrawals', 'management',
        ];

        $expected = [
            // Words a stranger would not read as a person's handle.
            'about', 'contact', 'help', 'terms', 'privacy', 'security',
            'signup', 'sign-up', 'sign-in', 'signin', 'account', 'accounts',
            'autodm', 'billing', 'careers', 'press', 'faq', 'legal', 'status',
            'blog-post', 'assets', 'static', 'public', 'cdn', 'img', 'images',
            'css', 'js', 'fonts', 'favicon.ico', 'robots.txt', 'sitemap.xml',
            'well-known', 'oauth', 'auth', 'callback', 'search', 'explore',
            'new', 'create', 'edit', 'delete', 'me', 'you', 'null', 'undefined',
            'vidlix', 'official', 'team', 'staff', 'root', 'system', 'noreply',
            'no-reply', 'postmaster', 'abuse', 'webmaster', 'mail', 'email',
            'www', 'ftp', 'ns1', 'ns2', 'mx',
        ];

        $now = now();
        $rows = [];

        foreach ($router as $name) {
            $rows[$name] = ['username' => $name, 'reason' => 'Reserved: application route', 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($expected as $name) {
            $rows[$name] ??= ['username' => $name, 'reason' => 'Reserved: platform or protocol name', 'created_at' => $now, 'updated_at' => $now];
        }

        DB::table('reserved_usernames')->insert(array_values($rows));
    }

    /**
     * Move existing handles into the registry, oldest claim first.
     *
     * Age decides who keeps a contested name, because it is the one rule that
     * does not require a judgement call and does not favour a profile type. The
     * later claim is recorded as a collision rather than renamed.
     */
    private function backfill(): void
    {
        $claims = collect();

        foreach ([['creator_profiles', 'creator'], ['editor_profiles', 'editor']] as [$table, $type]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->select('id', 'user_id', 'username', 'created_at')
                ->whereNotNull('username')
                ->where('username', '!=', '')
                ->get();

            foreach ($rows as $row) {
                $claims->push([
                    'username' => mb_strtolower(trim((string) $row->username)),
                    'user_id' => (int) $row->user_id,
                    'profile_type' => $type,
                    'profile_id' => (int) $row->id,
                    'created_at' => $row->created_at,
                ]);
            }
        }

        $reserved = DB::table('reserved_usernames')->pluck('username')->flip();
        $now = now();
        $taken = [];

        foreach ($claims->sortBy('created_at') as $claim) {
            $name = $claim['username'];

            if ($name === '') {
                continue;
            }

            // A handle that collides with a route was never reachable at
            // vidlix.in/<name> anyway. Record it so the person can be told,
            // rather than registering a name the router will always win.
            $blocked = $reserved->has($name);

            if (! $blocked && ! isset($taken[$name])) {
                DB::table('usernames')->insert([
                    'username' => $name,
                    'user_id' => $claim['user_id'],
                    'profile_type' => $claim['profile_type'],
                    'profile_id' => $claim['profile_id'],
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $taken[$name] = $claim['user_id'];

                continue;
            }

            DB::table('username_collisions')->insert([
                'username' => $name,
                'kept_user_id' => $taken[$name] ?? $claim['user_id'],
                'conflicting_user_id' => $claim['user_id'],
                'conflicting_profile_type' => $claim['profile_type'],
                'conflicting_profile_id' => $claim['profile_id'],
                'status' => $blocked ? 'reserved_word' : 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
