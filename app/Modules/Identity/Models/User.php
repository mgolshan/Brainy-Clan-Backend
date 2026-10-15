<?php

namespace App\Modules\Identity\Models;

use App\Modules\Family\Models\FamilyMember;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A person using the app. Adults have email + password; children only a nickname
 * (display_name) and an avatar, and sign in with a device-bound token from an invite.
 *
 * @property int $id
 * @property string $display_name
 * @property string|null $avatar
 * @property string|null $email
 * @property string|null $password
 * @property int|null $birth_year
 * @property int|null $birth_month
 * @property string $locale
 * @property bool $is_staff
 */
#[Fillable(['display_name', 'avatar', 'email', 'password', 'birth_year', 'birth_month', 'locale'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Fixed set of avatar keys the app can render (see docs/API.md).
     */
    public const array AVATARS = [
        'fox', 'owl', 'panda', 'lion', 'koala', 'penguin', 'tiger', 'rabbit',
        'bear', 'frog', 'cat', 'dog', 'unicorn', 'dragon', 'turtle', 'octopus',
    ];

    public const array LOCALES = ['en', 'fr'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_year' => 'integer',
            'birth_month' => 'integer',
            'is_staff' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FamilyMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /**
     * @return HasOne<FamilyMember, $this>
     */
    public function activeMember(): HasOne
    {
        return $this->hasOne(FamilyMember::class)->where('status', FamilyMember::STATUS_ACTIVE)->latestOfMany();
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Google / Facebook / Apple accounts linked for social sign-in.
     *
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * The user's active family membership (a user belongs to at most one family for now).
     */
    public function activeMembership(): ?FamilyMember
    {
        /** @var FamilyMember|null $member */
        $member = $this->activeMember;

        return $member;
    }

    /**
     * No email login and no Google/Facebook account: signs in with invite / sign-in codes only.
     * (A social-only adult may have no email, e.g. a phone-only Facebook account.)
     */
    public function isChildAccount(): bool
    {
        return $this->email === null && ! $this->socialAccounts()->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_staff;
    }

    public function getFilamentName(): string
    {
        return $this->display_name;
    }
}
