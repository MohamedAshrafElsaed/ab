<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Log;

class SocialAuthController extends Controller
{
    protected array $providers = ['github', 'google'];

    /**
     * Redirect to OAuth provider
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (! in_array($provider, $this->providers)) {
            abort(404);
        }

        $driver = Socialite::driver($provider);

        if ($provider === 'github') {
            $driver->scopes(['read:user', 'user:email', 'repo']);
        }

        return $driver->redirect();
    }

    /**
     * Handle OAuth callback
     */
    public function callback(string $provider): RedirectResponse
    {
        if (! in_array($provider, $this->providers)) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
            if ($provider === 'github' && empty($socialUser->getEmail())) {
                $email = $this->getGitHubEmail($socialUser->token);
                if ($email) {
                    $socialUser->email = $email;
                }
            }

        } catch (Exception $e) {
            return redirect()->route('login')->with('error', 'Authentication failed: '.$e->getMessage());
        }

        if (empty($socialUser->getEmail())) {
            return redirect()->route('login')->with('error', 'Unable to retrieve email from '.ucfirst($provider).'. Please make sure your email is public or try another provider.');
        }

        $user = $this->handleOAuthUser($provider, $socialUser);

        if (! $user) {
            return redirect()->route('login')->with('error', 'Unable to authenticate. Please try again.');
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Fetch primary email from GitHub API
     */
    protected function getGitHubEmail(string $token): ?string
    {
        try {
            $response = Http::withToken($token)
                ->accept('application/vnd.github.v3+json')
                ->get('https://api.github.com/user/emails');

            if ($response->successful()) {
                $emails = $response->json();

                foreach ($emails as $email) {
                    if ($email['primary'] && $email['verified']) {
                        return $email['email'];
                    }
                }

                foreach ($emails as $email) {
                    if ($email['verified']) {
                        return $email['email'];
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to fetch GitHub email: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Handle OAuth user - find existing or create new
     */
    protected function handleOAuthUser(string $provider, SocialiteUser $socialUser): ?User
    {
        $email = $socialUser->getEmail();

        if (! $email) {
            return null;
        }

        return DB::transaction(function () use ($provider, $socialUser, $email) {
            $socialAccount = SocialAccount::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($socialAccount) {
                $socialAccount->update([
                    'avatar' => $socialUser->getAvatar(),
                    'provider_email' => $email,
                    'provider_data' => $this->getProviderData($socialUser),
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken ?? null,
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                ]);

                return $socialAccount->user;
            }

            $user = User::where('email', $email)->first();

            if ($user) {
                $this->createSocialAccount($user, $provider, $socialUser);

                return $user;
            }

            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'avatar' => $socialUser->getAvatar(),
            ]);

            $this->createSocialAccount($user, $provider, $socialUser);

            return $user;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getProviderData(SocialiteUser $socialUser): array
    {
        return [
            'nickname' => $socialUser->getNickname(),
            'name' => $socialUser->getName(),
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
        ];
    }

    protected function createSocialAccount(User $user, string $provider, SocialiteUser $socialUser): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'provider_email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
            'provider_data' => $this->getProviderData($socialUser),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
        ]);
    }
}
