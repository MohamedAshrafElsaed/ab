<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SocialAccountController extends Controller
{
    public function destroy(Request $request, string $provider): RedirectResponse
    {
        $user = $request->user();

        // Ensure user has at least one other way to login
        if ($user->socialAccounts()->count() <= 1) {
            return back()->with('error', 'You must have at least one linked account.');
        }

        $user->socialAccounts()->where('provider', $provider)->delete();

        return back()->with('success', ucfirst($provider) . ' account unlinked successfully.');
    }
}
