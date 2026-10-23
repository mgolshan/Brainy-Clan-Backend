<?php

use App\Modules\Family\Actions\RequestAccountDeletionByEmail;
use App\Modules\Family\Mail\AccountDeletionConfirmation;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * The public website (routes/web.php, docs/DEPLOY.md "Website and hosts"): landing page,
 * support, legal pages, robots.txt, sitemap.xml and production host routing.
 */

/**
 * Production host setup: website on brainyclan.com, API + admin on api.brainyclan.com.
 */
function productionHosts(): void
{
    config()->set('app.url', 'https://api.brainyclan.com');
    config()->set('app.website_url', 'https://brainyclan.com');
    config()->set('app.website_host', 'brainyclan.com');
}

describe('pages', function () {
    it('renders the landing page in English with SEO tags, the store notes and the footer links', function () {
        $response = $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('<title>Brainy Clan — Family learning challenges</title>', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('<meta property="og:title"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('hreflang="fr"', false)
            ->assertSee('Coming soon to the App Store')
            ->assertSee('Coming soon to Google Play')
            ->assertSee('No ads for children, ever.')
            ->assertSee('World Geography')
            ->assertSee('Vocabulary &amp; Spelling', false)
            ->assertSee('/images/owl.svg', false)
            ->assertSee(route('legal.privacy', ['lang' => 'en']), false)
            ->assertSee(route('legal.terms', ['lang' => 'en']), false)
            ->assertSee(route('website.support', ['lang' => 'en']), false)
            ->assertSee(route('account.delete', ['lang' => 'en']), false)
            ->assertSee('mailto:support@brainyclan.com', false)
            ->assertSee('© '.now()->year.' Brainy Clan', false)
            ->assertHeader('Content-Language', 'en')
            ->assertHeader('X-Frame-Options', 'DENY');

        expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")
            ->and($response->headers->get('Vary'))->toContain('Accept-Language')
            ->and($response->headers->getCookies())->toBe([])
            ->and($response->getContent())->not->toContain('<script')
            ->not->toContain('https://fonts.')
            ->not->toContain('cdn');
    });

    it('shows the real plan limits from the policy settings', function () {
        $this->get('/')
            ->assertOk()
            ->assertSee('Up to 2 family members, you included')
            ->assertSee('Up to 7 family members, you included')
            ->assertSee('3 categories')
            ->assertSee('All 8 categories')
            ->assertSee('1 active family goal')
            ->assertSee('Unlimited family goals')
            ->assertSee('3 free second chances per member per day')
            ->assertSee('No ads');
    });

    it('picks French from Accept-Language, and ?lang= wins over the browser', function () {
        $this->get('/', ['Accept-Language' => 'fr-CA,fr;q=0.9,en;q=0.5'])
            ->assertOk()
            ->assertSee('<html lang="fr">', false)
            ->assertSee('Bientôt sur l’App Store', false)
            ->assertSee('Aucune publicité pour les enfants, jamais.', false)
            ->assertSee('Jusqu’à 7 membres de la famille, vous compris', false)
            ->assertHeader('Content-Language', 'fr');

        $this->get('/?lang=en', ['Accept-Language' => 'fr'])->assertOk()->assertSee('<html lang="en">', false);
        $this->get('/?lang=fr')->assertOk()->assertSee('<html lang="fr">', false);
        $this->get('/?lang=de', ['Accept-Language' => 'de-DE'])->assertOk()->assertSee('<html lang="en">', false);
    });

    it('has a language switch to the other language on the same page', function () {
        $this->get('/support?lang=en')->assertOk()
            ->assertSee('href="'.url('/support').'?lang=fr"', false)
            ->assertSee('Français');

        $this->get('/support?lang=fr')->assertOk()
            ->assertSee('href="'.url('/support').'?lang=en"', false)
            ->assertSee('English');
    });

    it('renders the support page with the FAQ, the support email and links in both languages', function (string $lang, array $texts) {
        $response = $this->get("/support?lang={$lang}")->assertOk()
            ->assertSee('mailto:support@brainyclan.com', false)
            ->assertSee(route('account.delete', ['lang' => $lang]), false)
            ->assertSee('/password/reset', false)
            ->assertSee(route('legal.privacy', ['lang' => $lang]), false)
            ->assertSee('15 minutes');

        foreach ($texts as $text) {
            $response->assertSee($text, false);
        }
    })->with([
        'en' => ['en', ['<title>Support — Brainy Clan</title>', 'How do I sign my child in on a new device?', '“I have a sign-in code”', 'I forgot my password']],
        'fr' => ['fr', ['<title>Aide — Brainy Clan</title>', 'Comment connecter mon enfant sur un nouvel appareil ?', '« J’ai un code de connexion »', 'J’ai oublié mon mot de passe']],
    ]);

    it('serves the legal pages at /terms and /privacy and redirects the old /legal URLs', function () {
        $this->get('/terms')->assertOk()->assertSee('Terms of Service');
        $this->get('/privacy?lang=fr')->assertOk()->assertSee('Politique de confidentialité');

        $this->get('/legal/terms')->assertStatus(301)->assertRedirect(url('/terms'));
        $this->get('/legal/privacy?lang=fr')->assertStatus(301)->assertRedirect(url('/privacy?lang=fr'));
        $this->get('/legal/other')->assertNotFound();
    });

    it('serves robots.txt and a sitemap with every page in English and French', function () {
        $this->get('/robots.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *')
            ->assertSee('Sitemap: '.route('website.sitemap'));

        $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

        expect(simplexml_load_string((string) $sitemap))->not->toBeFalse()
            ->and($sitemap)->toContain('<loc>'.e(route('website.home', ['lang' => 'en'])).'</loc>')
            ->toContain('<loc>'.e(route('website.support', ['lang' => 'fr'])).'</loc>')
            ->toContain('<loc>'.e(route('legal.privacy', ['lang' => 'en'])).'</loc>')
            ->toContain('<loc>'.e(route('legal.terms', ['lang' => 'fr'])).'</loc>')
            ->toContain('<loc>'.e(route('account.delete', ['lang' => 'en'])).'</loc>')
            ->and(substr_count((string) $sitemap, '<url>'))->toBe(10);
    });

    it('ships the owl mascot as a static file', function () {
        expect(file_get_contents(public_path('images/owl.svg')))->toContain('<svg');
    });
});

describe('configuration', function () {
    it('has the production domains and emails in the config defaults', function () {
        expect(config('app.website_url'))->toBe(config('app.url'))
            ->and(config('legal.support_email'))->toBe('support@brainyclan.com')
            ->and(config('app.website_host'))->toBeNull();

        $prod = (string) file_get_contents(base_path('.env.prod.example'));
        expect($prod)->toContain('APP_URL=https://api.brainyclan.com')
            ->toContain('WEBSITE_URL=https://brainyclan.com')
            ->toContain('WEBSITE_HOST=brainyclan.com')
            ->toContain('MAIL_FROM_ADDRESS=no-reply@brainyclan.com')
            ->not->toContain('brainyclan.app');
    });
});

describe('host routing (WEBSITE_HOST set)', function () {
    beforeEach(fn () => productionHosts());

    it('serves the website on the website host', function () {
        $this->get('https://brainyclan.com/')->assertOk()->assertSee('Brainy Clan');
        $this->get('https://brainyclan.com/support?lang=fr')->assertOk()->assertSee('Aide');
        $this->get('https://brainyclan.com/account/delete')->assertOk();
    });

    it('redirects www to the apex, keeping path and query', function () {
        $this->get('https://www.brainyclan.com/support?lang=fr')->assertStatus(301)->assertRedirect('https://brainyclan.com/support?lang=fr');
        $this->get('https://www.brainyclan.com/')->assertStatus(301)->assertRedirect('https://brainyclan.com/');
        $this->get('https://www.brainyclan.com/api/v1/health')->assertStatus(301)->assertRedirect('https://brainyclan.com/api/v1/health');
    });

    it('redirects website paths requested on the API host to the website host', function () {
        $this->get('https://api.brainyclan.com/')->assertStatus(301)->assertRedirect('https://brainyclan.com/');
        $this->get('https://api.brainyclan.com/privacy?lang=fr')->assertStatus(301)->assertRedirect('https://brainyclan.com/privacy?lang=fr');
        $this->get('https://api.brainyclan.com/account/delete')->assertStatus(301)->assertRedirect('https://brainyclan.com/account/delete');
        $this->post('https://api.brainyclan.com/account/delete', ['email' => 'x@example.com'])->assertStatus(308)->assertRedirect('https://brainyclan.com/account/delete');
        $this->get('https://api.brainyclan.com/legal/terms')->assertStatus(301)->assertRedirect('https://brainyclan.com/legal/terms');
        $this->get('https://brainyclan.com/legal/terms')->assertStatus(301)->assertRedirect('https://brainyclan.com/terms');
    });

    it('leaves the API, the admin and Horizon on the API host and sends them there from the website host', function () {
        $this->getJson('https://api.brainyclan.com/api/v1/health')->assertOk()->assertJsonPath('data.status', 'ok');
        $this->get('https://api.brainyclan.com/admin/login')->assertOk();

        $this->get('https://brainyclan.com/api/v1/health')->assertStatus(301)->assertRedirect('https://api.brainyclan.com/api/v1/health');
        $this->postJson('https://brainyclan.com/api/v1/auth/login', [])->assertStatus(308)->assertRedirect('https://api.brainyclan.com/api/v1/auth/login');
        $this->get('https://brainyclan.com/admin')->assertStatus(301)->assertRedirect('https://api.brainyclan.com/admin');
        $this->get('https://brainyclan.com/horizon')->assertStatus(301)->assertRedirect('https://api.brainyclan.com/horizon');
        $this->get('https://brainyclan.com/docs/api')->assertStatus(301)->assertRedirect('https://api.brainyclan.com/docs/api');
    });

    it('lets crawlers index only the website host', function () {
        $this->get('https://brainyclan.com/robots.txt')->assertOk()->assertSee('Sitemap: https://brainyclan.com/sitemap.xml');
        $this->get('https://api.brainyclan.com/robots.txt')->assertOk()->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap');
        $this->get('https://brainyclan.com/sitemap.xml')->assertOk()->assertSee('<loc>https://brainyclan.com/support?lang=fr</loc>', false);
    });

    it('builds legal and email links on the website host, also from the API host', function () {
        $this->getJson('https://api.brainyclan.com/api/v1/legal?lang=fr')->assertOk()
            ->assertJsonPath('data.terms.url', 'https://brainyclan.com/terms?lang=fr')
            ->assertJsonPath('data.privacy.url', 'https://brainyclan.com/privacy?lang=fr');

        Mail::fake();
        User::factory()->create(['email' => 'mom@example.com']);
        app(RequestAccountDeletionByEmail::class)->handle('mom@example.com', 'fr');

        Mail::assertQueued(AccountDeletionConfirmation::class, fn (AccountDeletionConfirmation $mail) => str_starts_with($mail->confirmUrl, 'https://brainyclan.com/account/delete/confirm/')
            && str_ends_with($mail->confirmUrl, '?lang=fr'));
    });
});
