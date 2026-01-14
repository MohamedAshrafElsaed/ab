<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class GitHubController extends Controller
{
    public function connect(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['read:user', 'user:email', 'repo'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver('github')->user();
        } catch (Exception $e) {
            return redirect()->route('dashboard')
                ->with('error', 'Failed to connect GitHub: ' . $e->getMessage());
        }

        $user = $request->user();

        $socialAccount = $user->githubAccount;

        if ($socialAccount) {
            $socialAccount->update([
                'provider_id' => $socialUser->getId(),
                'provider_email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
                'provider_data' => [
                    'nickname' => $socialUser->getNickname(),
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'avatar' => $socialUser->getAvatar(),
                ],
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken ?? null,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
            ]);
        } else {
            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => 'github',
                'provider_id' => $socialUser->getId(),
                'provider_email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
                'provider_data' => [
                    'nickname' => $socialUser->getNickname(),
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'avatar' => $socialUser->getAvatar(),
                ],
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken ?? null,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
            ]);
        }

        return redirect()->route('projects.create');
    }
}
