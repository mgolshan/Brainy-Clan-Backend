{{--
    Layout of the public website (landing page, help center, support). No cookies, no external
    fonts or CDNs: inline CSS only (strict CSP from SecurityHeaders); images are same-origin files
    under public/images. Pages add their own CSS with @section('styles'); only a page served with
    SecurityHeaders:script may add a same-origin script (with its CSP nonce) in @section('scripts').
    Light/dark follows the system setting. $locale is en|fr; links carry ?lang= so the choice sticks.
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
    <link rel="icon" href="/images/owl.svg" type="image/svg+xml">
    <style>
        :root {
            --bg: #fffaf3; --surface: #ffffff; --fg: #1b2a4a; --muted: #4a5873; --accent: #1d5fc0;
            --brand: #ff8a3d; --brand-soft: #ffe9c9; --border: #eadfce; --ok: #1f7a4d;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f1626; --surface: #172138; --fg: #eef2f8; --muted: #b3bdd0; --accent: #8db8ff;
                --brand: #ff9a55; --brand-soft: #3a2a1a; --border: #2a3654; --ok: #6fd3a0;
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
        header.site { padding: 14px 0; }
        header.site .wrap { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .logo { display: flex; align-items: center; gap: 10px; color: var(--fg); text-decoration: none; font-weight: 800; font-size: 1.2rem; }
        .logo img { width: 44px; height: 44px; }
        nav.top { display: flex; gap: 6px 18px; flex-wrap: wrap; font-size: 0.95rem; }
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
    </style>
    @hasSection('styles')
        <style>@yield('styles')</style>
    @endif
</head>
<body>
<a class="skip" href="#main">{{ $fr ? 'Aller au contenu' : 'Skip to content' }}</a>
<header class="site">
    <div class="wrap">
        <a class="logo" href="{{ route('website.home', $lang) }}">
            <img src="/images/owl.svg" alt="" width="44" height="44">
            <span>Brainy Clan</span>
        </a>
        <nav class="top" aria-label="{{ $fr ? 'Navigation principale' : 'Main navigation' }}">
            <a href="{{ route('website.help', $lang) }}">{{ $fr ? 'Comment jouer' : 'How to play' }}</a>
            <a href="{{ route('website.support', $lang) }}">{{ $fr ? 'Aide' : 'Support' }}</a>
            <a href="{{ request()->url() }}?lang={{ $other }}" hreflang="{{ $other }}" lang="{{ $other }}">{{ $fr ? 'English' : 'Français' }}</a>
        </nav>
    </div>
</header>
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
            <a href="{{ route('account.delete', $lang) }}">{{ $fr ? 'Supprimer un compte' : 'Delete account' }}</a>
        </nav>
        <p>{{ $fr ? 'Nous joindre :' : 'Contact:' }} <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></p>
        <p>© {{ now()->year }} Brainy Clan</p>
    </div>
</footer>
@yield('scripts')
</body>
</html>
