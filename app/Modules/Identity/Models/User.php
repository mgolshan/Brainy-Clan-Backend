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
use Illuminate\Support\Carbon;
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
 * @property Carbon|null $erased_at Account deleted: personal data erased (`EraseUser`)
 */
#[Fillable(['display_name', 'avatar', 'email', 'password', 'birth_year', 'birth_month', 'locale'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Fixed set of avatar keys the app can render: the Clan characters, 22 species × kid, teen,
     * mom, dad, grandma, grandpa (see docs/API.md "Avatars").
     */
    public const array AVATARS = [
        'fox', 'fox-teen', 'fox-mom', 'fox-dad', 'fox-grandma', 'fox-grandpa',
        'owl', 'owl-teen', 'owl-mom', 'owl-dad', 'owl-grandma', 'owl-grandpa',
        'panda', 'panda-teen', 'panda-mom', 'panda-dad', 'panda-grandma', 'panda-grandpa',
        'lion', 'lion-teen', 'lion-mom', 'lion-dad', 'lion-grandma', 'lion-grandpa',
        'koala', 'koala-teen', 'koala-mom', 'koala-dad', 'koala-grandma', 'koala-grandpa',
        'penguin', 'penguin-teen', 'penguin-mom', 'penguin-dad', 'penguin-grandma', 'penguin-grandpa',
        'tiger', 'tiger-teen', 'tiger-mom', 'tiger-dad', 'tiger-grandma', 'tiger-grandpa',
        'rabbit', 'rabbit-teen', 'rabbit-mom', 'rabbit-dad', 'rabbit-grandma', 'rabbit-grandpa',
        'bear', 'bear-teen', 'bear-mom', 'bear-dad', 'bear-grandma', 'bear-grandpa',
        'frog', 'frog-teen', 'frog-mom', 'frog-dad', 'frog-grandma', 'frog-grandpa',
        'cat', 'cat-teen', 'cat-mom', 'cat-dad', 'cat-grandma', 'cat-grandpa',
        'dog', 'dog-teen', 'dog-mom', 'dog-dad', 'dog-grandma', 'dog-grandpa',
        'unicorn', 'unicorn-teen', 'unicorn-mom', 'unicorn-dad', 'unicorn-grandma', 'unicorn-grandpa',
        'dragon', 'dragon-teen', 'dragon-mom', 'dragon-dad', 'dragon-grandma', 'dragon-grandpa',
        'turtle', 'turtle-teen', 'turtle-mom', 'turtle-dad', 'turtle-grandma', 'turtle-grandpa',
        'octopus', 'octopus-teen', 'octopus-mom', 'octopus-dad', 'octopus-grandma', 'octopus-grandpa',
        'elephant', 'elephant-teen', 'elephant-mom', 'elephant-dad', 'elephant-grandma', 'elephant-grandpa',
        'giraffe', 'giraffe-teen', 'giraffe-mom', 'giraffe-dad', 'giraffe-grandma', 'giraffe-grandpa',
        'hedgehog', 'hedgehog-teen', 'hedgehog-mom', 'hedgehog-dad', 'hedgehog-grandma', 'hedgehog-grandpa',
        'otter', 'otter-teen', 'otter-mom', 'otter-dad', 'otter-grandma', 'otter-grandpa',
        'raccoon', 'raccoon-teen', 'raccoon-mom', 'raccoon-dad', 'raccoon-grandma', 'raccoon-grandpa',
        'monkey', 'monkey-teen', 'monkey-mom', 'monkey-dad', 'monkey-grandma', 'monkey-grandpa',
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
            'erased_at' => 'datetime',
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
        return ! $this->hasOwnLogin();
    }

    /**
     * The user can sign in without the family: an email login or a linked Google/Apple/Facebook
     * account. Uses an eager-loaded `withExists('socialAccounts')` / `socialAccounts` relation
     * when present (lists must load one of them), otherwise one query.
     */
    public function hasOwnLogin(): bool
    {
        if ($this->email !== null) {
            return true;
        }

        if (array_key_exists('social_accounts_exists', $this->getAttributes())) {
            return (bool) $this->getAttribute('social_accounts_exists');
        }

        if ($this->relationLoaded('socialAccounts')) {
            return $this->socialAccounts->isNotEmpty();
        }

        return $this->socialAccounts()->exists();
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
