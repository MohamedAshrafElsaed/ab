<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_id
 * @property string|null $provider_email
 * @property string|null $avatar
 * @property array<array-key, mixed>|null $provider_data
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static Builder<static>|SocialAccount newModelQuery()
 * @method static Builder<static>|SocialAccount newQuery()
 * @method static Builder<static>|SocialAccount query()
 * @method static Builder<static>|SocialAccount whereAccessToken($value)
 * @method static Builder<static>|SocialAccount whereAvatar($value)
 * @method static Builder<static>|SocialAccount whereCreatedAt($value)
 * @method static Builder<static>|SocialAccount whereId($value)
 * @method static Builder<static>|SocialAccount whereProvider($value)
 * @method static Builder<static>|SocialAccount whereProviderData($value)
 * @method static Builder<static>|SocialAccount whereProviderEmail($value)
 * @method static Builder<static>|SocialAccount whereProviderId($value)
 * @method static Builder<static>|SocialAccount whereRefreshToken($value)
 * @method static Builder<static>|SocialAccount whereTokenExpiresAt($value)
 * @method static Builder<static>|SocialAccount whereUpdatedAt($value)
 * @method static Builder<static>|SocialAccount whereUserId($value)
 * @mixin Eloquent
 */
class SocialAccount extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'provider_email',
        'avatar',
        'provider_data',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider_data' => 'array',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasValidToken(): bool
    {
        if (!$this->access_token) {
            return false;
        }

        if ($this->token_expires_at && $this->token_expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
