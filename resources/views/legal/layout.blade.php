{{-- Shared layout of the legal pages (/legal/terms, /legal/privacy). No scripts, inline CSS only (strict CSP). --}}
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ ($draft ?? false) ? 'noindex' : 'index' }}">
    <title>@yield('title') — Brainy Clan</title>
    <link rel="icon" href="{{ \App\Modules\Core\Support\Brand::logoUrl() }}" type="image/svg+xml">
    <style>
        :root { --bg: #ffffff; --fg: #1f2933; --muted: #52606d; --accent: #4c51bf; --banner-bg: #fff4e5; --banner-fg: #8a4b00; --border: #e4e7eb; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #111418; --fg: #e4e7eb; --muted: #9aa5b1; --accent: #a3a8ff; --banner-bg: #3d2a00; --banner-fg: #ffd48a; --border: #2f3640; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--fg); font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        main { max-width: 760px; margin: 0 auto; padding: 24px 16px 64px; }
        h1 { font-size: 1.75rem; line-height: 1.25; margin: 0 0 4px; }
        h2 { font-size: 1.25rem; margin: 32px 0 8px; padding-top: 8px; border-top: 1px solid var(--border); }
        h3 { font-size: 1.05rem; margin: 20px 0 4px; }
        p, li { overflow-wrap: anywhere; }
        ul { padding-left: 1.25rem; }
        a { color: var(--accent); }
        .meta { color: var(--muted); font-size: 0.9rem; margin: 0 0 16px; }
        .nav { display: flex; flex-wrap: wrap; gap: 8px 16px; font-size: 0.95rem; margin-bottom: 16px; }
        .banner { background: var(--banner-bg); color: var(--banner-fg); border-radius: 8px; padding: 12px 16px; margin: 0 0 20px; font-weight: 600; }
        .review { background: var(--banner-bg); color: var(--banner-fg); padding: 0 4px; border-radius: 4px; font-size: 0.9em; }
        table { width: 100%; border-collapse: collapse; font-size: 0.95rem; display: block; overflow-x: auto; }
        th, td { text-align: left; vertical-align: top; padding: 6px 8px; border-bottom: 1px solid var(--border); }
        form { margin: 16px 0; }
        label { display: block; font-weight: 600; margin-bottom: 4px; }
        input[type=email], input[type=password] { width: 100%; max-width: 420px; padding: 10px 12px; font-size: 1rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--fg); }
        button { margin-top: 12px; padding: 10px 18px; font-size: 1rem; border: 0; border-radius: 8px; background: #c81e1e; color: #fff; cursor: pointer; }
        button.primary { background: #4c51bf; }
        label.spaced { margin-top: 12px; }
        .hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        .brand { display: inline-flex; align-items: center; gap: 8px; color: var(--fg); text-decoration: none; font-weight: 800; margin-bottom: 16px; }
        .brand img { width: 36px; height: 36px; }
        .notice { border-left: 4px solid var(--accent); padding: 8px 12px; margin: 16px 0; }
        .error { color: #c81e1e; }
        html { scroll-behavior: auto; }
        @media (prefers-reduced-motion: no-preference) { html { scroll-behavior: smooth; } }
        .to-top { position: fixed; right: 16px; bottom: 16px; width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--accent); color: var(--bg); box-shadow: 0 4px 14px rgba(0, 0, 0, .18); }
        .to-top svg { width: 22px; height: 22px; }
        @media print { .to-top { display: none; } }
    </style>
</head>
<body id="top">
<main>
    <a class="brand" href="{{ \App\Modules\Core\Support\Website::route('website.home', ['lang' => $locale]) }}"><img src="{{ \App\Modules\Core\Support\Brand::logoUrl() }}" alt="" width="36" height="36"><span>Brainy Clan</span></a>
    @if ($draft ?? false)
        <div class="banner" role="note">{{ $locale === 'fr' ? 'Ébauche — en cours de révision juridique. Ce texte n’est pas encore définitif.' : 'Draft — under legal review. This text is not final yet.' }}</div>
    @endif
    @isset($otherDocumentUrl)
    <nav class="nav">
        <a href="{{ $otherDocumentUrl }}">@yield('other_document')</a>
        <a href="{{ $otherLocaleUrl }}" hreflang="{{ $locale === 'fr' ? 'en' : 'fr' }}">{{ $locale === 'fr' ? 'English' : 'Français' }}</a>
    </nav>
    @endisset
    <h1>@yield('title')</h1>
    @isset($version)
        <p class="meta">Version {{ $version }}</p>
    @endisset
    @yield('content')
</main>
@isset($otherDocumentUrl)
<a class="to-top" href="#top" aria-label="{{ $locale === 'fr' ? 'Retour en haut de la page' : 'Back to top' }}" title="{{ $locale === 'fr' ? 'Retour en haut' : 'Back to top' }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5l-7 7 1.4 1.4L11 8.8V20h2V8.8l4.6 4.6L19 12z" fill="currentColor"/></svg></a>
@endisset
</body>
</html>
