<?php

namespace Database\Seeders;

use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Demo family "The Demos" (Family plan, America/Toronto):
 *
 *   Mom   owner + admin, adult   mom@brainyclan.test / password
 *   Dad   member, adult          dad@brainyclan.test / password
 *   Sam   child, 9 years old     device token: demo-sam-child-token
 *   Alex  teen, 14 years old     device token: demo-alex-teen-token
 *
 * Pending invite codes (valid for a year, re-run this seeder to reset them):
 *
 *   CUBS2222  "Robin", child, 7 years old
 *   TEEN2828  "Jordan", teen, 15 years old
 *
 * Children have no email or password; their known tokens let you call the API as them:
 *   curl -H "Authorization: Bearer demo-sam-child-token" http://localhost:8080/api/v1/me
 *
 * Ages are relative to the current year (born in January), so they stay 9 and 14.
 */
class DemoFamilySeeder extends Seeder
{
    public const string SAM_TOKEN = 'demo-sam-child-token';

    public const string ALEX_TOKEN = 'demo-alex-teen-token';

    public function run(): void
    {
        $year = (int) now()->year;

        $mom = $this->adult('mom@brainyclan.test', 'Mom', 'owl', 1988, 4);
        $dad = $this->adult('dad@brainyclan.test', 'Dad', 'bear', 1986, 9);

        $family = Family::query()->firstOrCreate(
            ['owner_user_id' => $mom->id],
            ['name' => 'The Demos', 'timezone' => 'America/Toronto', 'country_code' => 'CA', 'tier' => Family::TIER_FAMILY],
        );

        $this->member($family, $mom, 'Mom', FamilyMember::ROLE_ADMIN);
        $this->member($family, $dad, 'Dad', FamilyMember::ROLE_MEMBER);

        $sam = $this->child($family, 'Sam', 'fox', $year - 9);
        $alex = $this->child($family, 'Alex', 'dragon', $year - 14);

        $this->deviceToken($sam, self::SAM_TOKEN, 'Sam\'s tablet');
        $this->deviceToken($alex, self::ALEX_TOKEN, 'Alex\'s phone');

        $this->invite($family, $mom, 'CUBS2222', 'Robin', 'penguin', $year - 7);
        $this->invite($family, $mom, 'TEEN2828', 'Jordan', 'tiger', $year - 15);
    }

    private function adult(string $email, string $name, string $avatar, int $birthYear, int $birthMonth): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill([
            'display_name' => $name,
            'avatar' => $avatar,
            'birth_year' => $birthYear,
            'birth_month' => $birthMonth,
            'locale' => 'en',
        ]);

        if (! $user->exists) {
            $user->password = 'password';
        }

        $user->save();

        return $user;
    }

    private function member(Family $family, User $user, string $nickname, string $role): FamilyMember
    {
        return FamilyMember::query()->updateOrCreate(
            ['family_id' => $family->id, 'user_id' => $user->id],
            ['nickname' => $nickname, 'role' => $role, 'status' => FamilyMember::STATUS_ACTIVE, 'joined_at' => now()],
        );
    }

    private function child(Family $family, string $nickname, string $avatar, int $birthYear): User
    {
        $existing = $family->allMembers()->where('nickname', $nickname)->with('user')->first();
        $user = $existing !== null ? $existing->user : new User;

        $user->fill([
            'display_name' => $nickname,
            'avatar' => $avatar,
            'birth_year' => $birthYear,
            'birth_month' => 1,
            'locale' => 'en',
        ])->save();

        $this->member($family, $user, $nickname, FamilyMember::ROLE_MEMBER);

        return $user;
    }

    private function deviceToken(User $user, string $plainToken, string $deviceName): void
    {
        $hash = hash('sha256', $plainToken);

        $token = PersonalAccessToken::query()->where('token', $hash)->first()
            ?? $user->tokens()->create(['name' => $deviceName, 'token' => $hash, 'abilities' => ['*']]);

        Device::query()->updateOrCreate(
            ['personal_access_token_id' => $token->getKey()],
            ['user_id' => $user->id, 'name' => $deviceName, 'platform' => 'web', 'app_version' => '1.0.0'],
        );
    }

    private function invite(Family $family, User $creator, string $code, string $nickname, string $avatar, int $birthYear): void
    {
        Invite::query()->updateOrCreate(
            ['code' => $code],
            [
                'family_id' => $family->id,
                'created_by' => $creator->id,
                'nickname' => $nickname,
                'avatar' => $avatar,
                'birth_year' => $birthYear,
                'birth_month' => 1,
                'expires_at' => now()->addYear(),
                'used_by' => null,
                'used_at' => null,
            ],
        );
    }
}
