{{--
    Layout of the public website (landing page, help center, support). No cookies, no external
    fonts or CDNs: inline CSS only (strict CSP from SecurityHeaders); images are same-origin files
    under public/images. Pages add their own CSS with @section('styles'); only a page served with
    SecurityHeaders:script may add a same-origin script (with its CSP nonce) in @section('scripts').
    Light/dark follows the system setting. $locale is en|fr; links carry ?lang= so the choice sticks.
    Header and site menu: website/partials/site-header (same on every page, no JavaScript).
--}}
@php
    $fr = $locale === 'fr';
    $other = $fr ? 'en' : 'fr';
    $lang = ['lang' => $locale];
    $canonical = $canonicalRoute !== null ? \App\Modules\Core\Support\Website::route($canonicalRoute, $lang) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <meta name="description" content="@yield('description')">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#FF8A3D">
    @if ($canonical !== null)
        <link rel="canonical" href="{{ $canonical }}">
        <link rel="alternate" hreflang="en" href="{{ \App\Modules\Core\Support\Website::route($canonicalRoute, ['lang' => 'en']) }}">
        <link rel="alternate" hreflang="fr" href="{{ \App\Modules\Core\Support\Website::route($canonicalRoute, ['lang' => 'fr']) }}">
        <link rel="alternate" hreflang="x-default" href="{{ \App\Modules\Core\Support\Website::route($canonicalRoute) }}">
        <meta property="og:url" content="{{ $canonical }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Brainy Clan">
    <meta property="og:title" content="@yield('title')">
    <meta property="og:description" content="@yield('description')">
    <meta property="og:locale" content="{{ $fr ? 'fr_CA' : 'en_CA' }}">
    <meta property="og:locale:alternate" content="{{ $fr ? 'en_CA' : 'fr_CA' }}">
    <meta name="twitter:card" content="summary">
    <link rel="icon" href="{{ \App\Modules\Core\Support\Brand::logoUrl() }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ \App\Modules\Core\Support\Brand::pngLogoUrl() }}">
    <style>
        :root {
            --bg: #fffaf3; --surface: #ffffff; --fg: #1b2a4a; --muted: #4a5873; --accent: #1d5fc0;
            --brand: #ff8a3d; --brand-soft: #ffe9c9; --border: #eadfce; --ok: #1f7a4d; --sn-sea: #dff4f2; --sn-pop: #efe6ff;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f1626; --surface: #172138; --fg: #eef2f8; --muted: #b3bdd0; --accent: #8db8ff;
                --brand: #ff9a55; --brand-soft: #3a2a1a; --border: #2a3654; --ok: #6fd3a0; --sn-sea: #123a3d; --sn-pop: #2c2350;
            }
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; background: var(--bg); color: var(--fg); font: 17px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        a { color: var(--accent); text-underline-offset: 2px; }
        a:focus-visible { outline: 3px solid var(--brand); outline-offset: 2px; border-radius: 4px; }
        .wrap { max-width: 1040px; margin: 0 auto; padding: 0 16px; }
        .skip { position: absolute; left: -10000px; }
        .skip:focus { left: 16px; top: 8px; background: var(--surface); padding: 8px 12px; border-radius: 8px; z-index: 2; }
        /* Site header (website/partials/site-header): mobile-first compact bar + full-width menu sheet;
           dropdown panels from 960px on devices with a mouse. No JavaScript (see the partial). */
        header.site { position: sticky; top: 0; z-index: 10; background: var(--bg); border-bottom: 1px solid var(--border); box-shadow: 0 2px 10px rgba(0, 0, 0, .05); }
        .sn-bar { display: flex; align-items: center; gap: 8px; height: 60px; }
        .logo { display: flex; align-items: center; gap: 10px; margin-right: auto; color: var(--fg); text-decoration: none; font-weight: 800; font-size: 1.15rem; white-space: nowrap; }
        .logo img { width: 40px; height: 40px; }
        .sn ul { list-style: none; margin: 0; padding: 0; }
        .sn a { color: var(--fg); text-decoration: none; }
        .sn svg, .sn-toggle svg { width: 20px; height: 20px; flex: none; }
        .sn-txt { display: flex; flex-direction: column; min-width: 0; }
        .sn-txt strong { font-weight: 800; line-height: 1.25; }
        .sn-txt small { color: var(--muted); font-size: 0.82rem; line-height: 1.35; }
        .sn-ico { flex: none; width: 42px; height: 42px; display: grid; place-items: center; border-radius: 14px; font-size: 1.3rem; line-height: 1; background: var(--sn-tint, var(--brand-soft)); }
        .sn-dd-families { --sn-tint: var(--sn-sea); }
        .sn-dd-plans { --sn-tint: var(--sn-pop); }
        .sn-cta { flex: none; display: inline-flex; align-items: center; height: 44px; padding: 0 16px; border-radius: 999px; background: var(--brand); color: #1b2a4a; font-weight: 800; font-size: 0.92rem; white-space: nowrap; text-decoration: none; box-shadow: 0 3px 0 rgba(27, 42, 74, .18); }
        .sn-cta:hover { filter: brightness(1.05); }
        .sn-toggle { flex: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-width: 44px; height: 44px; padding: 0 12px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface); color: var(--fg); font-weight: 700; font-size: 0.92rem; text-decoration: none; }
        .sn-close { display: none; }
        .sn-btn { font: inherit; }
        .sn-btn:focus-visible { outline: 3px solid var(--brand); outline-offset: 2px; }
        .sn-util a[aria-current="page"] { background: var(--brand-soft); color: var(--fg); }
        .sn-code { display: none; font-weight: 800; font-size: 0.8rem; }
        html { scroll-padding-top: 76px; }
        @media (max-width: 420px) { .sn-toggle .sn-label { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; } .sn-toggle { padding: 0; } .sn-cta { padding: 0 14px; } .logo { font-size: 1.05rem; gap: 8px; } .logo img { width: 34px; height: 34px; } }
        @media (max-width: 350px) { .logo span { display: none; } }

        /* Phones, tablets, touch screens: the menu is a sheet below the bar while #site-menu is the :target. */
        @media not all and (min-width: 960px) and (hover: hover) {
            .sn, .sn-btn { display: none; }
            .sn:target { display: block; position: fixed; top: 61px; left: 0; right: 0; bottom: 0; z-index: 9; overflow-y: auto; overscroll-behavior: contain; background: var(--bg); padding: 18px 16px calc(28px + env(safe-area-inset-bottom)); }
            .sn:target ~ .sn-open { display: none; }
            .sn:target ~ .sn-close { display: inline-flex; background: var(--brand-soft); border-color: var(--brand); }
            html:has(.sn:target) { overflow: hidden; }
            .sn-main, .sn-util { max-width: 760px; margin: 0 auto !important; }
            .sn-main { display: flex; flex-direction: column; gap: 20px; }
            .sn-about { order: -1; }
            .sn-about a { display: flex; align-items: center; gap: 14px; min-height: 64px; padding: 12px 16px; border-radius: 18px; background: var(--brand-soft); border: 1px solid var(--border); }
            .sn-about img { width: 40px; height: 40px; }
            .sn-group { margin: 0 0 8px 4px; font-size: 0.78rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
            .sn-items { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .sn-items a { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; height: 100%; min-height: 96px; padding: 14px; border-radius: 18px; background: var(--surface); border: 1px solid var(--border); box-shadow: 0 2px 0 var(--border); }
            .sn-items a:active, .sn-about a:active { transform: translateY(1px); box-shadow: none; }
            .sn-items small { display: none; }
            .sn-util { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 22px !important; padding-top: 18px !important; border-top: 1px solid var(--border); }
            .sn-util li { flex: 1 1 140px; }
            .sn-util a { display: flex; align-items: center; gap: 10px; min-height: 48px; padding: 0 16px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface); font-weight: 700; }
            .sn-util svg { color: var(--muted); }
            @media (min-width: 600px) {
                .sn-items { grid-template-columns: repeat(3, minmax(0, 1fr)); }
                .sn-items small { display: block; }
            }
            @media (prefers-reduced-motion: no-preference) {
                .sn:target { animation: sn-sheet .2s ease-out; }
                @keyframes sn-sheet { from { opacity: 0; transform: translateY(-10px); } }
            }
        }

        /* Desktop with a mouse: one row; panels open on hover or keyboard focus. */
        @media (min-width: 960px) and (hover: hover) {
            header.site .wrap.sn-bar { max-width: 1240px; height: auto; min-height: 68px; flex-wrap: wrap; gap: 4px 12px; }
            .logo { margin-right: 0; }
            .sn-toggle { display: none; }
            .sn { flex: 1 1 auto; display: flex; align-items: center; gap: 8px; min-width: 0; }
            .sn-main { display: flex; align-items: center; gap: 2px; margin: 0 auto !important; }
            .sn-group, .sn-about img, .sn-about small { display: none; }
            .sn-btn, .sn-about a { display: inline-flex; align-items: center; gap: 2px; height: 40px; padding: 0 12px; border: 0; border-radius: 999px; background: none; color: var(--fg); font-size: 0.95rem; font-weight: 700; white-space: nowrap; cursor: pointer; }
            .sn-about strong { font-weight: 700; }
            .sn-btn svg { width: 18px; height: 18px; opacity: .7; }
            .sn-btn:hover, .sn-about a:hover, .sn-dd:hover .sn-btn { background: var(--brand-soft); }
            .sn-dd { position: relative; }
            .sn-panel { position: absolute; top: 100%; left: -8px; z-index: 11; padding-top: 12px; opacity: 0; pointer-events: none; transform: translateY(-6px); }
            .sn-dd:hover > .sn-panel, .sn-dd:has(:focus-visible) > .sn-panel { opacity: 1; pointer-events: auto; transform: none; }
            .sn-dd:hover .sn-btn svg, .sn-dd:has(:focus-visible) .sn-btn svg { transform: rotate(180deg); }
            @supports not selector(:has(*)) { .sn-dd:focus-within > .sn-panel { opacity: 1; pointer-events: auto; transform: none; } }
            .sn-items { display: grid; grid-template-columns: repeat(2, 280px); gap: 4px; padding: 10px; border-radius: 22px; background: var(--surface); border: 1px solid var(--border); box-shadow: 0 18px 44px rgba(27, 42, 74, .18); }
            .sn-dd-plans .sn-items { grid-template-columns: 296px; }
            .sn-items a { display: flex; align-items: center; gap: 12px; padding: 10px; border-radius: 16px; }
            .sn-items a:hover, .sn-items a:focus-visible { background: var(--bg); }
            .sn-items a:hover strong { color: var(--accent); }
            .sn-items strong { font-size: 0.95rem; }
            .sn-util { display: flex; align-items: center; gap: 2px; }
            .sn-util a { display: inline-flex; align-items: center; gap: 6px; height: 38px; padding: 0 10px; border-radius: 999px; color: var(--muted); font-size: 0.86rem; font-weight: 600; white-space: nowrap; }
            .sn-util a:hover { background: var(--brand-soft); color: var(--fg); }
            .sn-util svg { width: 18px; height: 18px; }
            .sn-cta { height: 42px; }
            html { scroll-padding-top: 88px; }
            @media (prefers-reduced-motion: no-preference) {
                .sn-panel { transition: opacity .15s ease, transform .15s ease; }
                .sn-btn svg { transition: transform .15s ease; }
            }
            /* Up to 1239px the utility links show their icon only (name kept for screen readers and as a tooltip). */
            @media (max-width: 1239px) {
                .sn-util .sn-label { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
                .sn-util a { padding: 0 9px; }
                .sn-code { display: inline; }
            }
        }
        h1 { font-size: clamp(1.9rem, 5vw, 3rem); line-height: 1.15; margin: 0 0 16px; letter-spacing: -0.01em; }
        h2 { font-size: clamp(1.4rem, 3.2vw, 2rem); line-height: 1.2; margin: 0 0 12px; }
        h3 { font-size: 1.1rem; margin: 0 0 6px; }
        p { margin: 0 0 12px; }
        section { padding: 40px 0; }
        .lead { font-size: 1.15rem; color: var(--muted); max-width: 640px; }
        .stores { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; padding: 0; list-style: none; }
        .stores li { border: 2px solid var(--fg); border-radius: 12px; padding: 8px 14px; font-weight: 600; font-size: 0.95rem; }
        .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); padding: 0; margin: 0; list-style: none; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 18px; }
        .card p { color: var(--muted); margin: 0; }
        .alt { background: var(--brand-soft); }
        .checks { list-style: none; padding: 0; margin: 0; }
        .checks li { padding-left: 1.8em; position: relative; margin-bottom: 10px; }
        .checks li::before { content: "✓"; position: absolute; left: 0.2em; color: var(--ok); font-weight: 800; }
        .plans { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .plan h3 { font-size: 1.3rem; }
        .plan.featured { border: 2px solid var(--brand); }
        .note { color: var(--muted); font-size: 0.95rem; }
        details { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 12px 16px; margin-bottom: 10px; }
        summary { cursor: pointer; font-weight: 700; }
        details[open] summary { margin-bottom: 8px; }
        ol, ul { padding-left: 1.3rem; }
        footer.site { border-top: 1px solid var(--border); padding: 28px 0 40px; font-size: 0.95rem; color: var(--muted); }
        footer.site nav { display: flex; flex-wrap: wrap; gap: 6px 18px; margin-bottom: 12px; }
        @media (prefers-reduced-motion: no-preference) { html { scroll-behavior: smooth; } }
        .to-top { position: fixed; right: 16px; bottom: 16px; z-index: 5; width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--brand); color: #1b2a4a; box-shadow: 0 4px 14px rgba(0, 0, 0, .18); text-decoration: none; }
        .to-top:hover { filter: brightness(1.05); }
        .to-top svg { width: 22px; height: 22px; }
        @media print { .to-top { display: none; } }
    </style>
    @hasSection('styles')
        <style>@yield('styles')</style>
    @endif
</head>
<body>
<span id="top"></span>
<a class="skip" href="#main">{{ $fr ? 'Aller au contenu' : 'Skip to content' }}</a>
@include('website.partials.site-header')
<main id="main">
    @yield('content')
</main>
<footer class="site">
    <div class="wrap">
        <nav aria-label="{{ $fr ? 'Liens du pied de page' : 'Footer links' }}">
            <a href="{{ route('website.help', $lang) }}">{{ $fr ? 'Comment jouer' : 'How to play' }}</a>
            <a href="{{ route('legal.privacy', $lang) }}">{{ $fr ? 'Confidentialité' : 'Privacy' }}</a>
            <a href="{{ route('legal.terms', $lang) }}">{{ $fr ? 'Conditions d’utilisation' : 'Terms' }}</a>
            <a href="{{ route('website.support', $lang) }}">{{ $fr ? 'Aide' : 'Support' }}</a>
            <a href="{{ route('website.partners', $lang) }}">{{ $fr ? 'Partenaires' : 'Partners' }}</a>
            <a href="{{ route('account.delete', $lang) }}">{{ $fr ? 'Supprimer un compte' : 'Delete account' }}</a>
        </nav>
        <p><a href="{{ \App\Modules\Core\Support\Website::contactUrl($locale) }}">{{ $fr ? 'Nous joindre' : 'Contact us' }}</a></p>
        <p>© {{ now()->year }} Brainy Clan</p>
    </div>
</footer>
@hasSection('backToTop')
<a class="to-top" href="#top" aria-label="{{ $fr ? 'Retour en haut de la page' : 'Back to top' }}" title="{{ $fr ? 'Retour en haut' : 'Back to top' }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5l-7 7 1.4 1.4L11 8.8V20h2V8.8l4.6 4.6L19 12z" fill="currentColor"/></svg></a>
@endif
@yield('scripts')
</body>
</html>
