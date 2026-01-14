<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'github',
            'provider_id' => (string) fake()->randomNumber(8),
            'provider_email' => fake()->safeEmail(),
            'avatar' => fake()->imageUrl(),
            'provider_data' => [],
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
        ];
    }

    public function withGitHubToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'github',
            'access_token' => 'ghu_' . fake()->sha256(),
        ]);
    }

    public function google(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'google',
        ]);
    }
}
