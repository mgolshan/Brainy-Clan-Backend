<?php

use App\Modules\Support\Mail\SupportMessageReceived;
use App\Modules\Support\Models\SupportMessage;
use App\Modules\Support\Support\FormToken;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/**
 * The website contact form on /support (docs/PLAN.md "Support channel without a public mailto"):
 * stateless (no cookies, no session, no CSRF), honeypot + signed form-time token + per-IP limit,
 * silent success for bots, POST-redirect-GET, accessible errors in en/fr, strict CSP, no mail to
 * the sender.
 */
beforeEach(function () {
    Mail::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function contactPost(array $overrides = [], string $query = ''): TestResponse
{
    return test()->post('/support'.$query, [
        'name' => 'Sam Parent',
        'email' => 'sam.parent@example.com',
        'topic' => 'bug',
        'message' => 'The maze game freezes on level 3 on my tablet.',
        'website' => '',
        'form_token' => FormToken::issue(time() - 10),
        ...$overrides,
    ]);
}

it('shows the contact form with a signed token, the topics and a honeypot, without cookies or scripts', function (string $lang, array $texts) {
    $response = $this->get("/support?lang={$lang}")->assertOk();
    $html = (string) $response->getContent();

    expect($html)->toContain('<section id="contact" aria-labelledby="contact-title">')
        ->toContain('<form class="contact" method="post" action="'.route('website.support.submit', ['lang' => $lang]).'#contact">')
        ->toMatch('/<input type="hidden" name="form_token" value="\d+\.[a-f0-9]{64}">/')
        ->toContain('<div class="hp" aria-hidden="true">')
        ->toContain('name="website" type="text" tabindex="-1" autocomplete="off"')
        ->not->toContain('mailto:')
        ->not->toContain('<script')
        ->not->toContain('name="_token"')
        ->not->toMatch('/\son[a-z]+="/i')
        ->not->toContain(' style="')
        ->and($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('Content-Security-Policy'))
        ->toBe("default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

    foreach (SupportMessage::TOPICS as $topic) {
        expect($html)->toContain('<option value="'.$topic.'"');
    }

    foreach ($texts as $text) {
        expect($html)->toContain($text);
    }
})->with([
    'en' => ['en', ['Your name', 'Your email', 'Send message', 'Profile → Contact support', 'Something doesn’t work']],
    'fr' => ['fr', ['Votre nom', 'Votre courriel', 'Envoyer le message', 'Profil → Contacter l’assistance', 'Quelque chose ne fonctionne pas']],
]);

it('preselects a topic from ?topic=', function () {
    expect((string) $this->get('/support?topic=privacy')->getContent())->toContain('<option value="privacy" selected')
        ->and((string) $this->get('/support?topic=nope')->getContent())->toContain('<option value="" selected');
});

it('stores the message, mails the support inbox with Reply-To = the sender and redirects to the sent panel', function () {
    $response = contactPost(['name' => "Sam\r\nBcc: victim@example.com", 'message' => "  Hello <b>team</b>\r\n\r\nIt freezes.  "], '?lang=fr')
        ->assertStatus(303)
        ->assertRedirect(route('website.support', ['sent' => 1, 'lang' => 'fr']).'#contact');

    expect($response->headers->getCookies())->toBe([]);

    $message = SupportMessage::query()->sole();
    expect($message->source)->toBe('web')
        ->and($message->name)->toBe('Sam Bcc: victim@example.com')
        ->and($message->email)->toBe('sam.parent@example.com')
        ->and($message->locale)->toBe('fr')
        ->and($message->topic)->toBe('bug')
        ->and($message->message)->toBe("Hello <b>team</b>\n\nIt freezes.")
        ->and($message->user_id)->toBeNull()
        ->and($message->status)->toBe('new');

    Mail::assertQueued(SupportMessageReceived::class, fn (SupportMessageReceived $mail) => $mail->hasTo(config('legal.support_email'))
        && $mail->hasReplyTo('sam.parent@example.com'));
    // Never a mail to the sender.
    Mail::assertQueuedCount(1);
    Mail::assertNotQueued(SupportMessageReceived::class, fn (SupportMessageReceived $mail) => $mail->hasTo('sam.parent@example.com'));

    $this->get('/support?sent=1&lang=fr')->assertOk()
        ->assertSee('Merci, votre message est envoyé.', false)
        ->assertDontSee('<form class="contact"', false);
});

it('gives bots the same redirect but stores and sends nothing', function (string $case) {
    $overrides = match ($case) {
        'honeypot filled' => ['website' => 'https://spam.example'],
        'posted within 3 seconds' => ['form_token' => FormToken::issue(time() - 1)],
        'token missing' => ['form_token' => null],
        'token tampered' => ['form_token' => (time() - 60).'.'.str_repeat('a', 64)],
        'token from the future' => ['form_token' => FormToken::issue(time() + 600)],
        default => ['form_token' => ['x']],
    };

    contactPost($overrides)->assertStatus(303)->assertRedirect(route('website.support', ['sent' => 1, 'lang' => 'en']).'#contact');

    expect(SupportMessage::query()->count())->toBe(0);
    Mail::assertNothingQueued();
})->with(['honeypot filled', 'posted within 3 seconds', 'token missing', 'token tampered', 'token from the future', 'token not a string']);

it('signs the token with the app key: another key or a changed time fails', function () {
    $token = FormToken::issue(time() - 60);
    [$time, $signature] = explode('.', $token);

    expect(FormToken::check($token))->toBe(FormToken::VALID)
        ->and(FormToken::check(($time - 1).'.'.$signature))->toBe(FormToken::INVALID)
        ->and(FormToken::check(FormToken::issue(time() - 7201)))->toBe(FormToken::EXPIRED)
        ->and(FormToken::check(FormToken::issue(time() - 2)))->toBe(FormToken::TOO_FAST);

    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    expect(FormToken::check($token))->toBe(FormToken::INVALID);
});

it('shows the form again with the typed text when the token expired (a person who left the page open)', function () {
    $html = (string) contactPost(['form_token' => FormToken::issue(time() - 7300), 'message' => 'My long story about the maze.'])
        ->assertStatus(422)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->getContent();

    expect($html)->toContain('The form expired because the page was open for a long time.')
        ->toContain('>My long story about the maze.</textarea>')
        ->toContain('value="sam.parent@example.com"')
        ->and(SupportMessage::query()->count())->toBe(0);
});

it('re-renders with accessible errors and the old input, escaped, in the page language', function (string $lang, string $nameError, string $messageError) {
    $response = contactPost([
        'name' => '   ',
        'email' => 'not-an-email',
        'topic' => 'refund',
        'message' => '"><b>x',
    ], "?lang={$lang}")->assertStatus(422);
    $html = (string) $response->getContent();

    expect($html)->toContain('<div class="form-alert" role="alert">')
        ->toContain('<li><a href="#contact-name">'.e($nameError).'</a></li>')
        ->toContain('aria-invalid="true" ')
        ->toContain('id="contact-name-error"')
        ->toContain('id="contact-email-error"')
        ->toContain('id="contact-topic-error"')
        ->toContain('id="contact-message-error">'.e($messageError).'</p>')
        ->toContain('aria-describedby="contact-message-hint contact-message-error"')
        ->toContain('&quot;&gt;&lt;b&gt;x</textarea>')
        ->not->toContain('"><b>x')
        ->and($response->headers->getCookies())->toBe([])
        ->and(SupportMessage::query()->count())->toBe(0);

    Mail::assertNothingQueued();
})->with([
    'en' => ['en', 'Please enter your name (up to 100 characters).', 'Please write your message (10 to 5000 characters).'],
    'fr' => ['fr', 'Veuillez entrer votre nom (100 caractères au plus).', 'Veuillez écrire votre message (de 10 à 5000 caractères).'],
]);

it('rejects emails with line breaks or without a valid address', function (string $email) {
    contactPost(['email' => $email])->assertStatus(422);

    expect(SupportMessage::query()->count())->toBe(0);
})->with([
    "a@example.com\r\nBcc: x@example.com",
    "a@example.com\nX: y",
    'two@@example.com',
    '',
]);

it('limits each IP to 5 stored messages per hour, then shows the form with a note (429)', function () {
    foreach (range(1, 5) as $i) {
        contactPost()->assertStatus(303);
    }

    $html = (string) contactPost(['message' => 'Sixth message, please keep me.'])->assertStatus(429)->getContent();
    expect($html)->toContain('You sent us several messages recently.')
        ->toContain('Sixth message, please keep me.')
        ->and(SupportMessage::query()->count())->toBe(5);

    // Another IP is not affected; the next hour works again.
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.8.7']);
    contactPost()->assertStatus(303);
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
    $this->travel(61)->minutes();
    contactPost()->assertStatus(303);

    expect(SupportMessage::query()->count())->toBe(7);
});

it('keeps the website pages free of support mailto links, except the obfuscated address on the legal pages', function (string $path) {
    $html = (string) $this->get($path)->assertOk()->getContent();

    expect($html)->not->toContain('mailto:')
        ->not->toContain('support@brainyclan.com');

    if (in_array(strtok($path, '?'), ['/privacy', '/terms'], true)) {
        expect($html)->toContain('<span class="email">&#115;&#x75;&#112;&#x70;')
            ->and(html_entity_decode($html))->toContain('support@brainyclan.com');
    } elseif (! str_starts_with($path, '/account') && ! str_starts_with($path, '/password')) {
        // The website footer's "Contact us" goes to the form.
        expect($html)->toContain(route('website.support', ['lang' => str_contains($path, 'lang=fr') ? 'fr' : 'en']).'#contact');
    }
})->with(['/', '/?lang=fr', '/help', '/partners', '/support', '/privacy', '/privacy?lang=fr', '/terms', '/terms?lang=fr', '/account/delete', '/password/reset']);

it('mentions support messages in the privacy policy', function () {
    $this->get('/privacy')->assertOk()->assertSee('id="support-messages"', false)->assertSee('Messages to support');
    $this->get('/privacy?lang=fr')->assertOk()->assertSee('Messages à l’assistance', false);
});
