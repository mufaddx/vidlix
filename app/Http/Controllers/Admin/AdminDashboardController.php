<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BrandProfile;
use App\Models\CreatorProfile;
use App\Models\EditorProfile;
use App\Models\HomepageSection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'counts' => [
                'users' => User::query()->count(),
                'creators' => CreatorProfile::query()->count(),
                'editors' => EditorProfile::query()->count(),
                'brands' => BrandProfile::query()->count(),
            ],
            'audit' => AuditLog::query()->latest()->limit(15)->get(),
        ]);
    }

    public function cms(): View
    {
        $sections = HomepageSection::query()->orderBy('sort_order')->get();

        return view('admin.cms', compact('sections'));
    }

    public function updateSection(Request $request, HomepageSection $section): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:400'],
            'is_visible' => ['sometimes', 'boolean'],
        ]);
        $section->update([
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'is_visible' => $request->boolean('is_visible'),
        ]);

        return back()->with('status', __('Section updated.'));
    }
}
