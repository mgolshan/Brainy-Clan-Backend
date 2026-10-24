{{--
    Landing page (/), v2 (docs/PLAN.md "Landing page v2 (2026-10-23)"). Every claim here must stay
    true to docs/PLAN.md and docs/API.md; the plan limits come from the same settings as the app's
    policy ($plans, WebsiteController::plan) and the world unlocks from the live world catalog
    ($worlds, ThemeWorldCatalog). Images: public/images/site/ (screens = WebP captures of the demo
    family, art + clan = SVGs exported from the app). No scripts: the worlds slider is CSS
    scroll-snap with anchor dots (arrows via ::scroll-button where the browser supports it).
--}}
@php
    $fr = $locale === 'fr';
    $lang = ['lang' => $locale];
    $categories = $fr ? [
        ['world-geography', 'Géographie du monde', 'Capitales, drapeaux, continents et monuments.'],
        ['science-nature', 'Sciences et nature', 'Les animaux, l’espace et le corps humain.'],
        ['wisdom-cards', 'Cartes de sagesse', 'Habitudes, gentillesse, l’argent et la pensée stoïcienne.'],
        ['math-sprint', 'Sprint de maths', 'Tables de multiplication, calcul mental et fractions.'],
        ['sudoku', 'Sudoku', 'Grilles 4x4 pour les enfants, puis 6x6 et 9x9.'],
        ['word-puzzles', 'Jeux de mots', 'Anagrammes, mots cachés et lettre manquante.'],
        ['history', 'Histoire', 'Des Amériques à l’Europe, à l’Asie et à l’Antiquité.'],
        ['vocabulary-spelling', 'Vocabulaire et orthographe', 'Par niveau scolaire, synonymes, expressions et orthographe.'],
        ['logic-riddles', 'Logique et énigmes', 'Suites logiques, devinettes et casse-têtes.'],
        ['language-swap', 'Échange de langues', 'Mots de tous les jours, nombres, couleurs et phrases utiles dans une autre langue.'],
        ['inventions-technology', 'Inventions et technologie', 'Inventions, comment ça marche, ordinateurs et programmation.'],
        ['our-planet', 'Notre planète', 'Météo, océans, volcans, écosystèmes et recyclage.'],
        ['art-music', 'Art et musique', 'Les artistes et leurs œuvres, les instruments et les bases de la musique.'],
        ['food-world', 'Cuisine du monde', 'Fruits et légumes, plats du monde et d’où vient notre nourriture.'],
        ['sports-games', 'Sports et jeux', 'Règles et équipement, Jeux olympiques, jeux de société et de cartes.'],
        ['brain-games', 'Jeux de cerveau', 'Jeu de mémoire, suites à répéter, logimages et jeu des différences.'],
        ['life-skills', 'Vie pratique et sécurité', 'Sécurité routière, premiers soins, sécurité en ligne et bonnes manières.'],
    ] : [
        ['world-geography', 'World Geography', 'Capitals, flags, continents and landmarks.'],
        ['science-nature', 'Science & Nature', 'Animals, space and the human body.'],
        ['wisdom-cards', 'Wisdom Cards', 'Habits, kindness, money basics and Stoic thinking.'],
        ['math-sprint', 'Math Sprint', 'Times tables, mental math and fractions.'],
        ['sudoku', 'Sudoku', '4x4 grids for kids, then 6x6 and 9x9.'],
        ['word-puzzles', 'Word Puzzles', 'Anagrams, word search and missing letters.'],
        ['history', 'History', 'The Americas, Europe, Asia and the ancient world.'],
        ['vocabulary-spelling', 'Vocabulary & Spelling', 'By grade level, synonyms, idioms and spelling.'],
        ['logic-riddles', 'Logic & Riddles', 'Patterns, riddles and brain teasers.'],
        ['language-swap', 'Language Swap', 'Everyday words, numbers, colours and useful phrases in another language.'],
        ['inventions-technology', 'Inventions & Technology', 'Inventions, how things work, computers and coding.'],
        ['our-planet', 'Our Planet', 'Weather, oceans, volcanoes, ecosystems and recycling.'],
        ['art-music', 'Art & Music', 'Artists and their art, instruments and music basics.'],
        ['food-world', 'Food Around the World', 'Fruits and vegetables, world dishes and where food comes from.'],
        ['sports-games', 'Sports & Games', 'Rules and equipment, the Olympic Games, board and card games.'],
        ['brain-games', 'Brain Games', 'Memory match, repeat-the-pattern, picture logic puzzles and spot the difference.'],
        ['life-skills', 'Life Skills & Safety', 'Road safety, first aid, online safety and everyday manners.'],
    ];
    $free = $plans['free'];
    $family = $plans['family'];
    $allCategories = count($categories);

    /** World key => [image, name en, name fr, look en, look fr] (names as in the app). */
    $worldInfo = [
        'playful' => ['world-candy', 'Candy Land', 'Pays des bonbons', 'Sweet, bright and bouncy', 'Sucré, coloré et rebondissant'],
        'jungle' => ['world-jungle', 'Jungle Adventure', 'Aventure dans la jungle', 'Leaves, vines and animal friends', 'Feuilles, lianes et amis animaux'],
        'space' => ['world-space', 'Space Explorers', 'Explorateurs de l’espace', 'Stars, planets and rockets', 'Étoiles, planètes et fusées'],
        'sea' => ['world-sea', 'Under the Sea', 'Sous la mer', 'Bubbles, coral and friendly fish', 'Bulles, coraux et poissons rigolos'],
        'neon' => ['world-neon', 'Neon Arcade', 'Arcade néon', 'Glowing arcade lights', 'Lumières d’arcade'],
        'sleek' => ['world-glass', 'Glass', 'Verre', 'Frosted glass, violet and cyan', 'Verre dépoli, violet et cyan'],
        'dino' => ['world-dino', 'Dino Valley', 'Vallée des dinos', 'Volcanoes, ferns and a friendly dino', 'Volcans, fougères et un gentil dino'],
        'sticker' => ['world-sticker', 'Sticker Street', 'Rue des stickers', 'Bold stickers, tape and doodles', 'Stickers, ruban adhésif et gribouillis'],
        'lofi' => ['world-lofi', 'Lo-fi Dusk', 'Crépuscule lo-fi', 'Pastel sunset and soft rain', 'Coucher de soleil pastel et pluie douce'],
        'nature' => ['world-nature', 'Calm Nature', 'Nature paisible', 'Soft green hills, calm and easy to read', 'Collines vertes, calme et facile à lire'],
        'midnight' => ['world-midnight', 'Midnight', 'Minuit', 'Deep blue night, moon and gold', 'Nuit bleu profond, lune et or'],
    ];
    $worldNames = [
        'playful' => $fr ? 'Pays des bonbons' : 'Candy Land', 'sleek' => $fr ? 'Verre' : 'Glass',
        'modern' => $fr ? 'Moderne' : 'Modern', 'classic' => $fr ? 'Classique' : 'Classic',
    ] + array_map(fn (array $info): string => $fr ? $info[2] : $info[1], $worldInfo);

    $unlockLabel = function (array $world) use ($fr): string {
        return match ($world['unlock_type']) {
            'points' => $fr ? $world['required'].' points' : $world['required'].' points',
            'streak' => $fr ? 'Série de '.$world['required'].' jours' : $world['required'].'-day streak',
            'family_plan' => $fr ? 'Forfait Famille' : 'Family plan',
            default => $fr ? 'Gratuit' : 'Free',
        };
    };

    // Gallery: worlds that have a capture and are active in the catalog, in the catalog's order.
    $gallery = [];
    foreach ($worlds as $key => $world) {
        if (isset($worldInfo[$key])) {
            $gallery[$key] = $world;
        }
    }
    $byUnlock = ['free' => [], 'points' => [], 'streak' => [], 'family_plan' => []];
    foreach ($worlds as $key => $world) {
        $byUnlock[$world['unlock_type']][] = ($worldNames[$key] ?? ucfirst($key))
            .(in_array($world['unlock_type'], ['points', 'streak'], true) ? ' ('.$unlockLabel($world).')' : '');
    }

    $screen = fn (string $name): string => '/images/site/screens/'.$name.'.webp';
    /** Chat retention (tier setting `chat.retention_days`) as "7 days" / "1 year". */
    $keptFor = function (int $days) use ($fr): string {
        if ($days % 365 === 0) {
            $years = intdiv($days, 365);

            return $fr ? $years.' an'.($years > 1 ? 's' : '') : $years.' year'.($years > 1 ? 's' : '');
        }

        return $fr ? $days.' jour'.($days > 1 ? 's' : '') : $days.' day'.($days > 1 ? 's' : '');
    };
    $chatFree = $keptFor($plans['free']['chat_days']);
    $chatFamily = $keptFor($plans['family']['chat_days']);
    /** Brain games: [image, width, height, name, text, alt] (crops of the app's game screens). */
    $brainGames = $fr ? [
        ['brain-memory', 333, 403, 'Jeu de mémoire', 'Retournez deux cartes à la fois et retrouvez chaque paire d’amis du Clan, de 3 à 10 paires.', 'Le jeu de mémoire dans le monde Aventure dans la jungle : une paire de renards trouvée, 3 essais sur 12.'],
        ['brain-pattern', 336, 463, 'Suites à répéter', 'Regardez les amis du Clan s’allumer, avec un son, puis répétez la suite dans le même ordre.', 'Le jeu de suites dans le monde Arcade néon : quatre amis du Clan à toucher dans le même ordre.'],
        ['brain-nonogram', 333, 514, 'Logimages', 'Noircissez les cases grâce aux indices chiffrés pour révéler une image cachée, de 5×5 à 10×10.', 'Un logimage 10×10 résolu dans le monde Aventure dans la jungle : l’image révélée est un voilier.'],
        ['brain-spot', 344, 673, 'Jeu des différences', 'Deux images, quelques petits changements : trouvez-les tous avant d’épuiser vos essais.', 'Le jeu des différences dans le monde Arcade néon : deux images, avec 2 différences sur 5 entourées.'],
    ] : [
        ['brain-memory', 333, 403, 'Memory Match', 'Turn over two cards at a time and find every pair of Clan friends, from 3 to 10 pairs.', 'Memory Match in the Jungle Adventure world: a pair of foxes found, 3 of 12 attempts used.'],
        ['brain-pattern', 336, 463, 'Pattern & Sequence', 'Watch the Clan friends light up, with sound, then repeat the pattern in the same order.', 'Pattern & Sequence in the Neon Arcade world: four Clan friends to tap in the same order.'],
        ['brain-nonogram', 333, 514, 'Nonograms', 'Fill in the grid from the number clues to reveal a hidden picture, from 5×5 up to 10×10.', 'A solved 10×10 nonogram in the Jungle Adventure world: the hidden picture is a sailing boat.'],
        ['brain-spot', 344, 673, 'Spot the Difference', 'Two pictures, a few small changes: find them all before you run out of misses.', 'Spot the Difference in the Neon Arcade world: two pictures with 2 of 5 differences circled.'],
    ];
    $clanFamily = $fr ? [
        ['fox', 'Enfant', 'Renard, version enfant'], ['cat-teen', 'Ado', 'Chat, version ado avec casque'],
        ['owl-mom', 'Maman', 'Chouette, version maman'], ['bear-dad', 'Papa', 'Ours, version papa avec nœud papillon'],
        ['rabbit-grandma', 'Grand-maman', 'Lapin, version grand-maman avec lunettes'], ['lion-grandpa', 'Grand-papa', 'Lion, version grand-papa avec lunettes'],
    ] : [
        ['fox', 'Kid', 'Fox, kid version'], ['cat-teen', 'Teen', 'Cat, teen version with headphones'],
        ['owl-mom', 'Mom', 'Owl, mom version'], ['bear-dad', 'Dad', 'Bear, dad version with a bow tie'],
        ['rabbit-grandma', 'Grandma', 'Rabbit, grandma version with glasses'], ['lion-grandpa', 'Grandpa', 'Lion, grandpa version with glasses'],
    ];
    $clanParade = ['panda', 'unicorn', 'dragon', 'penguin', 'monkey', 'octopus', 'koala', 'tiger-teen', 'elephant-grandma', 'dog-dad', 'giraffe', 'frog', 'hedgehog', 'otter', 'turtle', 'raccoon'];
