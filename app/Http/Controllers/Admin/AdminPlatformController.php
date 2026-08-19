<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Services\Audit\AuditLogger;
use App\Services\Platform\Features;
use App\Services\Platform\HealthCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Switches an operator can throw without a deploy, and a page that says what
 * is actually working.
 */
class AdminPlatformController extends Controller
{
    public function index(Features $features): View
    {
        return view('admin.platform', [
            'flags' => $features->flags(),
            'audiences' => FeatureFlag::AUDIENCES,
            'maintenance' => $features->isUnderMaintenance(),
            'maintenanceMessage' => $features->maintenanceMessage(),
        ]);
    }

    public function saveFlag(Request $request, Features $features, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64'],
            'enabled' => ['nullable', 'boolean'],
            'audience' => ['required', 'string', 'max:16'],
        ]);

        $enabled = (bool) ($data['enabled'] ?? false);
        $features->setFlag($data['key'], $enabled, $data['audience'], $request->user()->id);
        $audit->record('platform.flag_changed', null, $data + ['enabled' => $enabled]);

        return back()->with('status', __('Switch saved. It takes effect on the next page load.'));
    }

    public function saveMaintenance(Request $request, Features $features, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'message' => ['nullable', 'string', 'max:300'],
        ]);

        $enabled = (bool) ($data['enabled'] ?? false);

        $features->putSetting(Features::MAINTENANCE_KEY, $enabled ? '1' : '0', $request->user()->id);
        $features->putSetting(Features::MAINTENANCE_MESSAGE_KEY, $data['message'] ?? null, $request->user()->id);
        $audit->record('platform.maintenance_changed', null, ['enabled' => $enabled]);

        return back()->with('status', $enabled
            ? __('The site is now closed to members. Staff, sign-in and webhooks stay open.')
            : __('The site is open again.'));
    }

    public function health(HealthCheck $health): View
    {
        return view('admin.health', ['checks' => $health->all()]);
    }
}
