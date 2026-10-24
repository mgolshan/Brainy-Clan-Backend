<?php

use App\Modules\Core\Support\HelpCenter;
use App\Modules\Family\Actions\RequestAccountDeletionByEmail;
use App\Modules\Family\Mail\AccountDeletionConfirmation;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\Models\Setting;
use App\Modules\Policy\Models\ThemeWorld;
use Illuminate\Support\Facades\Mail;

/**
 * The public website (routes/web.php, docs/DEPLOY.md "Website and hosts"): landing page,
 * help center, support, legal pages, robots.txt, sitemap.xml and production host routing.
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
            ->assertSee('All 17 categories')
            ->assertSee('17 categories to start with')
            ->assertSee('1 active family goal')
            ->assertSee('Unlimited family goals')
            ->assertSee('3 free second chances per member per day')
            ->assertSee('No ads')
            ->assertSee('Family chat: messages kept for 7 days')
            ->assertSee('Family chat: messages kept for 1 year');
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
            ->toContain('<loc>'.e(route('website.help', ['lang' => 'fr'])).'</loc>')
            ->and(substr_count((string) $sitemap, '<url>'))->toBe(12);
    });

    it('ships the owl mascot as a static file', function () {
        expect(file_get_contents(public_path('images/owl.svg')))->toContain('<svg');
    });
});

/**
 * Every <img> of a page: src, alt (null when missing) and the whole tag.
 *
 * @return list<array{src: string, alt: string|null, tag: string}>
 */
function landingImages(string $html): array
{
    preg_match_all('/<img\b[^>]*>/i', $html, $tags);

    return array_map(function (string $tag): array {
        preg_match('/\ssrc="([^"]*)"/', $tag, $src);
        preg_match('/\salt="([^"]*)"/', $tag, $alt);

        return ['src' => $src[1] ?? '', 'alt' => $alt[1] ?? null, 'tag' => $tag];
    }, $tags[0]);
}

/**
 * Landing page v2 (docs/PLAN.md "Landing page v2 (2026-10-23)"): visual sections, same-origin
 * images that exist, alt texts in both languages, nothing loaded from other hosts.
 */
