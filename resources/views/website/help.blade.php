{{--
    "How to play" help center (/help, docs/PLAN.md "How to play" help center (2026-10-24)).
    Content: resources/help/{en,fr}.php via App\Modules\Core\Support\HelpCenter ($sections), plain
    text only, escaped here (HelpCenter::inline turns **label** into <strong> after escaping).
    Every article is a collapsed <details> whose id is its anchor (the app deep links to them).
    Search: ?q= filters on the server ($matches = article ids, null without a query), so the page
    works without JavaScript; /js/help-search.js (CSP nonce) filters instantly while typing, opens
    the article of a #hash and highlights the matches. No cookies, nothing from other hosts.
--}}
@use('App\Modules\Core\Support\HelpCenter')
@php
    $fr = $locale === 'fr';
    $lang = ['lang' => $locale];
    $searching = $matches !== null;
    $matched = $searching ? array_flip($matches) : [];
    $countLabel = fn (int $count): string => $count.' '.($count > 1 ? 'articles' : 'article');
    $resultLabel = fn (int $count): string => $fr
        ? ($count === 0 ? 'Aucun résultat' : ($count > 1 ? $count.' résultats' : '1 résultat'))
        : ($count === 0 ? 'No results' : ($count > 1 ? $count.' results' : '1 result'));
    /** Quick links under the search box: [article id, label]. */
    $popular = $fr
        ? [['invite-kids', 'Inviter les enfants'], ['sign-in-code', 'Code de connexion'], ['second-chances', 'Deuxièmes chances'], ['set-goal', 'Objectifs'], ['notifications', 'Heures calmes']]
        : [['invite-kids', 'Invite the kids'], ['sign-in-code', 'Sign-in code'], ['second-chances', 'Second chances'], ['set-goal', 'Family goals'], ['notifications', 'Quiet hours']];
@endphp
@extends('website.layout')

@section('backToTop', '1')
@section('title', $fr ? 'Comment jouer — Brainy Clan' : 'How to play — Brainy Clan')
@section('description', $fr
    ? 'Le guide des parents pour Brainy Clan : créer la famille, inviter les enfants, jouer, objectifs, mondes, discussion familiale, contrôles parentaux et forfaits.'
    : 'The parents’ guide to Brainy Clan: set up the family, invite the kids, play, goals, worlds, family chat, parent controls and plans.')

