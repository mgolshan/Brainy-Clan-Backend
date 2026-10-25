<?php

use App\Modules\Billing\Mail\WebCheckoutMail;
use App\Modules\Core\Console\MailPreviewCommand;
use App\Modules\Core\Mail\OpsAlertMail;
use App\Modules\Core\Support\Brand;
use App\Modules\Family\Mail\AccountDeletionConfirmation;
use App\Modules\Family\Mail\ChildDataExport;
use App\Modules\Identity\Mail\PasswordChanged;
use App\Modules\Identity\Mail\PasswordResetLink;
use App\Modules\Identity\Mail\SignInAttemptsNotice;
use App\Modules\Identity\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Horizon\Notifications\LongWaitDetected;
use Symfony\Component\Finder\Finder;

/**
 * CENTRAL_DOMAIN, the brand (one logo file for the web, the admin and every email) and the one
 * email template (resources/views/vendor/mail, README "Emails"), plus `mail:preview`.
 */

/**
 * One instance of every mailable of the app, in a locale.
 *
 * @return array<string, Mailable>
 */
function everyMailable(string $locale): array
{
    return [
        'password reset link' => new PasswordResetLink('https://brainyclan.test/password/reset?token=abc&lang='.$locale, $locale, 60),
        'password changed' => new PasswordChanged($locale),
        'sign-in attempts' => new SignInAttemptsNotice($locale, 15),
        'account deletion' => new AccountDeletionConfirmation('https://brainyclan.test/account/delete/confirm/'.str_repeat('a', 64), $locale),
        'web checkout link' => new WebCheckoutMail($locale, 'annual', 'https://brainyclan.test/subscribe/checkout?token=t', null, null),
        'web checkout refused' => new WebCheckoutMail($locale, 'annual', null, 'store_plan_active', 'app_store'),
        'child data export' => new ChildDataExport(User::factory()->create(), 'Little <b>Fox</b> *star*', $locale),
        'ops alert' => new OpsAlertMail('[Brainy Clan testing] Queued job failed: X', "Job: App\\Jobs\\X\n\nQueue: redis / default"),
    ];
}