describe('landing page v2', function () {
    it('renders every section in both languages', function (string $lang, array $texts) {
        $response = $this->get("/?lang={$lang}")->assertOk();

        foreach (['worlds', 'how', 'games', 'brain-games', 'categories', 'clan', 'unlocks', 'what', 'chat', 'safety', 'plans', 'get'] as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }

        foreach ($texts as $text) {
            $response->assertSee($text, false);
        }
    })->with([
        'en' => ['en', ['Pick your world', 'How it works', 'Invite the kids with a code', 'Real games, not homework', 'Meet the Clan', 'Play, earn points, unlock worlds', 'Candy Land', 'Neon Arcade', 'Grandpa', 'Brain games for memory and logic', 'Memory Match', 'Pattern &amp; Sequence', 'Nonograms', 'Spot the Difference', 'Chat together, safely', 'Only your family']],
        'fr' => ['fr', ['Choisissez votre monde', 'Comment ça marche', 'Invitez les enfants avec un code', 'De vrais jeux, pas des devoirs', 'Voici le Clan', 'Jouez, gagnez des points, débloquez des mondes', 'Pays des bonbons', 'Arcade néon', 'Grand-papa', 'Des jeux de cerveau pour la mémoire et la logique', 'Jeu de mémoire', 'Suites à répéter', 'Logimages', 'Jeu des différences', 'Jasez en famille, en toute sécurité', 'Seulement votre famille']],
    ]);

    it('only references images that exist in public/, with sizes and alt texts', function (string $lang) {
        $images = landingImages((string) $this->get("/?lang={$lang}")->assertOk()->getContent());

        expect(count($images))->toBeGreaterThan(40);

        foreach ($images as $image) {
            expect($image['src'])->toStartWith('/images/')
                ->and(is_file(public_path(ltrim($image['src'], '/'))))->toBeTrue("missing file {$image['src']}")
                ->and($image['alt'])->not->toBeNull("no alt on {$image['src']}")
                ->and($image['tag'])->toMatch('/\swidth="\d+"/')->toMatch('/\sheight="\d+"/');
        }

        // Every app screenshot is described (never decorative); the hero's middle phone is the
        // eager LCP image and everything below the hero is lazy.
        $screens = array_values(array_filter($images, fn (array $image): bool => str_contains($image['src'], '/screens/')));
        expect(count($screens))->toBeGreaterThanOrEqual(15);

        foreach ($screens as $screen) {
            expect(mb_strlen((string) $screen['alt']))->toBeGreaterThan(15);
        }

        expect($screens[1]['tag'])->toContain('fetchpriority="high"')->not->toContain('loading="lazy"')
            ->and(collect($screens)->slice(3)->every(fn (array $image): bool => str_contains($image['tag'], 'loading="lazy"')))->toBeTrue();
    })->with(['en', 'fr']);

    it('lists every seeded top-level category with its art and both names', function () {
        /** @var array<string, array{name: array{en: string, fr: string}}> $seeded */
        $seeded = require database_path('seeders/data/categories.php');
        $en = (string) $this->get('/?lang=en')->assertOk()->getContent();
        $fr = (string) $this->get('/?lang=fr')->assertOk()->getContent();

        expect($seeded)->toHaveCount(17)
            ->and($en)->toContain(count($seeded).' categories to start with')
            ->and($fr)->toContain(count($seeded).' catégories pour commencer')
            ->and(substr_count($en, '<li class="cat">'))->toBe(count($seeded));

        foreach ($seeded as $slug => $category) {
            expect($en)->toContain('src="/images/site/art/'.$slug.'.svg"')
                ->toContain(e($category['name']['en']))
                ->and($fr)->toContain(e($category['name']['fr']))
                ->and(file_get_contents(public_path("images/site/art/{$slug}.svg")))
                ->toStartWith('<svg')->not->toMatch('/\sid=|url\(#/');
        }
    });

    it('loads nothing from other hosts and keeps the strict CSP without scripts or cookies', function () {
        $response = $this->get('/')->assertOk();

        expect((string) $response->getContent())->not->toContain('<script')
            ->not->toMatch('/\s(src|srcset)="(https?:)?\/\//i')
            ->not->toMatch('/url\(\s*["\']?(https?:)?\/\//i')
            ->not->toContain('@import')
            ->not->toMatch('/\son[a-z]+="/i')
            ->and($response->headers->get('Content-Security-Policy'))
            ->toBe("default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'")
            ->and($response->headers->getCookies())->toBe([]);
    });

    it('keeps the page and the site images within the weight budget', function () {
        $html = (string) $this->get('/')->assertOk()->getContent();
        $files = array_unique(array_column(landingImages($html), 'src'));
        $bytes = strlen($html) + array_sum(array_map(fn (string $src): int => (int) filesize(public_path(ltrim($src, '/'))), $files));

        expect($bytes)->toBeLessThan((int) (2.5 * 1024 * 1024));

        $siteImages = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(public_path('images/site'), FilesystemIterator::SKIP_DOTS)) as $file) {
            $siteImages += $file->getSize();
        }

        expect($siteImages)->toBeLessThan(2 * 1024 * 1024);
    });

    it('shows the four brain games and the family chat with their screenshots', function (string $lang, array $alts) {
        $html = (string) $this->get("/?lang={$lang}")->assertOk()->getContent();
        $images = collect(landingImages($html))->keyBy('src');

        foreach (['brain-memory', 'brain-pattern', 'brain-nonogram', 'brain-spot', 'chat-family'] as $i => $name) {
            $image = $images->get("/images/site/screens/{$name}.webp");

            expect($image)->not->toBeNull("{$name} missing")
                ->and(is_file(public_path("images/site/screens/{$name}.webp")))->toBeTrue()
                ->and($image['alt'])->toContain($alts[$i])
                ->and($image['tag'])->toContain('loading="lazy"');
        }

        expect(substr_count($html, '<div class="shot">'))->toBe(4);
    })->with([
        'en' => ['en', ['Memory Match', 'Pattern &amp; Sequence', 'nonogram', 'Spot the Difference', 'family chat']],
        'fr' => ['fr', ['jeu de mémoire', 'jeu de suites', 'logimage', 'jeu des différences', 'clavardage familial']],
    ]);

    it('tells the chat retention of each plan from the tier settings, in the chat section and the plans', function () {
        Setting::query()->where('key', 'chat.retention_days')->where('scope_id', 'free')->update(['value' => json_encode(30)]);
        Setting::query()->where('key', 'chat.retention_days')->where('scope_id', 'family')->update(['value' => json_encode(730)]);

        $this->get('/?lang=en')->assertOk()
            ->assertSee('Kept for 30 days on the free plan and 2 years on the Family plan, then deleted.')
            ->assertSee('Family chat: messages kept for 30 days')
            ->assertSee('Family chat: messages kept for 2 years')
            ->assertSee('a parent can turn it off for a child or remove any message.')
            ->assertDontSee('No chat and no contact with strangers');
        $this->get('/?lang=fr')->assertOk()
            ->assertSee('Gardés 30 jours avec le forfait gratuit et 2 ans avec le forfait Famille, puis supprimés.')
            ->assertSee('Clavardage familial : messages gardés 2 ans');
    });

    it('shows the world unlocks from the live catalog', function () {
        ThemeWorld::query()->where('key', 'jungle')->update(['required' => 75]);
        ThemeWorld::query()->where('key', 'neon')->update(['active' => false]);

        $this->get('/?lang=en')->assertOk()
            ->assertSee('75 points to unlock')
            ->assertSee('Jungle Adventure (75 points)')
            ->assertSee('3-day streak to unlock')
            ->assertSee('Exclusive worlds: Dino Valley, Midnight')
            ->assertDontSee('id="world-neon"', false)
            ->assertSee('id="world-sea"', false);

        $this->get('/?lang=fr')->assertOk()
            ->assertSee('Série de 3 jours pour débloquer', false)
            ->assertSee('Forfait Famille');
    });
});

