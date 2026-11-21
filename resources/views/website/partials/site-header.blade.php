{{--
    Site header of the public website (layout). No JavaScript (the home page CSP allows no scripts):
    - Desktop (≥ 960px with a mouse): logo, grouped dropdown panels (open on hover and on keyboard
      focus, close when the pointer or focus leaves), small utility links, the "Get the app" CTA.
    - Phones, tablets and touch screens: a compact bar (logo, CTA, Menu). "Menu" links to
      #site-menu and the sheet shows while it is the :target; tapping a tile jumps to its section and
      that closes the sheet by itself; "Close" links to #close-menu (no such element: no scroll).
    One menu markup for both; the menu links reach the home page sections from every page.
--}}
@php
    $onHome = $canonicalRoute === 'website.home'; // also /r/{code}, which renders the home page
    $base = $onHome ? '' : route('website.home', $lang);
    $t = fn (string $en, string $frText): string => $fr ? $frText : $en;
    $groups = [
        'explore' => [$t('Explore', 'Explorer'), [
            ['worlds', '🏰', $t('Worlds', 'Mondes'), $t('Pick a world and make it yours', 'Choisissez un monde à votre goût')],
            ['games', '🎯', $t('Games', 'Jeux'), $t('Real games, not homework', 'De vrais jeux, pas des devoirs')],
            ['brain-games', '🧩', $t('Brain games', 'Jeux de cerveau'), $t('Memory, logic and sharp eyes', 'Mémoire, logique et sens de l’observation')],
            ['categories', '📚', $t('Categories', 'Catégories'), $t('From geography to math and art', 'De la géographie aux maths et à l’art')],
        ]],
        'families' => [$t('For families', 'Familles'), [
            ['how', '🧭', $t('How it works', 'Comment ça marche'), $t('Start a family, invite the kids', 'Créez la famille, invitez les enfants')],
            ['clan', '🦊', $t('The Clan', 'Le Clan'), $t('A character for everyone', 'Un personnage pour chacun')],
            ['unlocks', '🏆', $t('Rewards', 'Récompenses'), $t('Earn points, unlock worlds', 'Des points pour débloquer des mondes')],
            ['chat', '💬', $t('Family chat', 'Clavardage'), $t('Only your family, never strangers', 'Seulement votre famille, jamais d’inconnus')],
            ['safety', '🛡️', $t('Safety', 'Sécurité'), $t('No ads for kids, parents in control', 'Aucune pub pour les enfants, les parents décident')],
        ]],
        'plans' => [$t('Plans', 'Forfaits'), [
            ['use-cases', '🤔', $t('Which plan?', 'Quel forfait ?'), $t('Find the plan for your group', 'Le forfait qui convient à votre groupe')],
            ['plans', '⭐', $t('Plans and prices', 'Forfaits et prix'), $t('From free to Classroom', 'Du forfait gratuit à Classe')],
        ]],
    ];
    $util = [
        ['website.help', $t('How to play', 'Comment jouer'), 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 15.5a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Zm1.3-5.1c-.6.4-.8.6-.8 1.1v.5h-2v-.6c0-1.3.6-2 1.6-2.6.7-.5 1-.8 1-1.4 0-.7-.5-1.2-1.3-1.2-.8 0-1.4.5-1.5 1.4l-2-.2C8.5 7.6 10 6.5 12 6.5c2 0 3.4 1.2 3.4 2.9 0 1.4-.8 2.2-2.1 3Z'],
        ['website.support', $t('Support', 'Aide'), 'M12 3C7 3 3 6.6 3 11c0 2.3 1.1 4.3 2.8 5.8L5 21l4.4-2.3c.8.2 1.7.3 2.6.3 5 0 9-3.6 9-8s-4-8-9-8Zm-4 9.2a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Zm4 0a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Zm4 0a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Z'],
    ];
@endphp
<header class="site">
    <div class="wrap sn-bar">
        <a class="logo" href="{{ route('website.home', $lang) }}">
            <img src="{{ \App\Modules\Core\Support\Brand::logoUrl() }}" alt="" width="44" height="44">
            <span>Brainy Clan</span>
        </a>
        <nav id="site-menu" class="sn" aria-label="{{ $t('Main navigation', 'Navigation principale') }}">
            <ul class="sn-main">
                @foreach ($groups as $key => [$title, $items])
                    <li class="sn-dd sn-dd-{{ $key }}">
                        <button type="button" class="sn-btn">{{ $title }}<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.4 8.6 12 14.2l5.6-5.6L19 10l-7 7-7-7z" fill="currentColor"/></svg></button>
                        <p class="sn-group" aria-hidden="true">{{ $title }}</p>
                        <div class="sn-panel">
                            <ul class="sn-items" aria-label="{{ $title }}">
                                @foreach ($items as [$anchor, $icon, $label, $line])
                                    <li><a href="{{ $base }}#{{ $anchor }}"><span class="sn-ico" aria-hidden="true">{{ $icon }}</span><span class="sn-txt"><strong>{{ $label }}</strong><small>{{ $line }}</small></span></a></li>
                                @endforeach
                            </ul>
                        </div>
                    </li>
                @endforeach
                <li class="sn-about"><a href="{{ $base }}#what"><img src="{{ \App\Modules\Core\Support\Brand::logoUrl() }}" alt="" width="40" height="40"><span class="sn-txt"><strong>{{ $t('About', 'À propos') }}</strong><small>{{ $t('What is Brainy Clan?', 'C’est quoi, Brainy Clan ?') }}</small></span></a></li>
            </ul>
            <ul class="sn-util">
                @foreach ($util as [$route, $label, $path])
                    <li><a href="{{ route($route, $lang) }}" title="{{ $label }}"@if ($canonicalRoute === $route) aria-current="page"@endif><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="{{ $path }}" fill="currentColor"/></svg><span class="sn-label">{{ $label }}</span></a></li>
                @endforeach
                <li><a class="sn-lang" href="{{ request()->url() }}?lang={{ $other }}" hreflang="{{ $other }}" lang="{{ $other }}" title="{{ $fr ? 'English' : 'Français' }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.9 6h-3a15.7 15.7 0 0 0-1.3-3.6A8 8 0 0 1 18.9 8ZM12 4c.8 1.2 1.5 2.5 1.9 4h-3.8c.4-1.5 1.1-2.8 1.9-4ZM4.3 14a8.2 8.2 0 0 1 0-4h3.4a16.5 16.5 0 0 0 0 4H4.3Zm.8 2h3c.3 1.3.8 2.5 1.3 3.6A8 8 0 0 1 5.1 16Zm3-8h-3a8 8 0 0 1 4.3-3.6C8.9 5.5 8.4 6.7 8.1 8ZM12 20c-.8-1.2-1.5-2.5-1.9-4h3.8c-.4 1.5-1.1 2.8-1.9 4Zm2.3-6H9.7a14.7 14.7 0 0 1 0-4h4.6a14.7 14.7 0 0 1 0 4Zm.3 5.6c.5-1.1 1-2.3 1.3-3.6h3a8 8 0 0 1-4.3 3.6Zm1.7-5.6a16.5 16.5 0 0 0 0-4h3.4a8.2 8.2 0 0 1 0 4h-3.4Z" fill="currentColor"/></svg><span class="sn-label">{{ $fr ? 'English' : 'Français' }}</span><span class="sn-code" aria-hidden="true">{{ strtoupper($other) }}</span></a></li>
            </ul>
        </nav>
        <a class="sn-cta" href="{{ $base }}#get">{{ $t('Get the app', 'Obtenir l’app') }}</a>
        <a class="sn-toggle sn-open" href="#site-menu" aria-controls="site-menu"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z" fill="currentColor"/></svg><span class="sn-label">{{ $t('Menu', 'Menu') }}</span></a>
        <a class="sn-toggle sn-close" href="#close-menu" aria-controls="site-menu"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.4 5 12 10.6 17.6 5 19 6.4 13.4 12l5.6 5.6-1.4 1.4-5.6-5.6L6.4 19 5 17.6 10.6 12 5 6.4z" fill="currentColor"/></svg><span class="sn-label">{{ $t('Close', 'Fermer') }}</span></a>
    </div>
</header>