describe('central domain', function () {
    it('derives the config defaults from CENTRAL_DOMAIN', function () {
        $keys = ['CENTRAL_DOMAIN', 'LEGAL_SUPPORT_EMAIL', 'LEGAL_CONTACT_EMAIL', 'OPS_ALERT_EMAIL', 'MAIL_FROM_ADDRESS'];
        $saved = [];

        foreach ($keys as $key) {
            $saved[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }

        $_ENV['CENTRAL_DOMAIN'] = $_SERVER['CENTRAL_DOMAIN'] = 'example-clan.org';

        try {
            $app = require config_path('app.php');
            $legal = require config_path('legal.php');
            $ops = require config_path('ops.php');
            $mail = require config_path('mail.php');

            expect($app['central_domain'])->toBe('example-clan.org')
                ->and($legal['support_email'])->toBe('support@example-clan.org')
                ->and($legal['contact']['email'])->toBe('support@example-clan.org')
                ->and($ops['alert_email'])->toBe('support@example-clan.org')
                ->and($mail['from']['address'])->toBe('no-reply@example-clan.org');
        } finally {
            foreach ($saved as $key => [$env, $server, $getenv]) {
                unset($_ENV[$key], $_SERVER[$key]);
                $env !== null && $_ENV[$key] = $env;
                $server !== null && $_SERVER[$key] = $server;
                $getenv !== false ? putenv("{$key}={$getenv}") : putenv($key);
            }
        }
    });

    it('expands ${CENTRAL_DOMAIN} in both env examples the way Laravel reads them', function () {
        $prod = Dotenv\Dotenv::parse((string) file_get_contents(base_path('.env.prod.example')));
        $dev = Dotenv\Dotenv::parse((string) file_get_contents(base_path('.env.example')));

        expect($prod['API_DOMAIN'])->toBe('api.brainyclan.com')
            ->and($prod['ACME_EMAIL'])->toBe('support@brainyclan.com')
            ->and($prod['LEGAL_SUPPORT_EMAIL'])->toBe('support@brainyclan.com')
            ->and($prod['LEGAL_CONTACT_EMAIL'])->toBe('support@brainyclan.com')
            ->and($prod['OPS_ALERT_EMAIL'])->toBe('support@brainyclan.com')
            ->and($dev['CENTRAL_DOMAIN'])->toBe('brainyclan.com')
            ->and($dev['MAIL_FROM_ADDRESS'])->toBe('no-reply@brainyclan.com')
            ->and($dev['APP_URL'])->toBe('http://localhost:8080');
    });

    it('keeps the domain out of code and views (comments describing production are fine)', function () {
        $offenders = [];

        foreach (Finder::create()->files()->in([app_path(), resource_path('views')])->name('*.php')->contains('brainyclan.com') as $file) {
            foreach ((array) file($file->getPathname()) as $number => $line) {
                if (str_contains((string) $line, 'brainyclan.com') && preg_match('/^\s*(\*|\/\/|\/\*)/', (string) $line) !== 1) {
                    $offenders[] = $file->getRelativePathname().':'.($number + 1);
                }
            }
        }

        expect($offenders)->toBe([]);
    });
});

describe('brand', function () {
    it('has both logo files, the PNG at 192×192', function () {
        expect(is_file(public_path(config('brand.logo'))))->toBeTrue()
            ->and(file_get_contents(public_path(config('brand.logo'))))->toContain('<svg');

        $size = getimagesize(public_path(config('brand.logo_png')));
        expect($size['mime'] ?? null)->toBe('image/png')
            ->and([$size[0], $size[1]])->toBe([192, 192]);
    });

    it('builds versioned URLs: relative on the web, absolute on the website for emails', function () {
        config(['app.website_url' => 'https://brainyclan.com']);

        expect(Brand::logoUrl())->toMatch('#^/images/brand/logo\.svg\?v=[0-9a-f]{8}$#')
            ->and(Brand::pngLogoUrl())->toMatch('#^/images/brand/logo\.png\?v=[0-9a-f]{8}$#')
            ->and(Brand::emailLogoUrl())->toMatch('#^https://brainyclan\.com/images/brand/logo\.png\?v=[0-9a-f]{8}$#')
            ->and(Brand::websiteLabel())->toBe('brainyclan.com')
            ->and(Brand::websiteUrl('fr'))->toBe('https://brainyclan.com/?lang=fr');
    });

    it('changes the fingerprint when the logo file is replaced and falls back without a file', function () {
        // A scratch public/ (the real one is a Windows bind mount where touch() can't set times).
        $public = sys_get_temp_dir().'/brand-test-'.uniqid();
        mkdir($public.'/images/brand', 0777, true);
        $file = $public.'/images/brand/logo.svg';
        file_put_contents($file, '<svg/>');
        touch($file, 1_700_000_000);
        app()->usePublicPath($public);

        try {
            $before = Brand::logoUrl();
            touch($file, 1_700_000_060);
            clearstatcache(true, $file);
            expect(Brand::logoUrl())->not->toBe($before)->toStartWith('/images/brand/logo.svg?v=');
        } finally {
            unlink($file);
            rmdir($public.'/images/brand');
            rmdir($public.'/images');
            rmdir($public);
        }

        config(['brand.logo' => 'images/brand/missing.svg']);
        expect(Brand::logoUrl())->toBe('/images/brand/missing.svg');
    });

    it('uses the brand logo on the website, the legal pages and the admin', function () {
        $logo = e(Brand::logoUrl());

        $this->get('/')->assertOk()
            ->assertSee('<link rel="icon" href="'.$logo.'" type="image/svg+xml">', false)
            ->assertSee('<img src="'.$logo.'" alt="" width="44" height="44">', false);
        $this->get(route('legal.privacy'))->assertOk()->assertSee('src="'.$logo.'"', false);
        $this->get(route('account.delete'))->assertOk()->assertSee('src="'.$logo.'"', false);
        $this->get(route('password.reset'))->assertOk()->assertSee('src="'.$logo.'"', false);
        $this->get('/admin/login')->assertOk()->assertSee($logo, false);

        $views = Finder::create()->files()->in(resource_path('views'))->contains('images/owl.svg');
        expect(iterator_count($views))->toBe(0);
    });
});

describe('email template', function () {
    it('renders every mail in the shared frame with the PNG logo, footer and locale', function (string $locale) {
        config(['app.website_url' => 'https://brainyclan.com']);

        foreach (everyMailable($locale) as $name => $mailable) {
            $html = (string) $mailable->render();
            $mailLocale = $mailable instanceof OpsAlertMail ? 'en' : $locale;

            expect($html)->toContain('src="'.e(Brand::emailLogoUrl()).'"')
                ->toContain('class="header-name"')
                ->toContain('<html lang="'.$mailLocale.'"')
                ->toContain('mailto:'.config('legal.support_email'))
                ->toContain('© '.date('Y').' Brainy Clan')
                ->toContain('background-color: #fffaf3')
                ->not->toContain('.svg')
                ->not->toContain('<script')
                ->and(substr_count($html, '<img'))->toBe(1, "{$name}: only the logo image (no tracking pixels)");

            $mailable->assertSeeInText(config('legal.support_email'))
                ->assertSeeInText('© '.date('Y').' Brainy Clan')
                ->assertDontSeeInText('<table');
        }
    })->with(['en', 'fr']);

    it('keeps the French and English wording and the links in each mail', function () {
        config(['app.website_url' => 'https://brainyclan.com']);

        $fr = new PasswordResetLink('https://brainyclan.test/password/reset?token=abc&lang=fr', 'fr', 60);
        $fr->assertSeeInHtml('Choisir un nouveau mot de passe')
            ->assertSeeInHtml('href="https://brainyclan.test/password/reset?token=abc&amp;lang=fr"', false)
            ->assertSeeInText('https://brainyclan.test/password/reset?token=abc&lang=fr')
            ->assertSeeInHtml('Des questions ?')
            ->assertSeeInHtml('https://brainyclan.com/?lang=fr', false);

        (new AccountDeletionConfirmation('https://brainyclan.test/account/delete/confirm/x', 'en'))
            ->assertSeeInHtml('Open the confirmation page')
            ->assertSeeInHtml('deletion of the account with this address was requested');
    });

    it('escapes family-chosen text instead of reading it as Markdown or HTML', function () {
        $html = (string) (new ChildDataExport(User::factory()->create(), 'Little <b>Fox</b> *star*', 'en'))->render();

        expect($html)->toContain('Little &lt;b&gt;Fox&lt;/b&gt; *star*')
            ->not->toContain('<b>Fox</b>')
            ->not->toContain('<em>star</em>');
    });

    it('sends Horizon notifications in the same frame', function () {
        Notification::route('mail', 'ops@example.test')->notifyNow(new LongWaitDetected('redis', 'default', 75));

        $message = Mail::getFacadeRoot()->mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();

        expect($message->getHtmlBody())->toContain(e(Brand::emailLogoUrl()))->toContain('class="header-name"');
    });
});

describe('mail:preview', function () {
    it('refuses to run in production', function () {
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('mail:preview', ['email' => 'moe@example.test'])
            ->expectsOutputToContain('disabled in production')
            ->assertFailed();

        expect(Mail::getFacadeRoot()->mailer()->getSymfonyTransport()->messages())->toHaveCount(0);
    });

    it('sends one sample of every mail type, in the chosen language', function () {
        $this->artisan('mail:preview', ['email' => 'moe@example.test', '--pause' => 0])->assertSuccessful();
        $sent = Mail::getFacadeRoot()->mailer()->getSymfonyTransport()->messages();

        expect($sent)->toHaveCount(9)
            ->and($sent->every(fn ($message): bool => $message->getOriginalMessage()->getTo()[0]->getAddress() === 'moe@example.test'))->toBeTrue();

        $this->artisan('mail:preview', ['email' => 'moe@example.test', '--locale' => 'fr', '--pause' => 0])->assertSuccessful();
        $subjects = Mail::getFacadeRoot()->mailer()->getSymfonyTransport()->messages()->slice(9)
            ->map(fn ($message): string => (string) $message->getOriginalMessage()->getSubject());

        expect($subjects)->toHaveCount(7)
            ->and($subjects->first())->toBe('Réinitialisez votre mot de passe Brainy Clan');
    });

    it('covers every Mailable class of the app', function () {
        $classes = collect(iterator_to_array(Finder::create()->files()->in(app_path('Modules'))->path('#(^|/)Mail/#')->name('*.php')))
            ->map(fn (SplFileInfo $file): string => $file->getBasename('.php'))
            ->sort()->values();
        $covered = collect(everyMailable('en'))->map(fn (Mailable $mailable): string => class_basename($mailable::class))->unique()->sort()->values();

        expect($covered->all())->toBe($classes->all())
            ->and(array_keys(app(MailPreviewCommand::class)->samples('en')))->toHaveCount(9);
    });

    it('rejects a bad address or locale', function () {
        $this->artisan('mail:preview', ['email' => 'not-an-email'])->assertFailed();
        $this->artisan('mail:preview', ['email' => 'moe@example.test', '--locale' => 'de'])->assertFailed();
    });
});