@section('styles')
    .help-hero { padding: 20px 0 4px; text-align: center; }
    @media (min-width: 640px) { .help-hero { padding-top: 28px; } }
    .help-hero h1 { margin-bottom: 8px; }
    .help-hero .lead { margin: 0 auto 20px; }
    .search { position: relative; max-width: 620px; margin: 0 auto; display: flex; gap: 8px; }
    .search label { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
    .search .field { position: relative; flex: 1; }
    .search svg { position: absolute; left: 16px; top: 50%; width: 22px; height: 22px; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
    .search input { width: 100%; min-height: 54px; padding: 12px 16px 12px 48px; font: inherit; font-size: 1.05rem; color: var(--fg); background: var(--surface); border: 2px solid var(--border); border-radius: 999px; box-shadow: 0 6px 24px rgba(27, 42, 74, 0.08); -webkit-appearance: none; appearance: none; }
    .search input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 4px var(--brand-soft); }
    .search button { min-height: 54px; padding: 0 20px; font: inherit; font-weight: 700; color: #1b2a4a; background: var(--brand); border: 0; border-radius: 999px; cursor: pointer; }
    .search button:focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }
    .js .search button { display: none; }
    .status { margin: 10px 0 0; color: var(--muted); font-size: 0.95rem; }
    .status:empty { margin: 0; }
    .chips { display: none; flex-wrap: wrap; justify-content: center; gap: 8px; margin: 6px 0 0; padding: 0; list-style: none; }
    @media (min-width: 640px) { .chips { display: flex; } }
    .chips a { display: inline-block; padding: 6px 12px; border-radius: 999px; background: var(--surface); border: 1px solid var(--border); color: var(--fg); text-decoration: none; font-size: 0.9rem; }
    .chips a:hover { border-color: var(--brand); }
    .cards { display: grid; gap: 8px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 12px 0 0; padding: 0; list-style: none; }
    @media (min-width: 640px) { .cards { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 960px) { .cards { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
    .cards a { display: flex; align-items: center; gap: 10px; height: 100%; padding: 8px 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 14px; color: var(--fg); text-decoration: none; line-height: 1.25; }
    .cards a:hover { border-color: var(--brand); }
    .cards img { flex: none; width: 34px; height: 34px; border-radius: 10px; background: var(--brand-soft); }
    .cards strong { display: block; font-size: 0.88rem; }
    @media (min-width: 640px) { .cards a { padding: 10px 12px; } .cards img { width: 40px; height: 40px; border-radius: 12px; } .cards strong { font-size: 0.95rem; } }
    .cards span span { display: block; color: var(--muted); font-size: 0.8rem; }
    .help-body { display: grid; gap: 28px; padding: 28px 0 8px; }
    @media (min-width: 960px) { .help-body { grid-template-columns: 220px minmax(0, 1fr); } }
    .toc { display: none; }
    @media (min-width: 960px) {
        .toc { display: block; position: sticky; top: 16px; align-self: start; max-height: calc(100vh - 32px); overflow: auto; font-size: 0.92rem; }
        .toc h2 { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin: 0 0 8px; }
        .toc ol { list-style: none; margin: 0; padding: 0; }
        .toc a { display: block; padding: 5px 10px; border-radius: 8px; border-left: 3px solid transparent; color: var(--fg); text-decoration: none; }
        .toc a:hover { background: var(--surface); }
        .toc a[aria-current="true"] { border-left-color: var(--brand); background: var(--surface); font-weight: 700; }
    }
    .help-section { padding: 0 0 22px; scroll-margin-top: 16px; }
    .help-section > header { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
    .help-section > header img { flex: none; width: 44px; height: 44px; border-radius: 12px; background: var(--brand-soft); }
    .help-section h2 { font-size: 1.35rem; margin: 0; }
    .help-section header p { margin: 0; color: var(--muted); font-size: 0.95rem; line-height: 1.4; }
    details.article { padding: 0; margin-bottom: 8px; border-radius: 12px; scroll-margin-top: 16px; }
    details.article > summary { display: flex; align-items: center; gap: 12px; padding: 12px 16px; list-style: none; border-radius: 12px; }
    details.article > summary::-webkit-details-marker { display: none; }
    details.article > summary::after { content: ""; flex: none; width: 9px; height: 9px; margin-left: auto; border-right: 2px solid var(--muted); border-bottom: 2px solid var(--muted); transform: rotate(45deg); transition: transform 0.15s; }
    details.article[open] > summary::after { transform: rotate(-135deg); }
    details.article > summary:focus-visible { outline: 3px solid var(--brand); outline-offset: 2px; }
    details[open].article summary { margin-bottom: 0; }
    .a-head { display: block; min-width: 0; }
    .a-title { display: block; font-weight: 700; line-height: 1.3; }
    .a-sum { display: block; color: var(--muted); font-weight: 400; font-size: 0.92rem; line-height: 1.35; margin-top: 2px; }
    .a-body { display: grid; gap: 14px 24px; padding: 2px 16px 16px; }
    .a-body.has-shot { grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 720px) { .a-body.has-shot { grid-template-columns: minmax(0, 1fr) 200px; } }
    .steps { counter-reset: step; list-style: none; margin: 0; padding: 0; }
    .steps li { counter-increment: step; position: relative; padding-left: 36px; margin-bottom: 8px; line-height: 1.5; }
    .steps li::before { content: counter(step); position: absolute; left: 0; top: 1px; width: 24px; height: 24px; border-radius: 50%; background: var(--brand-soft); color: var(--fg); font-weight: 700; font-size: 0.82rem; line-height: 24px; text-align: center; }
    .tip { margin: 4px 0 0; padding: 10px 12px; border-radius: 10px; background: var(--bg); border: 1px dashed var(--border); font-size: 0.95rem; }
    .tip b { color: var(--ok); }
    .a-links { margin: 10px 0 0; font-size: 0.95rem; display: flex; flex-wrap: wrap; gap: 4px 16px; }
    .a-links .anchor { color: var(--muted); }
    .shot { margin: 0; justify-self: center; }
    .shot img { display: block; width: 200px; max-width: 100%; height: auto; border-radius: 18px; border: 1px solid var(--border); box-shadow: 0 6px 20px rgba(27, 42, 74, 0.12); }
    .shot.wide { justify-self: start; }
    .shot.wide img { width: 360px; border: 0; border-radius: 0; box-shadow: none; }
    mark { background: var(--brand-soft); color: inherit; border-radius: 3px; box-shadow: inset 0 -2px 0 var(--brand); }
    [hidden] { display: none !important; }
    .empty { text-align: center; padding: 28px 16px; background: var(--surface); border: 1px solid var(--border); border-radius: 16px; }
    .empty p { margin: 0 0 8px; }
    .help-more { padding: 8px 0 40px; text-align: center; color: var(--muted); }
    @media (prefers-color-scheme: dark) { .search button { color: #0f1626; } }
@endsection

@section('content')
    <section class="help-hero" aria-labelledby="help-title">
        <div class="wrap">
            <h1 id="help-title">{{ $fr ? 'Comment jouer' : 'How to play' }}</h1>
            <p class="lead">{{ $fr
                ? 'Des réponses courtes pour les parents : configurer la famille, jouer, les objectifs, la discussion et les réglages.'
                : 'Short answers for parents: set up the family, play, goals, chat and settings.' }}</p>
            <form class="search" role="search" method="get" action="{{ route('website.help') }}" id="help-search"
                  data-stop-words="{{ implode(' ', $stopWords) }}"
                  data-result-one="{{ $fr ? '1 résultat' : '1 result' }}"
                  data-result-many="{{ $fr ? ':count résultats' : ':count results' }}"
                  data-result-none="{{ $fr ? 'Aucun résultat' : 'No results' }}">
                <div class="field">
                    <label for="help-q">{{ $fr ? 'Chercher dans le guide' : 'Search the guide' }}</label>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                    <input id="help-q" type="search" name="q" value="{{ $query }}" maxlength="{{ HelpCenter::MAX_QUERY_LENGTH }}" autocomplete="off" spellcheck="false" aria-describedby="help-status"
                           placeholder="{{ $fr ? 'Ex. : code de connexion, heures calmes, mondes…' : 'e.g. sign-in code, quiet hours, worlds…' }}">
                </div>
                @if ($fr)
                    <input type="hidden" name="lang" value="fr">
                @endif
                <button type="submit">{{ $fr ? 'Chercher' : 'Search' }}</button>
            </form>
            <p class="status" id="help-status" role="status" aria-live="polite">@if ($searching){{ $resultLabel(count($matches)) }} {{ $fr ? 'pour « '.$query.' »' : 'for “'.$query.'”' }}@endif</p>
            @unless ($searching)
                <ul class="chips" aria-label="{{ $fr ? 'Sujets fréquents' : 'Popular topics' }}">
                    @foreach ($popular as [$id, $label])
                        <li><a href="#{{ $id }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
            @endunless
        </div>
    </section>

    <div class="wrap">
        <nav aria-label="{{ $fr ? 'Sections du guide' : 'Guide sections' }}" id="help-cards" @if ($searching) hidden @endif>
            <ul class="cards">
                @foreach ($sections as $section)
                    <li><a href="#{{ $section['id'] }}">
                        <img src="{{ $section['icon'] }}" width="40" height="40" alt="">
                        <span><strong>{{ $section['title'] }}</strong><span>{{ $countLabel(count($section['articles'])) }}</span></span>
                    </a></li>
                @endforeach
            </ul>
        </nav>

        <div class="help-body">
            <aside class="toc">
                <nav aria-labelledby="toc-title">
                    <h2 id="toc-title">{{ $fr ? 'Sommaire' : 'Contents' }}</h2>
                    <ol>
                        @foreach ($sections as $section)
                            <li><a href="#{{ $section['id'] }}" data-toc="{{ $section['id'] }}">{{ $section['title'] }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            </aside>

            <div id="help-articles">
                <div class="empty" id="help-empty" @if (! $searching || $matches !== []) hidden @endif>
                    <p><strong>{{ $fr ? 'Aucun article ne correspond à votre recherche.' : 'No article matches your search.' }}</strong></p>
                    <p>{{ $fr ? 'Essayez un autre mot, ou' : 'Try another word, or' }}
                        <a href="{{ route('website.support', $lang) }}">{{ $fr ? 'écrivez-nous depuis la page d’aide' : 'contact us from the support page' }}</a>.</p>
                    <p><a href="{{ route('website.help', $lang) }}" data-clear>{{ $fr ? 'Voir tous les articles' : 'Show all articles' }}</a></p>
                </div>

                @foreach ($sections as $section)
                    @php($visible = ! $searching || collect($section['articles'])->contains(fn (array $article): bool => isset($matched[$article['id']])))
                    <section class="help-section" id="{{ $section['id'] }}" aria-labelledby="{{ $section['id'] }}-title" @unless ($visible) hidden @endunless>
                        <header>
                            <img src="{{ $section['icon'] }}" width="44" height="44" alt="" loading="lazy" decoding="async">
                            <div>
                                <h2 id="{{ $section['id'] }}-title">{{ $section['title'] }}</h2>
                                <p>{{ $section['summary'] }}</p>
                            </div>
                        </header>

                        @foreach ($section['articles'] as $article)
                            @php($isMatch = isset($matched[$article['id']]))
                            @php($wide = $article['image'] !== null && $article['image']['width'] > 2 * $article['image']['height'])
                            <details class="article" id="{{ $article['id'] }}" data-keywords="{{ implode(' ', $article['keywords']) }}" @if ($isMatch) open @endif @if ($searching && ! $isMatch) hidden @endif>
                                <summary>
                                    <span class="a-head">
                                        <span class="a-title" data-search>{{ $article['title'] }}</span>
                                        <span class="a-sum" data-search>{{ $article['summary'] }}</span>
                                    </span>
                                </summary>
                                <div class="a-body @if ($article['image'] !== null && ! $wide) has-shot @endif">
                                    <div>
                                        <ol class="steps">
                                            @foreach ($article['steps'] as $step)
                                                <li data-search>{{ HelpCenter::inline($step) }}</li>
                                            @endforeach
                                        </ol>
                                        @if ($article['tip'] !== null)
                                            <p class="tip"><b>{{ $fr ? 'Astuce :' : 'Tip:' }}</b> <span data-search>{{ HelpCenter::inline($article['tip']) }}</span></p>
                                        @endif
                                        <p class="a-links">
                                            @foreach ($article['links'] as $link)
                                                <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                                            @endforeach
                                            <a class="anchor" href="#{{ $article['id'] }}">{{ $fr ? 'Lien vers cet article' : 'Link to this article' }}</a>
                                        </p>
                                    </div>
                                    @if ($article['image'] !== null)
                                        <figure class="shot @if ($wide) wide @endif">
                                            <img src="{{ $article['image']['src'] }}" width="{{ $article['image']['width'] }}" height="{{ $article['image']['height'] }}" loading="lazy" decoding="async" alt="{{ $article['image']['alt'] }}">
                                        </figure>
                                    @endif
                                </div>
                            </details>
                        @endforeach
                    </section>
                @endforeach
            </div>
        </div>

        <p class="help-more">{{ $fr ? 'Vous ne trouvez pas votre réponse ?' : 'Can’t find your answer?' }}
            <a href="{{ route('website.support', $lang) }}">{{ $fr ? 'Visitez la page d’aide' : 'Visit the support page' }}</a>
            {{ $fr ? 'ou écrivez à' : 'or email' }} <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
    </div>
@endsection

@section('scripts')
    @if ($nonce !== null)
        <script src="/js/help-search.js" nonce="{{ $nonce }}" defer></script>
    @endif
@endsection