@endphp
@extends('website.layout')

@section('backToTop', '1')
@section('title', $fr ? 'Brainy Clan — Des défis d’apprentissage en famille' : 'Brainy Clan — Family learning challenges')
@section('description', $fr
    ? 'Brainy Clan est une application de quiz et d’apprentissage pour toute la famille : de courts défis adaptés à chaque âge, des objectifs à atteindre ensemble et un espace sûr pour les enfants.'
    : 'Brainy Clan is a quiz and learning app for the whole family: short challenges adapted to every age, goals you reach together, and a safe space for kids.')

@section('styles')
    :root { --ink: #1b2a4a; --pop: #7a3ff2; --pop-soft: #efe6ff; --sea: #0f7c8c; --sea-soft: #dff4f2; --sun: #ffc93d; --pink: #e0457b; --card-shadow: 0 10px 30px rgba(27, 42, 74, .12); }
    @media (prefers-color-scheme: dark) {
        :root { --ink: #eef2f8; --pop: #b99bff; --pop-soft: #251c40; --sea: #6fd6e0; --sea-soft: #10303a; --card-shadow: 0 10px 30px rgba(0, 0, 0, .4); }
    }
    .wrap { max-width: 1160px; }
    h1, h2 { font-weight: 900; letter-spacing: -0.02em; }
    .eyebrow { display: inline-block; font-weight: 800; font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; color: var(--pop); margin-bottom: 8px; }
    .center { text-align: center; }
    .center .lead { margin-left: auto; margin-right: auto; }
    img { max-width: 100%; }
    .sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

    /* Phone frames (CSS only; screens are 480×1039 captures of the app) */
    .phone { display: block; background: #12131a; padding: 16px 7px; border-radius: 34px; box-shadow: 0 18px 40px rgba(27, 42, 74, .28), inset 0 0 0 2px #2b2e3a; position: relative; }
    .phone::before { content: ""; position: absolute; top: 6px; left: 50%; width: 22%; height: 4px; transform: translateX(-50%); background: #3a3e4d; border-radius: 4px; }
    .phone img { display: block; width: 100%; height: auto; border-radius: 18px; aspect-ratio: 480 / 1039; background: var(--brand-soft); }

    /* Hero */
    .hero { position: relative; overflow: hidden; padding: 20px 0 56px; background:
        radial-gradient(600px 380px at 85% 10%, rgba(122, 63, 242, .16), transparent 70%),
        radial-gradient(520px 360px at 5% 90%, rgba(255, 138, 61, .22), transparent 70%); }
    .hero .wrap { display: grid; gap: 28px; align-items: center; }
    @media (min-width: 900px) { .hero .wrap { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
    .hero h1 { font-size: clamp(2.2rem, 5.6vw, 3.5rem); line-height: 1.05; }
    .hero h1 em { font-style: normal; background: linear-gradient(90deg, #d9480f, #c2255c 45%, #7a3ff2); -webkit-background-clip: text; background-clip: text; color: transparent; }
    @media (prefers-color-scheme: dark) { .hero h1 em { background-image: linear-gradient(90deg, #ffa25e, #ff7aa8 45%, #b99bff); } }
    .pill { display: inline-flex; align-items: center; gap: 8px; background: var(--surface); border: 1px solid var(--border); border-radius: 999px; padding: 6px 14px 6px 6px; font-weight: 700; font-size: .9rem; margin-bottom: 18px; box-shadow: var(--card-shadow); }
    .pill img { width: 30px; height: 30px; border-radius: 50%; background: var(--brand-soft); }
    .stores { display: flex; flex-wrap: wrap; gap: 12px; margin: 22px 0 0; padding: 0; list-style: none; }
    .stores li { display: flex; align-items: center; gap: 10px; background: var(--fg); color: var(--bg); border-radius: 14px; padding: 9px 16px; font-weight: 700; font-size: .95rem; line-height: 1.2; border: 0; }
    .stores li small { display: block; font-weight: 600; opacity: .8; font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; }
    .stores svg { width: 26px; height: 26px; flex: none; fill: currentColor; }
    .chips { display: flex; flex-wrap: wrap; gap: 8px 16px; margin: 18px 0 0; padding: 0; list-style: none; color: var(--muted); font-size: .95rem; font-weight: 600; }
    .chips li::before { content: "✓ "; color: var(--ok); font-weight: 900; }
    .stack { position: relative; height: clamp(400px, 112vw, 560px); max-width: 520px; width: 100%; justify-self: center; }
    .stack .phone { position: absolute; top: 50%; left: 50%; width: clamp(150px, 41vw, 232px); }
    .stack .p1 { transform: translate(-128%, -46%) rotate(-9deg); }
    .stack .p2 { transform: translate(-50%, -52%); z-index: 2; width: clamp(170px, 46vw, 258px); }
    .stack .p3 { transform: translate(28%, -46%) rotate(9deg); }
    .floaty { position: absolute; width: clamp(52px, 13vw, 84px); height: auto; z-index: 3; filter: drop-shadow(0 6px 10px rgba(27, 42, 74, .2)); }
    .f1 { top: 2%; left: 4%; } .f2 { top: 8%; right: 2%; } .f3 { bottom: 4%; left: 10%; } .f4 { bottom: 0; right: 8%; }
    @media (prefers-reduced-motion: no-preference) {
        .floaty { animation: bob 5s ease-in-out infinite; }
        .f2 { animation-delay: -1.2s; } .f3 { animation-delay: -2.4s; } .f4 { animation-delay: -3.6s; }
        @keyframes bob { 50% { transform: translateY(-10px) rotate(-4deg); } }
    }

    /* Generic sections */
    section { padding: 64px 0; }
    .band { background: var(--brand-soft); }
    .band-pop { background: var(--pop-soft); }
    .band-sea { background: var(--sea-soft); }
    .section-head { margin-bottom: 28px; }
    .section-head h2 { font-size: clamp(1.7rem, 4.4vw, 2.6rem); }

    /* Worlds slider */
    .slider { position: relative; }
    .track { display: grid; grid-auto-flow: column; grid-auto-columns: clamp(170px, 46vw, 220px); gap: 18px; overflow-x: auto; scroll-snap-type: x mandatory; scroll-padding-inline: 16px; padding: 12px 16px 22px; margin: 0 -16px; list-style: none; scrollbar-width: thin; anchor-name: --worlds; overscroll-behavior-x: contain; }
    .track:focus-visible { outline: 3px solid var(--brand); outline-offset: -3px; border-radius: 16px; }
    .track li { scroll-snap-align: center; scroll-margin-top: 30vh; text-align: center; }
    .track .phone { margin-bottom: 12px; transition: transform .25s; }
    @media (prefers-reduced-motion: no-preference) { .track li:hover .phone { transform: translateY(-6px) rotate(-1.5deg); } }
    .track h3 { margin: 0; font-size: 1.05rem; }
    .track p { margin: 2px 0 6px; color: var(--muted); font-size: .88rem; line-height: 1.35; }
    .tag { display: inline-block; font-size: .78rem; font-weight: 800; border-radius: 999px; padding: 3px 10px; background: var(--surface); border: 1px solid var(--border); }
    .tag.points { color: #8a4b00; background: #fff1d6; border-color: #f4d29a; }
    .tag.streak { color: #9b1c4a; background: #ffe3ec; border-color: #f5b4c8; }
    .tag.family_plan { color: #4a23b0; background: #ece4ff; border-color: #cdb9ff; }
    .tag.free { color: #13623c; background: #dcf5e7; border-color: #a9e2c3; }
    .dots { display: flex; flex-wrap: wrap; justify-content: center; gap: 4px; margin-top: 4px; }
    .dots a { width: 26px; height: 26px; display: grid; place-items: center; border-radius: 50%; }
    .dots a::before { content: ""; width: 10px; height: 10px; border-radius: 50%; background: var(--muted); opacity: .45; transition: transform .2s, opacity .2s; }
    .dots a:hover::before, .dots a:focus-visible::before { opacity: 1; transform: scale(1.3); background: var(--brand); }
    @supports selector(::scroll-button(*)) {
        .track::scroll-button(*) { position: absolute; position-anchor: --worlds; top: calc(anchor(center) - 70px); width: 46px; height: 46px; border-radius: 50%; border: 0; background: var(--surface); color: var(--fg); box-shadow: var(--card-shadow); font: 900 1.3rem/1 system-ui, sans-serif; cursor: pointer; z-index: 4; }
        .track::scroll-button(*):focus-visible { outline: 3px solid var(--brand); }
        .track::scroll-button(*):disabled { opacity: .35; cursor: default; }
        @media (max-width: 599px) { .track::scroll-button(*) { display: none; } }
        .track::scroll-button(left) { content: "←" / "{{ $fr ? 'Mondes précédents' : 'Previous worlds' }}"; left: calc(anchor(left) + 22px); }
        .track::scroll-button(right) { content: "→" / "{{ $fr ? 'Mondes suivants' : 'Next worlds' }}"; right: calc(anchor(right) + 22px); }
    }

    /* Steps */
    .steps { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); counter-reset: step; list-style: none; padding: 0; margin: 0; }
    .step { background: var(--surface); border: 1px solid var(--border); border-radius: 24px; padding: 20px; box-shadow: var(--card-shadow); display: flex; flex-direction: column; }
    .step h3 { font-size: 1.15rem; display: flex; gap: 10px; align-items: center; }
    .step h3::before { counter-increment: step; content: counter(step); flex: none; width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; background: var(--brand); color: #1b2a4a; font-weight: 900; }
    .step p { color: var(--muted); margin: 0 0 14px; }
    .visual { margin-top: auto; height: 210px; border-radius: 18px; overflow: hidden; position: relative; background: linear-gradient(160deg, var(--brand-soft), var(--pop-soft)); display: grid; place-items: center; }
    .visual .phone { width: 150px; position: absolute; top: 18px; padding: 10px 5px; border-radius: 24px; }
    .visual .phone::before { display: none; }
    .visual .phone img { border-radius: 14px; }
    .fam { display: flex; align-items: flex-end; gap: 0; }
    .fam img { width: 74px; height: 74px; margin: 0 -8px; filter: drop-shadow(0 4px 6px rgba(27, 42, 74, .18)); }
    .fam img:nth-child(2), .fam img:nth-child(3) { width: 60px; height: 60px; }
    .fam-name { position: absolute; top: 16px; left: 50%; transform: translateX(-50%); background: var(--surface); border-radius: 999px; padding: 4px 14px; font-weight: 800; font-size: .9rem; white-space: nowrap; box-shadow: var(--card-shadow); }
    .code { display: grid; gap: 10px; justify-items: center; }
    .code-box { display: flex; gap: 5px; }
    .code-box span { width: 26px; height: 36px; display: grid; place-items: center; background: var(--surface); border: 2px solid var(--pop); border-radius: 8px; font: 800 1.1rem/1 ui-monospace, Menlo, Consolas, monospace; color: var(--fg); }
    .code-box span.gap { width: 6px; border: 0; background: none; }
    .code small { font-weight: 700; color: var(--muted); }

    /* Game strip */
    .games { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(170px, 1fr); gap: 20px; overflow-x: auto; scroll-snap-type: x mandatory; scroll-padding-inline: 16px; list-style: none; padding: 8px 16px 18px; margin: 0 -16px; }
    .games:focus-visible { outline: 3px solid var(--brand); outline-offset: -3px; border-radius: 16px; }
    .games li { scroll-snap-align: start; }
    .games figure { margin: 0; }
    .games figcaption { margin-top: 12px; font-weight: 700; line-height: 1.35; }
    .games figcaption span { display: block; font-weight: 500; color: var(--muted); font-size: .92rem; }
    @media (min-width: 900px) { .games li:nth-child(even) { margin-top: 36px; } }

    /* Brain games (crops of the game cards, shown whole on a tinted tile) */
    .band-brain { background: linear-gradient(180deg, var(--pop-soft), var(--sea-soft)); }
    .brain { display: grid; gap: 16px; grid-template-columns: repeat(2, minmax(0, 1fr)); list-style: none; padding: 0; margin: 0; }
    @media (min-width: 900px) { .brain { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 22px; } }
    .brain li { background: var(--surface); border: 1px solid var(--border); border-radius: 24px; padding: 10px 10px 16px; box-shadow: var(--card-shadow); display: flex; flex-direction: column; transition: transform .2s; }
    @media (prefers-reduced-motion: no-preference) { .brain li:hover { transform: translateY(-4px) rotate(-1deg); } }
    .brain .shot { --pad: 12px; position: relative; aspect-ratio: 4 / 5; border-radius: 18px; overflow: hidden; }
    .brain li:nth-child(odd) .shot { background: linear-gradient(160deg, #e3f4dc, #c9e9bd); }
    .brain li:nth-child(even) .shot { background: linear-gradient(160deg, #ffe1f0, #e4dcff); }
    .brain .shot img { position: absolute; inset: var(--pad); margin: auto; max-width: calc(100% - 2 * var(--pad)); max-height: calc(100% - 2 * var(--pad)); width: auto; height: auto; border-radius: 12px; box-shadow: 0 8px 20px rgba(27, 42, 74, .22); }
    .brain h3 { margin: 12px 4px 4px; font-size: 1.05rem; line-height: 1.25; }
    .brain p { margin: 0 4px; color: var(--muted); font-size: .9rem; line-height: 1.4; }
    @media (max-width: 599px) { .brain { gap: 12px; } .brain .shot { --pad: 8px; } .brain h3 { font-size: .95rem; } .brain p { font-size: .82rem; } }

    /* Family chat */
    .chat { display: grid; gap: 30px; align-items: center; }
    @media (min-width: 860px) { .chat { grid-template-columns: 1.15fr .85fr; } }
    .chat-phone { position: relative; width: min(260px, 68vw); margin: 0 auto; }
    .chat-phone .phone { transform: rotate(-3deg); }
    .bubble { position: absolute; z-index: 2; background: var(--surface); color: var(--fg); border-radius: 18px 18px 18px 4px; padding: 8px 12px; font-weight: 800; font-size: 1.15rem; box-shadow: var(--card-shadow); }
    .bubble.b1 { top: 30%; left: -20%; }
    .bubble.b2 { bottom: 32%; right: -18%; border-radius: 18px 18px 4px 18px; }
    @media (prefers-reduced-motion: no-preference) {
        .bubble { animation: bob 5s ease-in-out infinite; }
        .bubble.b2 { animation-delay: -2.5s; }
    }
    .perks { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); list-style: none; padding: 0; margin: 22px 0 0; }
    .perks li { display: flex; gap: 12px; align-items: flex-start; background: var(--surface); border: 1px solid var(--border); border-radius: 18px; padding: 14px 16px; }
    .perks .ico { flex: none; width: 40px; height: 40px; display: grid; place-items: center; border-radius: 50%; background: var(--sea-soft); font-size: 1.25rem; line-height: 1; }
    .perks strong { display: block; margin-bottom: 2px; }
    .perks span:last-child { color: var(--muted); font-size: .93rem; }

    /* Categories */
    /* Compact tiles in centred rows (17 categories: 2 per row on phones, 3 on tablets, 6 on desktop -> 6/6/5). */
    .cats { --cols: 2; --gap: 12px; display: flex; flex-wrap: wrap; justify-content: center; gap: var(--gap); list-style: none; padding: 0; margin: 0; }
    @media (min-width: 600px) { .cats { --cols: 3; --gap: 16px; } }
    @media (min-width: 1000px) { .cats { --cols: 6; } }
    .cat { flex: 0 0 calc((100% - (var(--cols) - 1) * var(--gap)) / var(--cols)); min-width: 0; background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 10px 10px 14px; box-shadow: var(--card-shadow); transition: transform .2s; }
    @media (prefers-reduced-motion: no-preference) { .cat:hover { transform: translateY(-4px) rotate(-1deg); } }
    .cat img { display: block; width: 100%; height: auto; aspect-ratio: 1; border-radius: 14px; background: var(--tile, #ffe9d6); }
    .cat h3 { margin: 10px 2px 4px; font-size: 1rem; line-height: 1.25; }
    .cat p { margin: 0 2px; color: var(--muted); font-size: .85rem; line-height: 1.4; }
    @media (max-width: 599px) { .cat img { width: 66%; margin: 0 auto; } .cat h3 { font-size: .95rem; text-align: center; } .cat p { font-size: .8rem; text-align: center; } }
    .cat:nth-child(8n+1) { --tile: #ffe6d4; } .cat:nth-child(8n+2) { --tile: #e3ecff; } .cat:nth-child(8n+3) { --tile: #fff2c9; } .cat:nth-child(8n+4) { --tile: #dcf3ea; }
    .cat:nth-child(8n+5) { --tile: #ffe3e6; } .cat:nth-child(8n+6) { --tile: #ece3ff; } .cat:nth-child(8n+7) { --tile: #e0f1ff; } .cat:nth-child(8n+8) { --tile: #f6e7d8; }

    /* Clan */
    .variants { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; list-style: none; padding: 0; margin: 0 auto 26px; max-width: 760px; }
    @media (min-width: 700px) { .variants { grid-template-columns: repeat(6, 1fr); } }
    .variants li { text-align: center; font-weight: 800; }
    .variants img { width: 100%; max-width: 120px; height: auto; aspect-ratio: 1; display: block; margin: 0 auto 6px; background: var(--surface); border-radius: 50%; border: 4px solid var(--surface); box-shadow: var(--card-shadow); }
    .parade { overflow: hidden; mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent); }
    .parade div { display: flex; gap: 18px; width: max-content; }
    .parade img { width: 72px; height: 72px; }
    @media (prefers-reduced-motion: no-preference) {
        .parade div { animation: parade 40s linear infinite; }
        .parade:hover div { animation-play-state: paused; }
        @keyframes parade { to { transform: translateX(-50%); } }
    }
    @media (prefers-reduced-motion: reduce) { .parade div { flex-wrap: wrap; width: auto; justify-content: center; } .parade .dup { display: none; } .parade { mask-image: none; } }

    /* Unlocks */
    .unlock { display: grid; gap: 28px; align-items: center; }
    @media (min-width: 860px) { .unlock { grid-template-columns: 1.2fr .8fr; } }
    .unlock .phone { width: min(250px, 66vw); margin: 0 auto; transform: rotate(3deg); }
    .ways { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); list-style: none; padding: 0; margin: 0; }
    .ways li { background: var(--surface); border: 1px solid var(--border); border-radius: 18px; padding: 14px 16px; }
    .ways strong { display: block; margin-bottom: 4px; }
    .ways .list { display: block; color: var(--muted); font-size: .92rem; }
    .ways .tag { display: table; margin-bottom: 8px; }

    /* Why + safety */
    .why { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); padding: 0; margin: 0; list-style: none; }
    .why li { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 18px; }
    .why img { width: 56px; height: 56px; display: block; margin-bottom: 8px; }
    .why p { color: var(--muted); margin: 0; }
    .safe { display: grid; gap: 26px; align-items: center; }
    @media (min-width: 860px) { .safe { grid-template-columns: 1.4fr .6fr; } }
    .safe .owl { width: min(240px, 60vw); justify-self: center; }
    .checks li { padding-left: 2.1em; }
    .checks li::before { width: 1.4em; height: 1.4em; border-radius: 50%; background: #dcf5e7; color: #13623c; display: grid; place-items: center; font-size: .8em; top: .15em; left: 0; }

    /* Plans + final CTA */
    .card { border-radius: 22px; box-shadow: var(--card-shadow); }
    .plan.featured { position: relative; }
    .plan.featured::after { content: "★"; position: absolute; top: -14px; right: 18px; width: 36px; height: 36px; display: grid; place-items: center; border-radius: 50%; background: var(--brand); color: #1b2a4a; font-weight: 900; }
    .cta { background: linear-gradient(135deg, #b8400e, #b5245a 55%, #5f3dc4); color: #fff; border-radius: 32px; padding: 40px 24px; text-align: center; position: relative; overflow: hidden; }
    .cta h2 { color: #fff; font-size: clamp(1.8rem, 5vw, 2.8rem); }
    .cta p { color: #fff; max-width: 560px; margin: 0 auto 12px; font-size: 1.1rem; }
    .cta a { color: #fff; font-weight: 800; }
    .cta a:focus-visible { outline-color: #fff; }
    .cta .stores { justify-content: center; }
    .cta .stores li { background: #fff; color: #1b2a4a; }
    .cta .row { display: flex; justify-content: center; gap: 4px; margin-bottom: 14px; }
    .cta .row img { width: 64px; height: 64px; }
@endsection

@section('content')
    <div class="hero">
        <div class="wrap">
            <div>
                @if (($referral ?? null) !== null)
                    {{-- /r/{code} (B10): affiliate code banner. --}}
                    <p class="pill" role="note"><strong>Code {{ $referral['code'] }}</strong>&nbsp;—
                        {{ $referral['has_offer_code']
                            ? ($fr ? 'entrez ce code dans l’application (« Avez-vous un code ? ») ou utilisez l’offre de l’App Store / Google Play.' : 'enter it in the app (“Have a code?”) or redeem the App Store / Google Play offer.')
                            : ($fr ? 'entrez ce code dans l’application (« Avez-vous un code ? »).' : 'enter it in the app (“Have a code?”).') }}
                        @if ($referral['subscribe_url'] !== null)
                            <a href="{{ $referral['subscribe_url'] }}">{{ $fr ? 'Ou abonnez-vous sur le Web (carte ou PayPal)' : 'Or subscribe on the web (card or PayPal)' }}</a>
                        @endif
                    </p>
                @endif
                <p class="pill"><img src="/images/site/clan/owl-mom.svg" alt="" width="30" height="30">{{ $fr ? 'Pour les enfants, les ados, les parents et les grands-parents' : 'For kids, teens, parents and grandparents' }}</p>
                <h1>{!! $fr ? 'Apprendre, jouer et grandir <em>en famille.</em>' : 'Learn, play and grow <em>together as a family.</em>' !!}</h1>
                <p class="lead">{{ $fr
                    ? 'De courts défis de quiz et de casse-têtes pour toute la famille, adaptés à l’âge de chacun, avec des objectifs que vous atteignez ensemble.'
                    : 'Short quiz and puzzle challenges for the whole family, adapted to each person’s age, with goals you reach together.' }}</p>
                <ul class="stores" aria-label="{{ $fr ? 'Disponibilité' : 'Availability' }}">
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm0 3v13h10V5H7Zm5 14.2a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z"/></svg><span><small>iPhone · iPad</small>{{ $fr ? 'Bientôt sur l’App Store' : 'Coming soon to the App Store' }}</span></li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3.5v17a1 1 0 0 0 1.5.86l14.6-8.5a1 1 0 0 0 0-1.72L5.5 2.64A1 1 0 0 0 4 3.5Z"/></svg><span><small>Android</small>{{ $fr ? 'Bientôt sur Google Play' : 'Coming soon to Google Play' }}</span></li>
                </ul>
                <ul class="chips">
                    <li>{{ $fr ? 'Aucune pub pour les enfants' : 'No ads for kids' }}</li>
                    <li>{{ $fr ? 'Pas de clavardage avec des inconnus' : 'No chat with strangers' }}</li>
                    <li>{{ $fr ? 'En français et en anglais' : 'In English and French' }}</li>
                </ul>
            </div>
            <div class="stack">
                <span class="phone p1"><img src="{{ $screen('world-sticker') }}" width="480" height="1039" alt="{{ $fr ? 'L’écran « On joue » dans le monde Rue des stickers : des catégories illustrées avec les personnages du Clan.' : 'The “Let’s play” screen in the Sticker Street world: illustrated categories with Clan characters.' }}"></span>
                <span class="phone p2"><img src="{{ $screen('game-question') }}" width="480" height="1039" fetchpriority="high" alt="{{ $fr ? 'Une question de géographie dans le monde Pays des bonbons : bonne réponse, et le renard du Clan s’exclame « Correct! +1 point ».' : 'A geography question in the Candy Land world: the right answer is picked and the Clan fox cheers “Correct! +1 point”.' }}"></span>
                <span class="phone p3"><img src="{{ $screen('world-space') }}" width="480" height="1039" alt="{{ $fr ? 'L’accueil dans le monde Explorateurs de l’espace : les points de la semaine, la série et l’objectif familial Pizza Friday à 72 sur 100 points.' : 'The home screen in the Space Explorers world: this week’s points, the streak and the family goal Pizza Friday at 72 of 100 points.' }}"></span>
                <img class="floaty f1" src="/images/site/clan/fox.svg" alt="" width="84" height="84">
                <img class="floaty f2" src="/images/site/clan/owl-mom.svg" alt="" width="84" height="84">
                <img class="floaty f3" src="/images/site/clan/octopus.svg" alt="" width="84" height="84">
                <img class="floaty f4" src="/images/site/clan/lion-grandpa.svg" alt="" width="84" height="84">
            </div>
        </div>
    </div>

    <section id="worlds" class="band-pop" aria-labelledby="worlds-title">
        <div class="wrap">
            <div class="section-head center">
                <span class="eyebrow">{{ $fr ? 'Des mondes à explorer' : 'Worlds to explore' }}</span>
                <h2 id="worlds-title">{{ $fr ? 'Choisissez votre monde' : 'Pick your world' }}</h2>
                <p class="lead">{{ $fr
                    ? 'Chaque membre choisit le look de son appli : décor, cartes, barres de progression et célébrations changent avec le monde. Le monde ne change que l’apparence, jamais le contenu ni les règles.'
                    : 'Each member picks how their app looks: the scene, cards, progress bars and celebrations all change with the world. A world only changes the look — never the content or the rules.' }}</p>
            </div>
            <div class="slider">
                <ul class="track" tabindex="0" aria-label="{{ $fr ? 'Captures d’écran des mondes (faites défiler)' : 'World screenshots (scroll sideways)' }}">
                    @foreach ($gallery as $key => $world)
                        @php [$image, $nameEn, $nameFr, $lookEn, $lookFr] = $worldInfo[$key]; @endphp
                        <li id="world-{{ $key }}">
                            <span class="phone"><img src="{{ $screen($image) }}" width="480" height="1039" loading="lazy" decoding="async" alt="{{ $fr ? 'Brainy Clan dans le monde '.$nameFr : 'Brainy Clan in the '.$nameEn.' world' }}"></span>
                            <h3>{{ $fr ? $nameFr : $nameEn }}</h3>
                            <p>{{ $fr ? $lookFr : $lookEn }}</p>
                            <span class="tag {{ $world['unlock_type'] }}">{{ $unlockLabel($world) }}@if (in_array($world['unlock_type'], ['points', 'streak'], true)){{ $fr ? ' pour débloquer' : ' to unlock' }}@endif</span>
                        </li>
                    @endforeach
                </ul>
                <nav class="dots" aria-label="{{ $fr ? 'Aller à un monde' : 'Go to a world' }}">
                    @foreach ($gallery as $key => $world)
                        <a href="#world-{{ $key }}"><span class="sr">{{ $fr ? $worldInfo[$key][2] : $worldInfo[$key][1] }}</span></a>
                    @endforeach
                </nav>
            </div>
        </div>
    </section>

    <section id="how" aria-labelledby="how-title">
        <div class="wrap">
            <div class="section-head center">
                <span class="eyebrow">{{ $fr ? 'Simple comme bonjour' : 'Easy to start' }}</span>
                <h2 id="how-title">{{ $fr ? 'Comment ça marche' : 'How it works' }}</h2>
            </div>
            <ol class="steps">
                <li class="step">
                    <h3>{{ $fr ? 'Créez votre famille' : 'Create your family' }}</h3>
                    <p>{{ $fr ? 'Un parent s’inscrit, crée l’espace de la famille et choisit un personnage.' : 'A parent signs up, creates your family space and picks a character.' }}</p>
                    <div class="visual" aria-hidden="true">
                        <span class="fam-name">{{ $fr ? 'Les Martin' : 'The Millers' }}</span>
                        <div class="fam"><img src="/images/site/clan/owl-mom.svg" alt="" width="74" height="74"><img src="/images/site/clan/fox.svg" alt="" width="60" height="60"><img src="/images/site/clan/cat-teen.svg" alt="" width="60" height="60"><img src="/images/site/clan/bear-dad.svg" alt="" width="74" height="74"></div>
                    </div>
                </li>
                <li class="step">
                    <h3>{{ $fr ? 'Invitez les enfants avec un code' : 'Invite the kids with a code' }}</h3>
                    <p>{{ $fr ? 'Chaque enfant se joint à la famille avec un code d’invitation. Pas de courriel ni de mot de passe pour les enfants.' : 'Each child joins your family with an invite code. No email and no password for kids.' }}</p>
                    <div class="visual" aria-hidden="true">
                        <div class="code">
                            <img src="/images/site/clan/panda.svg" alt="" width="70" height="70">
                            <div class="code-box"><span>C</span><span>U</span><span>B</span><span>S</span><span class="gap"></span><span>2</span><span>2</span><span>2</span><span>2</span></div>
                            <small>{{ $fr ? 'Code d’invitation' : 'Invite code' }}</small>
                        </div>
                    </div>
                </li>
                <li class="step">
                    <h3>{{ $fr ? 'Jouez à de courts défis' : 'Play short daily challenges' }}</h3>
                    <p>{{ $fr ? 'Quiz, sudoku, mots cachés et calcul mental, adaptés à l’âge de chacun.' : 'Quizzes, Sudoku, word search and mental math, adapted to each person’s age.' }}</p>
                    <div class="visual"><span class="phone"><img src="{{ $screen('play-categories') }}" width="480" height="1039" loading="lazy" decoding="async" alt="{{ $fr ? 'Le choix des catégories dans le monde Explorateurs de l’espace.' : 'Picking a category in the Space Explorers world.' }}"></span></div>
                </li>
                <li class="step">
                    <h3>{{ $fr ? 'Atteignez vos objectifs et célébrez' : 'Reach goals together & celebrate' }}</h3>
                    <p>{{ $fr ? 'Un objectif pour la journée, la semaine ou le mois, avec une vraie récompense que vous choisissez.' : 'A goal for the day, week or month, with a real reward you choose.' }}</p>
                    <div class="visual"><span class="phone"><img src="{{ $screen('game-celebration') }}" width="480" height="1039" loading="lazy" decoding="async" alt="{{ $fr ? 'La célébration « Objectif atteint! » avec des confettis et le renard du Clan.' : 'The “Target reached!” celebration with confetti and the Clan fox.' }}"></span></div>
                </li>
            </ol>
        </div>
    </section>

    <section id="games" class="band" aria-labelledby="games-title">
        <div class="wrap">
            <div class="section-head center">
                <span class="eyebrow">{{ $fr ? 'Dans l’appli' : 'Inside the app' }}</span>
                <h2 id="games-title">{{ $fr ? 'De vrais jeux, pas des devoirs' : 'Real games, not homework' }}</h2>
            </div>
            <ul class="games" tabindex="0" aria-label="{{ $fr ? 'Captures d’écran des jeux' : 'Game screenshots' }}">
                <li><figure><span class="phone"><img src="{{ $screen('game-question') }}" width="480" height="1039" loading="lazy" decoding="async" alt="{{ $fr ? 'Question à choix multiples avec la bonne réponse et la réaction du personnage.' : 'A multiple-choice question with the right answer and the character’s reaction.' }}"></span>
                    <figcaption>{{ $fr ? 'Quiz' : 'Quizzes' }}<span>{{ $fr ? 'Votre personnage du Clan réagit à chaque réponse.' : 'Your Clan character reacts to every answer.' }}</span></figcaption></figure></li>
                <li><figure><span class="phone"><img src="{{ $screen('game-sudoku') }}" width="480" height="1039" loading="lazy" decoding="async" alt="{{ $fr ? 'Une grille de sudoku 6x6 dans le monde Explorateurs de l’espace.' : 'A 6x6 Sudoku grid in the Space Explorers world.' }}"></span>
                    <figcaption>Sudoku<span>{{ $fr ? 'Des grilles à la taille de chaque âge.' : 'Grids sized for every age.' }}</span></figcaption></figure></li>
                <li><figure><span class="phone"><img src="{{ $screen('game-wordsearch') }}" width="480" height="1039" loading="lazy" decoding="async" alt="{{ $fr ? 'Un jeu de mots cachés dans le monde Arcade néon, avec trois mots trouvés.' : 'A word search in the Neon Arcade world with three words found.' }}"></span>
                    <figcaption>{{ $fr ? 'Jeux de mots' : 'Word puzzles' }}<span>{{ $fr ? 'Mots cachés, anagrammes et lettre manquante.' : 'Word search, anagrams and missing letters.' }}</span></figcaption></figure></li>
                <li><figure><span class="phone"><img src="{{ $screen('game-race') }}" width="480" height="1039" loading="lazy" decoding="async" alt="{{ $fr ? 'Une course familiale dans le monde Sous la mer : chaque membre avance vers son objectif.' : 'A family race in the Under the Sea world: each member moves toward their target.' }}"></span>
                    <figcaption>{{ $fr ? 'Courses en famille' : 'Family races' }}<span>{{ $fr ? 'Affrontez-vous ou faites équipe pour une récompense.' : 'Compete or team up for a reward.' }}</span></figcaption></figure></li>
                <li><figure><span class="phone"><img src="{{ $screen('world-jungle') }}" width="480" height="1039" loading="lazy" decoding="async" alt="{{ $fr ? 'Un objectif d’équipe dans le monde Aventure dans la jungle : 72 sur 100 points, récompense « Pizza night! ».' : 'A team goal in the Jungle Adventure world: 72 of 100 points toward “Pizza night!”.' }}"></span>
                    <figcaption>{{ $fr ? 'Objectifs d’équipe' : 'Team goals' }}<span>{{ $fr ? 'Les points de tous s’additionnent.' : 'Everyone’s points add up.' }}</span></figcaption></figure></li>
            </ul>
        </div>
    </section>

    <section id="brain-games" class="band-brain" aria-labelledby="brain-games-title">
        <div class="wrap">
            <div class="section-head center">
                <span class="eyebrow">{{ $fr ? 'Nouveau : la catégorie Jeux de cerveau' : 'New: the Brain Games category' }}</span>
                <h2 id="brain-games-title">{{ $fr ? 'Des jeux de cerveau pour la mémoire et la logique' : 'Brain games for memory and logic' }}</h2>
                <p class="lead">{{ $fr
                    ? 'Quatre casse-têtes qui font travailler la mémoire, l’attention et la logique. Chaque partie est créée sur mesure, à la taille du niveau de chacun.'
                    : 'Four puzzles that train memory, focus and logic. Every round is freshly made and sized to each player’s level.' }}</p>
            </div>
            <ul class="brain">
                @foreach ($brainGames as [$image, $width, $height, $name, $text, $alt])
                    <li>
                        <div class="shot"><img src="{{ $screen($image) }}" width="{{ $width }}" height="{{ $height }}" loading="lazy" decoding="async" alt="{{ $alt }}"></div>
                        <h3>{{ $name }}</h3>
                        <p>{{ $text }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section id="categories" aria-labelledby="categories-title">
        <div class="wrap">
            <div class="section-head center">
                <span class="eyebrow">{{ $fr ? 'Quelque chose pour chacun' : 'Something for everyone' }}</span>
                <h2 id="categories-title">{{ $fr ? $allCategories.' catégories pour commencer' : $allCategories.' categories to start with' }}</h2>
            </div>
            <ul class="cats">
                @foreach ($categories as [$slug, $name, $description])
                    <li class="cat">
                        <img src="/images/site/art/{{ $slug }}.svg" width="256" height="256" loading="lazy" decoding="async" alt="">
                        <h3>{{ $name }}</h3>
                        <p>{{ $description }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section id="clan" class="band-sea" aria-labelledby="clan-title">
        <div class="wrap">
            <div class="section-head center">
                <span class="eyebrow">{{ $fr ? 'Les personnages' : 'The characters' }}</span>
                <h2 id="clan-title">{{ $fr ? 'Voici le Clan' : 'Meet the Clan' }}</h2>
                <p class="lead">{{ $fr
                    ? 'Chacun a son personnage, des enfants aux grands-parents : 22 animaux, chacun en version enfant, ado, maman, papa, grand-maman et grand-papa.'
                    : 'Everyone gets a character, from kids to grandparents: 22 animals, each as a kid, teen, mom, dad, grandma and grandpa.' }}</p>
            </div>
            <ul class="variants">
                @foreach ($clanFamily as [$key, $label, $alt])
                    <li><img src="/images/site/clan/{{ $key }}.svg" width="120" height="120" loading="lazy" decoding="async" alt="{{ $alt }}">{{ $label }}</li>
                @endforeach
            </ul>
            <div class="parade" aria-hidden="true">
                <div>
                    @foreach ($clanParade as $key)
                        <img src="/images/site/clan/{{ $key }}.svg" width="72" height="72" loading="lazy" decoding="async" alt="">
                    @endforeach
                    @foreach ($clanParade as $key)
                        <img class="dup" src="/images/site/clan/{{ $key }}.svg" width="72" height="72" loading="lazy" decoding="async" alt="">
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="unlocks" aria-labelledby="unlocks-title">
        <div class="wrap unlock">
            <div>
                <span class="eyebrow">{{ $fr ? 'Une raison de revenir' : 'A reason to come back' }}</span>
                <h2 id="unlocks-title">{{ $fr ? 'Jouez, gagnez des points, débloquez des mondes' : 'Play, earn points, unlock worlds' }}</h2>
                <p class="lead">{{ $fr
                    ? 'Les points et les séries de jours débloquent de nouveaux mondes, et ils restent débloqués. Quelques mondes spéciaux viennent avec le forfait Famille.'
                    : 'Points and daily streaks unlock new worlds, and they stay unlocked. A few special worlds come with the Family plan.' }}</p>
                <ul class="ways">
                    @foreach (['free' => [$fr ? 'Dès le départ' : 'From day one', 'free'], 'points' => [$fr ? 'Avec des points' : 'With points', 'points'], 'streak' => [$fr ? 'Avec une série de jours' : 'With a streak', 'streak'], 'family_plan' => [$fr ? 'Avec le forfait Famille' : 'With the Family plan', 'family_plan']] as $type => [$title, $class])
                        @if ($byUnlock[$type] !== [])
                            <li><span class="tag {{ $class }}">{{ $title }}</span><span class="list">{{ implode(', ', $byUnlock[$type]) }}</span></li>
                        @endif
                    @endforeach
                </ul>
            </div>
            <span class="phone"><img src="{{ $screen('worlds-picker') }}" width="480" height="1039" loading="lazy" decoding="async" alt="{{ $fr ? 'Le choix du monde : les mondes verrouillés montrent un cadenas et la progression, par exemple 133 sur 150 points.' : 'The world picker: locked worlds show a lock and the progress, for example 133 of 150 points.' }}"></span>
        </div>
    </section>

    <section id="what" class="band" aria-labelledby="what-title">
        <div class="wrap">
            <div class="section-head center">
                <h2 id="what-title">{{ $fr ? 'C’est quoi, Brainy Clan ?' : 'What is Brainy Clan?' }}</h2>
            </div>
            <ul class="why">
                <li>
                    <img src="/images/site/clan/fox.svg" alt="" width="56" height="56" loading="lazy">
                    <h3>{{ $fr ? 'Des défis en famille' : 'Family challenges' }}</h3>
                    <p>{{ $fr
                        ? 'De courtes parties de quiz et de casse-têtes. Chaque bonne réponse rapporte des points, et le classement reste entre vous.'
                        : 'Short rounds of quizzes and puzzles. Every correct answer earns points, and the leaderboard stays inside your family.' }}</p>
                </li>
                <li>
                    <img src="/images/site/clan/bear-dad.svg" alt="" width="56" height="56" loading="lazy">
                    <h3>{{ $fr ? 'Des objectifs ensemble' : 'Goals together' }}</h3>
                    <p>{{ $fr
                        ? 'Fixez un objectif pour la journée, la semaine ou le mois : affrontez-vous ou faites équipe vers un total commun, avec une récompense que vous choisissez.'
                        : 'Set a goal for the day, week or month: compete for the top spot or team up toward a shared total, with a reward you choose.' }}</p>
                </li>
                <li>
                    <img src="/images/site/clan/rabbit-grandma.svg" alt="" width="56" height="56" loading="lazy">
                    <h3>{{ $fr ? 'Adapté à chaque âge' : 'Adapted to every age' }}</h3>
                    <p>{{ $fr
                        ? 'La difficulté, les contenus et l’apparence s’adaptent à l’âge : ludique pour les enfants, épuré pour les ados, classique avec un texte plus grand pour les aînés.'
                        : 'Difficulty, content and look adapt to each age: playful for kids, sleek for teens, classic with larger text for seniors.' }}</p>
                </li>
                <li>
                    <img src="/images/site/clan/panda.svg" alt="" width="56" height="56" loading="lazy">
                    <h3>{{ $fr ? 'En français et en anglais' : 'In English and French' }}</h3>
                    <p>{{ $fr
                        ? 'Chaque membre choisit sa langue. Les questions et les cartes sont vérifiées par une personne avant d’être publiées.'
                        : 'Each member picks their language. Questions and cards are checked by a person before they are published.' }}</p>
                </li>
            </ul>
        </div>
    </section>

    <section id="chat" class="band-sea" aria-labelledby="chat-title">
        <div class="wrap chat">
            <div>
                <span class="eyebrow">{{ $fr ? 'Clavardage familial' : 'Family chat' }}</span>
                <h2 id="chat-title">{{ $fr ? 'Jasez en famille, en toute sécurité' : 'Chat together, safely' }}</h2>
                <p class="lead">{{ $fr
                    ? 'Un clavardage de groupe privé, juste pour votre famille : des messages et des émojis pour s’encourager, se lancer des défis et célébrer ensemble.'
                    : 'A private group chat just for your family: messages and emoji to cheer each other on, trade challenges and celebrate together.' }}</p>
                <ul class="perks">
                    <li><span class="ico" aria-hidden="true">🔒</span><span><strong>{{ $fr ? 'Seulement votre famille' : 'Only your family' }}</strong><span>{{ $fr ? 'Seuls les membres de votre famille peuvent lire le clavardage. Aucun inconnu, jamais.' : 'Only the members of your family can read it. No strangers, ever.' }}</span></span></li>
                    <li><span class="ico" aria-hidden="true">🛡️</span><span><strong>{{ $fr ? 'Les parents décident' : 'Parents are in control' }}</strong><span>{{ $fr ? 'Un parent peut désactiver le clavardage pour un enfant ou retirer n’importe quel message.' : 'A parent can turn chat off for a child or remove any message.' }}</span></span></li>
                    <li><span class="ico" aria-hidden="true">💬</span><span><strong>{{ $fr ? 'Du texte et des émojis' : 'Just text and emoji' }}</strong><span>{{ $fr ? 'Pas de liens cliquables, pas de photos, pas de fichiers.' : 'No clickable links, no photos, no files.' }}</span></span></li>
                    <li><span class="ico" aria-hidden="true">🕒</span><span><strong>{{ $fr ? 'Des messages qui ne s’accumulent pas' : 'Messages don’t pile up' }}</strong><span>{{ $fr ? 'Gardés '.$chatFree.' avec le forfait gratuit et '.$chatFamily.' avec le forfait Famille, puis supprimés.' : 'Kept for '.$chatFree.' on the free plan and '.$chatFamily.' on the Family plan, then deleted.' }}</span></span></li>
                </ul>
            </div>
            <div class="chat-phone">
                <span class="bubble b1" aria-hidden="true">🎉 👏</span>
                <span class="phone"><img src="{{ $screen('chat-family') }}" width="480" height="1039" loading="lazy" decoding="async" alt="{{ $fr ? 'Le clavardage familial dans le monde Pays des bonbons : Maman, Alex, Papa et Sam s’organisent pour jouer avant le souper, avec des émojis.' : 'The family chat in the Candy Land world: Mom, Alex, Dad and Sam plan a game before dinner, with emoji.' }}"></span>
                <span class="bubble b2" aria-hidden="true">❤️ 🦊</span>
            </div>
        </div>
    </section>

    <section id="safety" aria-labelledby="safety-title">
        <div class="wrap safe">
            <div>
                <span class="eyebrow">{{ $fr ? 'Pour les parents' : 'For parents' }}</span>
                <h2 id="safety-title">{{ $fr ? 'Pensé pour les enfants' : 'Safe for kids' }}</h2>
                <ul class="checks">
                    <li>{{ $fr ? 'Aucune publicité pour les enfants, jamais.' : 'No ads for children, ever.' }}</li>
                    <li>{{ $fr
                        ? 'Les parents décident : un parent invite chaque enfant, donne son consentement et gère la famille.'
                        : 'Parents are in control: a parent invites each child, gives consent and manages the family.' }}</li>
                    <li>{{ $fr
                        ? 'Aucun contact avec des inconnus : votre famille est un espace privé, et le seul clavardage est celui de votre famille.'
                        : 'No contact with strangers: your family is a private space, and the only chat is your own family chat.' }}</li>
                    <li>{{ $fr
                        ? 'Le clavardage familial reste sous votre contrôle : un parent peut le désactiver pour un enfant ou retirer n’importe quel message.'
                        : 'Family chat stays under your control: a parent can turn it off for a child or remove any message.' }}</li>
                    <li>{{ $fr
                        ? 'Le minimum de données : pour un enfant, un surnom, un avatar, l’année et le mois de naissance. Pas de courriel, pas de mot de passe, pas de photo.'
                        : 'Minimal data: for a child, a nickname, an avatar and a birth year and month. No email, no password, no photo.' }}</li>
                    <li>{{ $fr
                        ? 'Les ados et adultes du forfait gratuit peuvent choisir de regarder une courte pub pour une seconde chance; ces pubs ne sont jamais personnalisées et sont classées pour tous.'
                        : 'Teens and adults on the free plan can choose to watch a short ad for a second chance; those ads are never personalized and are rated for general audiences.' }}</li>
                    <li>{{ $fr
                        ? 'Vous pouvez télécharger vos données ou supprimer votre compte en tout temps.'
                        : 'You can download your data or delete your account at any time.' }}</li>
                </ul>
            </div>
            <img class="owl" src="/images/owl.svg" width="240" height="240" loading="lazy" alt="{{ $fr ? 'Ollie, la chouette de Brainy Clan' : 'Ollie, the Brainy Clan owl' }}">
        </div>
    </section>

    <section id="plans" class="band-pop" aria-labelledby="plans-title">
        <div class="wrap">
            <div class="section-head center">
                <h2 id="plans-title">{{ $fr ? 'Forfaits' : 'Plans' }}</h2>
            </div>
            <div class="plans">
                <div class="card plan">
                    <h3>{{ $fr ? 'Gratuit' : 'Free' }}</h3>
                    <ul>
                        <li>{{ $fr ? 'Jusqu’à '.$free['members'].' membres de la famille, vous compris' : 'Up to '.$free['members'].' family members, you included' }}</li>
                        <li>{{ $free['categories'] >= $allCategories
                            ? ($fr ? 'Toutes les catégories' : 'All categories')
                            : ($fr ? $free['categories'].' catégories' : $free['categories'].' categories') }}</li>
                        <li>{{ $free['goals'] === null
                            ? ($fr ? 'Objectifs familiaux illimités' : 'Unlimited family goals')
                            : ($fr ? $free['goals'].' objectif familial actif' : $free['goals'].' active family goal'.($free['goals'] === 1 ? '' : 's')) }}</li>
                        <li>{{ $fr
                            ? 'Seconde chance : une ronde de rattrapage, ou une courte pub (ados et adultes seulement)'
                            : 'Second chance: a redemption round, or a short ad (teens and adults only)' }}</li>
                        <li>{{ $fr ? 'Clavardage familial : messages gardés '.$chatFree : 'Family chat: messages kept for '.$chatFree }}</li>
                    </ul>
                </div>
                <div class="card plan featured">
                    <h3>{{ $fr ? 'Forfait Famille' : 'Family plan' }}</h3>
                    <ul>
                        <li>{{ $fr ? 'Jusqu’à '.$family['members'].' membres de la famille, vous compris' : 'Up to '.$family['members'].' family members, you included' }}</li>
                        <li>{{ $family['categories'] >= $allCategories
                            ? ($fr ? 'Les '.$allCategories.' catégories' : 'All '.$allCategories.' categories')
                            : ($fr ? $family['categories'].' catégories' : $family['categories'].' categories') }}</li>
                        <li>{{ $family['goals'] === null
                            ? ($fr ? 'Objectifs familiaux illimités' : 'Unlimited family goals')
                            : ($fr ? $family['goals'].' objectifs familiaux actifs' : $family['goals'].' active family goals') }}</li>
                        @if ($family['free_second_chances'] > 0)
                            <li>{{ $fr
                                ? $family['free_second_chances'].' secondes chances gratuites par membre et par jour'
                                : $family['free_second_chances'].' free second chances per member per day' }}</li>
                        @endif
                        <li>{{ $fr ? 'Clavardage familial : messages gardés '.$chatFamily : 'Family chat: messages kept for '.$chatFamily }}</li>
                        @unless ($family['ads'])
                            <li>{{ $fr ? 'Aucune publicité' : 'No ads' }}</li>
                        @endunless
                        @if ($byUnlock['family_plan'] !== [])
                            <li>{{ ($fr ? 'Mondes exclusifs : ' : 'Exclusive worlds: ').implode(', ', $byUnlock['family_plan']) }}</li>
                        @endif
                    </ul>
                    <p class="note">{{ $fr
                        ? 'Un abonnement acheté par un adulte dans l’application couvre toute la famille.'
                        : 'One subscription, bought by an adult in the app, covers the whole family.' }}</p>
                    @if ($webCheckout ?? false)
                        <p class="note">{{ $fr ? 'Aussi offert sur le Web :' : 'Also available on the web:' }}
                            <a href="{{ route('website.subscribe', $lang) }}">{{ $fr ? 'payez par carte ou PayPal' : 'pay by card or PayPal' }}</a>.</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section id="get" aria-labelledby="get-title">
        <div class="wrap">
            <div class="cta">
                <div class="row" aria-hidden="true">
                    @foreach (['penguin', 'fox', 'owl-mom', 'lion-grandpa', 'dragon'] as $key)
                        <img src="/images/site/clan/{{ $key }}.svg" width="64" height="64" loading="lazy" alt="">
                    @endforeach
                </div>
                <h2 id="get-title">{{ $fr ? 'Bientôt disponible' : 'Coming soon' }}</h2>
                <p>{{ $fr
                    ? 'Brainy Clan arrive bientôt sur iPhone, iPad et Android. Des questions ?'
                    : 'Brainy Clan is coming soon to iPhone, iPad and Android. Questions?' }}
                    <a href="{{ route('website.support', $lang) }}">{{ $fr ? 'Visitez la page d’aide' : 'Visit the support page' }}</a>.</p>
                <ul class="stores" aria-label="{{ $fr ? 'Disponibilité' : 'Availability' }}">
                    <li>{{ $fr ? 'Bientôt sur l’App Store' : 'Coming soon to the App Store' }}</li>
                    <li>{{ $fr ? 'Bientôt sur Google Play' : 'Coming soon to Google Play' }}</li>
                </ul>
            </div>
        </div>
    </section>
@endsection
