<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Privacy\PersonalDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Your data, and the door out.
 */
class PrivacyController extends Controller
{
    public function show(): View
    {
        return view('app.privacy');
    }

    public function export(Request $request, PersonalDataService $data)
    {
        $payload = $data->export($request->user());

        return response()->streamDownload(
            fn () => print (json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'vidlix-my-data-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function destroy(Request $request, PersonalDataService $data): RedirectResponse
    {
        $input = $request->validate([
            'password' => ['required', 'string'],
            'confirm' => ['required', 'in:DELETE'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // The password is checked here rather than trusted from the session,
        // because a borrowed logged-in browser should not be able to close
        // somebody's account.
        if (! Hash::check($input['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => __('That password is not correct.')]);
        }

        $data->closeAccount($request->user(), $input['reason'] ?? '');

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', __('Your account is closed. Financial records were kept with your identity removed, as the law requires.'));
    }
}
