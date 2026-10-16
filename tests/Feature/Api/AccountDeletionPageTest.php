<?php

use App\Modules\Family\Mail\AccountDeletionConfirmation;
use App\Modules\Family\Models\AccountDeletionRequest;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Actions\EraseUser;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Mail;

/**
 * Public account deletion page `/account/delete` (docs/API.md "Account deletion web page").
 */
beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Mail::fake();
});

/**
 * Requests deletion for `$email` and returns the token from the queued confirmation mail.
 */
function requestDeletionToken(string $email): string
{
    test()->post('/account/delete', ['email' => $email])->assertOk();

    $url = null;
    Mail::assertQueued(AccountDeletionConfirmation::class, function (AccountDeletionConfirmation $mail) use (&$url) {
        $url = $mail->confirmUrl;

        return true;
    });

    return (string) preg_replace('#.*/confirm/([A-Za-z0-9]{64}).*#', '$1', (string) $url);
}

it('explains how to delete the account in English and French with strict headers', function () {
    $this->get('/account/delete')
        ->assertOk()
        ->assertSee('Profile → Privacy → Delete account')
        ->assertSee('name="website"', false)
        ->assertHeader('X-Frame-Options', 'DENY');

    $response = $this->get('/account/delete?lang=fr')->assertOk()->assertSee('Supprimer votre compte Brainy Clan');
    expect($response->headers->get('Content-Security-Policy'))->toContain("form-action 'self'");
});

it('emails a one-time link and answers the same whether or not the email has an account', function () {
    $user = User::factory()->aged(40)->create(['email' => 'mom@example.com']);

    $known = $this->post('/account/delete', ['email' => 'MOM@example.com'])->assertOk()->getContent();
    Mail::assertQueued(AccountDeletionConfirmation::class, fn (AccountDeletionConfirmation $mail) => $mail->hasTo('mom@example.com')
        && str_contains($mail->confirmUrl, '/account/delete/confirm/'));

    $unknown = $this->post('/account/delete', ['email' => 'nobody@example.com'])->assertOk()->getContent();
    Mail::assertQueuedCount(1);

    $strip = fn (string $html) => preg_replace('/name="_token" value="[^"]*"/', '', $html);
    expect($strip($known))->toBe($strip($unknown))
        ->and(AccountDeletionRequest::query()->count())->toBe(2)
        ->and(AccountDeletionRequest::query()->where('user_id', $user->id)->sole()->status)->toBe('pending')
        ->and(AccountDeletionRequest::query()->whereNull('user_id')->sole()->status)->toBe('no_account')
        ->and(AccountDeletionRequest::query()->pluck('email_hash')->implode(''))->not->toContain('mom@');
});

it('ignores the honeypot and validates the email', function () {
    User::factory()->aged(40)->create(['email' => 'mom@example.com']);

    $this->post('/account/delete', ['email' => 'mom@example.com', 'website' => 'http://spam'])->assertOk();
    Mail::assertNothingQueued();
    expect(AccountDeletionRequest::query()->count())->toBe(0);

    $this->post('/account/delete', ['email' => 'not-an-email'])->assertSessionHasErrors('email');
});

it('deletes the account only after the confirmation button, once', function () {
    $owner = familyOwner();
    $email = $owner->user->email;
    $token = requestDeletionToken($email);

    // Opening the link (e.g. a mail scanner) deletes nothing.
    $this->get("/account/delete/confirm/{$token}")->assertOk()->assertSee('Delete my account');
    expect($owner->user->fresh()->erased_at)->toBeNull();

    $this->post("/account/delete/confirm/{$token}")->assertOk()->assertSee('Your account has been deleted.');

    $user = $owner->user->fresh();
    expect($user->erased_at)->not->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->display_name)->toBe(EraseUser::ERASED_NAME)
        ->and(AccountDeletionRequest::query()->sole()->status)->toBe('completed');

    $this->post("/account/delete/confirm/{$token}")->assertOk()->assertSee('This link is no longer valid');
});

it('refuses expired and superseded links', function () {
    $user = User::factory()->aged(40)->create(['email' => 'dad@example.com']);
    $old = requestDeletionToken('dad@example.com');
    Mail::fake();
    $new = requestDeletionToken('dad@example.com');

    $this->post("/account/delete/confirm/{$old}")->assertSee('This link is no longer valid');

    $this->travel(61)->minutes();
    $this->post("/account/delete/confirm/{$new}")->assertSee('This link is no longer valid');
    expect($user->fresh()->erased_at)->toBeNull();
});

it('explains instead of deleting when the owner still has family members', function () {
    $owner = familyOwner();
    addMember($owner->family, 9);
    $token = requestDeletionToken($owner->user->email);

    $this->post("/account/delete/confirm/{$token}")->assertOk()->assertSee('You own a family that has other members');

    expect($owner->user->fresh()->erased_at)->toBeNull()
        ->and($owner->fresh()->status)->toBe(FamilyMember::STATUS_ACTIVE)
        ->and(AccountDeletionRequest::query()->sole()->status)->toBe('blocked');
});

it('rate-limits the form per IP', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/account/delete', ['email' => "x{$i}@example.com"])->assertOk();
    }

    $this->post('/account/delete', ['email' => 'x6@example.com'])->assertStatus(429);
});