/**
 * The "How to play" help center (docs/PLAN.md "How to play" help center (2026-10-24)): structured
 * en/fr content, anchors for the app's deep links, ?q= search without JavaScript, live numbers,
 * same-origin images within budget, and the search script allowed only by a per-request nonce.
 */
describe('help center', function () {
    it('renders every section and article in both languages, with anchors and the search box', function (string $lang, array $texts) {
        $html = (string) $this->get("/help?lang={$lang}")->assertOk()
            ->assertHeader('Content-Language', $lang)
            ->assertSee('<html lang="'.$lang.'">', false)
            ->assertSee('role="search"', false)
            ->assertSee('<label for="help-q">', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('<link rel="canonical"', false)
            ->getContent();

        foreach ($texts as $text) {
            expect($html)->toContain($text);
        }

        foreach (HelpCenter::content($lang) as $section) {
            expect($html)->toContain('<section class="help-section" id="'.$section['id'].'"')
                ->toContain('href="#'.$section['id'].'"');

            foreach ($section['articles'] as $article) {
                expect($html)->toContain('<details class="article" id="'.$article['id'].'"')
                    ->toContain('href="#'.$article['id'].'"');
            }
        }

        // Collapsed by default: nothing is open and nothing hidden without a search.
        expect($html)->not->toMatch('/<details class="article"[^>]*\sopen[\s>]/')
            ->not->toMatch('/<details class="article"[^>]*\shidden[\s>]/');
    })->with([
        'en' => ['en', ['<title>How to play — Brainy Clan</title>', 'Search the guide', 'Invite the kids with a code', 'Sign a child in on a new device', '<strong>I have a sign-in code</strong>', 'Out of lives? Second chances', 'Report a message', 'Notifications and quiet hours', 'Too many attempts']],
        'fr' => ['fr', ['<title>Comment jouer — Brainy Clan</title>', 'Chercher dans le guide', 'Inviter les enfants avec un code', 'Connecter un enfant sur un nouvel appareil', '<strong>J&#039;ai un code de connexion</strong>', 'Plus de vies ? Les deuxièmes chances', 'Signaler un message', 'Notifications et heures calmes', 'Trop de tentatives']],
    ]);

    it('has the same sections, articles and images in English and French, each with 2 to 6 steps and keywords', function () {
        $shape = fn (string $lang): array => array_map(fn (array $section): array => [
            $section['id'],
            array_map(fn (array $article): array => [$article['id'], $article['image'] ?? null], $section['articles']),
        ], HelpCenter::content($lang));

        expect($shape('fr'))->toBe($shape('en'));

        $ids = [];
        foreach (['en', 'fr'] as $lang) {
            foreach (app(HelpCenter::class)->sections($lang) as $section) {
                expect($section['articles'])->not->toBeEmpty()
                    ->and(is_file(public_path(ltrim($section['icon'], '/'))))->toBeTrue("missing icon {$section['icon']}");

                foreach ($section['articles'] as $article) {
                    expect(count($article['steps']))->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(6)
                        ->and($article['keywords'])->not->toBeEmpty()
                        ->and($article['id'])->toMatch('/^[a-z][a-z-]+$/');

                    foreach ([$article['title'], $article['summary'], $article['tip'] ?? '', ...$article['steps']] as $text) {
                        expect($text)->not->toMatch('/[<>]|\{[a-z_]+\}/');
                    }

                    if ($lang === 'en') {
                        $ids[] = $article['id'];
                    }
                }
            }
        }

        // Anchors are unique, also against the section ids.
        $sectionIds = array_column(HelpCenter::content('en'), 'id');
        expect(array_unique([...$ids, ...$sectionIds]))->toHaveCount(count($ids) + count($sectionIds))
            // The app deep links to these (src/config/links.ts helpAnchors).
            ->and($ids)->toContain('invite-kids', 'sign-in-code', 'family-chat');
    });

    it('fills in the numbers from the live settings, config and world catalog', function () {
        Setting::query()->where('key', 'invites.ttl_days')->whereNull('scope_id')->update(['value' => json_encode(5)]);
        Setting::query()->where('key', 'chat.retention_days')->where('scope_id', 'free')->update(['value' => json_encode(30)]);
        ThemeWorld::query()->where('key', 'jungle')->update(['required' => 75]);
        ThemeWorld::query()->where('unlock_type', 'streak')->update(['active' => false]);

        $html = (string) $this->get('/help?lang=en')->assertOk()->getContent();

        expect($html)->toContain('An invite code works once and stays valid for 5 days.')
            ->toContain('The code works once, for 15 minutes.')
            ->toContain('A game ends with a win when you reach 10 points.')
            ->toContain('You have 3 lives per game.')
            ->toContain('Messages are kept for 30 days on the free plan and 1 year on the Family plan, then deleted.')
            ->toContain('Free plan: up to 2 members, 3 categories, 1 active goal, chat kept 30 days.')
            ->toContain('Family plan: up to 7 members, all categories, unlimited goals and chat kept 1 year.')
            ->toContain('Free from the start: Candy Land, Glass, Modern and Classic.')
            ->toContain('Jungle Adventure (75 points)')
            ->toContain('Included with the Family plan: Dino Valley, Neon Arcade and Midnight.')
            ->toContain('After 5 wrong passwords in a row, sign-in pauses for 5 minutes, then 1 hour, then 24 hours.')
            ->toContain('Open the link within 60 minutes')
            // No streak world is active, so that step is left out instead of reading "…: .".
            ->not->toContain('Earned with a streak');

        $this->get('/help?lang=fr')->assertOk()
            ->assertSee('Un code d’invitation sert une seule fois et reste valide 5 jours.', false)
            ->assertSee('Les messages sont gardés 30 jours avec le forfait gratuit et 1 an avec le forfait Famille, puis supprimés.', false)
            ->assertSee('Aventure dans la jungle (75 points)', false)
            ->assertSee('la connexion se met en pause 5 minutes, puis 1 heure, puis 24 heures.', false);
    });

    it('searches with ?q= without JavaScript: matching articles open, the others hidden', function () {
        $html = (string) $this->get('/help?q='.urlencode('How do I invite my kids?'))->assertOk()
            ->assertSee('value="How do I invite my kids?"', false)
            ->getContent();

        expect($html)->toMatch('/<details class="article" id="invite-kids"[^>]*\sopen[\s>]/')
            ->toMatch('/<details class="article" id="report-message"[^>]*\shidden[\s>]/')
            ->toMatch('/<section class="help-section" id="worlds"[^>]*\shidden[\s>]/')
            ->toMatch('/<div class="empty" id="help-empty"\s+hidden/')
            ->toContain('1 result for “How do I invite my kids?”');

        // Accents and case don't matter; French stop words are ignored.
        $fr = (string) $this->get('/help?lang=fr&q='.urlencode('Comment avoir une DEUXIEME chance'))->assertOk()->getContent();
        expect($fr)->toMatch('/<details class="article" id="second-chances"[^>]*\sopen[\s>]/');

        expect(app(HelpCenter::class)->search(app(HelpCenter::class)->sections('en'), 'quiet hours', 'en'))
            ->toContain('notifications')->not->toContain('create-family');
    });

    it('shows a no-results message with a link to support, and escapes the query', function () {
        $html = (string) $this->get('/help?q='.urlencode('<i onmouseover="x()">zzqx</i>'))->assertOk()->getContent();

        expect($html)->not->toContain('<i onmouseover')
            ->toContain('value="&lt;i onmouseover=&quot;x()&quot;&gt;zzqx&lt;/i&gt;"')
            ->toContain('No results for “&lt;i onmouseover=&quot;x()&quot;&gt;zzqx&lt;/i&gt;”')
            ->toMatch('/<div class="empty" id="help-empty"\s+>/')
            ->toContain('href="'.route('website.support', ['lang' => 'en']).'"')
            ->and(substr_count($html, '<script'))->toBe(1);

        // A query of stop words only, an array or an empty q shows everything.
        foreach (['/help?q=how+do+i', '/help?q[]=x', '/help?q='] as $url) {
            expect((string) $this->get($url)->assertOk()->getContent())
                ->not->toMatch('/<details class="article"[^>]*\shidden[\s>]/');
        }
    });

    it('only uses same-origin images that exist, with alt texts, sizes and lazy loading, within budget', function (string $lang) {
        $html = (string) $this->get("/help?lang={$lang}")->assertOk()->getContent();
        $images = landingImages($html);
        $shots = array_values(array_filter($images, fn (array $image): bool => str_ends_with($image['src'], '.webp')));

        expect(count($shots))->toBeGreaterThanOrEqual(12);

        foreach ($images as $image) {
            $path = public_path(ltrim($image['src'], '/'));

            expect($image['src'])->toStartWith('/images/')
                ->and(is_file($path))->toBeTrue("missing file {$image['src']}")
                ->and($image['alt'])->not->toBeNull("no alt on {$image['src']}")
                ->and($image['tag'])->toMatch('/\swidth="\d+"/')->toMatch('/\sheight="\d+"/');
        }

        foreach ($shots as $shot) {
            $path = public_path(ltrim($shot['src'], '/'));
            [$width, $height] = (array) getimagesize($path);

            expect(mb_strlen((string) $shot['alt']))->toBeGreaterThan(20)
                ->and($shot['tag'])->toContain('loading="lazy"')
                ->toContain('width="'.$width.'"')->toContain('height="'.$height.'"')
                ->and(filesize($path))->toBeLessThanOrEqual(60 * 1024);
        }

        $bytes = array_sum(array_map(fn (string $src): int => (int) filesize(public_path(ltrim($src, '/'))), array_unique(array_column($images, 'src'))));
        expect($bytes)->toBeLessThanOrEqual((int) (1.5 * 1024 * 1024));
    })->with(['en', 'fr']);

    it('allows only its own search script, by a per-request nonce, and loads nothing from other hosts', function () {
        $first = $this->get('/help')->assertOk();
        $second = $this->get('/help')->assertOk();
        $html = (string) $first->getContent();
        $csp = (string) $first->headers->get('Content-Security-Policy');

        preg_match("/script-src 'nonce-([A-Za-z0-9_-]{20,})'/", $csp, $nonce);

        expect($nonce)->toHaveCount(2)
            ->and($csp)->toBe("default-src 'none'; script-src 'nonce-{$nonce[1]}'; style-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'")
            ->and((string) $second->headers->get('Content-Security-Policy'))->not->toContain($nonce[1])
            ->and($html)->toContain('<script src="/js/help-search.js" nonce="'.$nonce[1].'" defer></script>')
            ->and(substr_count($html, '<script'))->toBe(1)
            ->and($html)->not->toMatch('/<script(?![^>]*\ssrc=)[^>]*>/')
            ->not->toMatch('/\s(src|srcset|href|action)="(https?:)?\/\/(?!'.preg_quote(parse_url((string) config('app.url'), PHP_URL_HOST) ?? 'localhost', '/').')/i')
            ->not->toMatch('/url\(\s*["\']?(https?:)?\/\//i')
            ->not->toMatch('/\son[a-z]+="/i')
            ->and($first->headers->getCookies())->toBe([])
            ->and((string) $first->headers->get('Cache-Control'))->toContain('private')
            ->and(is_file(public_path('js/help-search.js')))->toBeTrue()
            ->and((string) file_get_contents(public_path('js/help-search.js')))->not->toMatch('/https?:\/\/|innerHTML|eval\(|document\.write/');

        // The other website pages keep their script-free CSP.
        expect((string) $this->get('/support')->headers->get('Content-Security-Policy'))->not->toContain('script-src');
    });

    it('is linked from the header, the footer and the support page, and listed in the sitemap', function () {
        $help = route('website.help', ['lang' => 'fr']);

        expect(substr_count((string) $this->get('/?lang=fr')->assertOk()->getContent(), 'href="'.$help.'"'))->toBe(2)
            ->and(substr_count((string) $this->get('/support?lang=fr')->assertOk()->getContent(), 'href="'.$help.'"'))->toBe(3)
            ->and((string) $this->get('/sitemap.xml')->getContent())->toContain('<loc>'.e(route('website.help', ['lang' => 'en'])).'</loc>');
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

it('serves app-ads.txt only when the AdMob publisher id is configured', function () {
    config(['services.admob.publisher_id' => null]);
    $this->get('/app-ads.txt')->assertNotFound();

    config(['services.admob.publisher_id' => 'not-a-publisher']);
    $this->get('/app-ads.txt')->assertNotFound();

    config(['services.admob.publisher_id' => 'pub-1234567890123456']);
    $response = $this->get('/app-ads.txt')->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain')
        ->and($response->getContent())->toBe("google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0\n");
});
