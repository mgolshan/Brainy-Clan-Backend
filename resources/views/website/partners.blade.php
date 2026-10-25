{{--
    Partner (affiliate) program page (/partners, docs/PLAN.md "Partner (affiliate) instructions
    (2026-10-24)"). Same content as docs/PARTNER_GUIDE.md (+ .fr.md): keep them in sync. Claims come
    from the approved list (docs/OPERATIONS_HANDBOOK.md §10.3) and follow the mission and the content
    neutrality rules. Every commission / payout number comes from config('billing.affiliates') via
    WebsiteController::partners ($terms); a line whose value is null or empty is left out — never
    type a number here. $webCheckout: the web discount is only mentioned while web checkout is open.
    Stateless, no scripts, nothing from other hosts.
--}}
@php
    $fr = $locale === 'fr';
    $lang = ['lang' => $locale];
    $percent = $terms['rate'] !== null ? ($fr ? str_replace('.', ',', $terms['rate'])."\u{00A0}%" : $terms['rate'].'%') : null;
    $list = fn (array $items): string => $items === [] ? '' : (count($items) === 1 ? $items[0] : implode(', ', array_slice($items, 0, -1)).($fr ? ' et ' : ' and ').end($items));
    $methodLabels = $fr
        ? ['paypal' => 'PayPal', 'interac' => 'virement Interac (Canada)', 'bank' => 'virement bancaire']
        : ['paypal' => 'PayPal', 'interac' => 'Interac e-Transfer (Canada)', 'bank' => 'bank transfer'];
    $methods = array_map(fn (string $method): string => $methodLabels[$method], $terms['methods']);
    $applyUrl = 'mailto:'.$supportEmail.'?subject='.rawurlencode('Partner application');
    $refLink = \App\Modules\Core\Support\Brand::domain().'/r/'.($fr ? 'VOTRECODE' : 'YOURCODE');
    $toc = $fr
        ? [['how', 'Fonctionnement'], ['commission', 'Commission'], ['rules', 'Règles'], ['brand', 'Trousse de marque'], ['faq', 'FAQ'], ['apply', 'Postuler']]
        : [['how', 'How it works'], ['commission', 'Commission'], ['rules', 'Rules'], ['brand', 'Brand kit'], ['faq', 'FAQ'], ['apply', 'Apply']];
    $screens = [
        ['game-question', $fr ? 'Une question de quiz' : 'A quiz question'],
        ['play-categories', $fr ? 'Les catégories' : 'Categories'],
        ['game-sudoku', 'Sudoku'],
        ['game-race', $fr ? 'Course en famille' : 'Family race'],
        ['chat-family', $fr ? 'Discussion familiale' : 'Family chat'],
        ['worlds-picker', $fr ? 'Les mondes' : 'Worlds'],
    ];
@endphp
@extends('website.layout')

@section('backToTop', '1')
@section('title', $fr ? 'Partenaires — Brainy Clan' : 'Partners — Brainy Clan')
@section('description', $fr
    ? 'Le programme partenaires de Brainy Clan pour les parents et les créateurs qui partagent notre mission : fonctionnement, commission, règles, trousse de marque et comment postuler.'
    : 'The Brainy Clan partner program for parents and creators who share our mission: how it works, commission, rules, brand kit and how to apply.')

@section('styles')
    .p-hero { padding: 28px 0 12px; }
    .p-hero .wrap { display: grid; gap: 20px; align-items: center; }
    @media (min-width: 820px) { .p-hero .wrap { grid-template-columns: minmax(0, 1fr) 300px; } .p-hero { padding-top: 44px; } }
    .eyebrow { display: inline-block; margin-bottom: 10px; padding: 4px 12px; border-radius: 999px; background: var(--brand-soft); font-weight: 700; font-size: 0.85rem; }
    .cta { display: inline-block; padding: 12px 22px; border-radius: 999px; background: var(--brand); color: #1b2a4a; font-weight: 800; text-decoration: none; }
    .cta:hover { filter: brightness(1.05); }
    .hero-clan { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; max-width: 300px; margin: 0 auto; padding: 0; list-style: none; }
    .hero-clan img { display: block; width: 100%; height: auto; border-radius: 22px; background: var(--surface); border: 1px solid var(--border); }
    @media (max-width: 819px) { .hero-clan { grid-template-columns: repeat(6, 1fr); max-width: 420px; } .hero-clan img { border-radius: 14px; } }
    .jump { display: flex; flex-wrap: wrap; gap: 8px; margin: 18px 0 0; padding: 0; list-style: none; }
    .jump a { display: inline-block; padding: 6px 12px; border-radius: 999px; background: var(--surface); border: 1px solid var(--border); color: var(--fg); text-decoration: none; font-size: 0.9rem; }
    .jump a:hover { border-color: var(--brand); }
    section[id] { scroll-margin-top: 12px; }
    .section-lead { color: var(--muted); max-width: 680px; margin-bottom: 18px; }
    .steps4 { counter-reset: step; display: grid; gap: 14px; grid-template-columns: minmax(0, 1fr); margin: 0; padding: 0; list-style: none; }
    @media (min-width: 720px) { .steps4 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .steps4 code { white-space: nowrap; }
    .steps4 li { counter-increment: step; }
    .steps4 h3::before { content: counter(step); display: inline-block; width: 30px; height: 30px; margin-right: 10px; border-radius: 50%; background: var(--brand); color: #1b2a4a; text-align: center; line-height: 30px; font-size: 0.95rem; }
    .steps4 code, .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.92em; background: var(--brand-soft); padding: 1px 6px; border-radius: 6px; overflow-wrap: anywhere; }
    .two { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
    .terms { list-style: none; margin: 0; padding: 0; }
    .terms li { display: flex; gap: 12px; padding: 12px 0; border-top: 1px solid var(--border); }
    .terms li:first-child { border-top: 0; padding-top: 0; }
    .terms strong { display: block; }
    .terms .big { flex: none; width: 5.4rem; font-size: 1.35rem; font-weight: 800; color: var(--brand); line-height: 1.2; }
    .terms span { color: var(--muted); }
    .donts { list-style: none; padding: 0; margin: 0; }
    .donts li { padding-left: 1.8em; position: relative; margin-bottom: 10px; }
    .donts li::before { content: "✕"; position: absolute; left: 0.2em; color: #c0392b; font-weight: 800; }
    @media (prefers-color-scheme: dark) { .donts li::before { color: #ff8a80; } .cta, .steps4 h3::before { color: #0f1626; } }
    .card h3 { margin-bottom: 10px; }
    .card ul + p, .card p + ul { margin-top: 10px; }
    .warn { border-left: 4px solid var(--brand); }
    .copy { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); margin: 0 0 16px; }
    .copy blockquote { margin: 0; padding: 14px 16px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; }
    .copy blockquote p { margin: 0 0 8px; color: var(--fg); }
    .copy blockquote p:last-child { margin-bottom: 0; }
    .copy .tag { display: inline-block; margin-bottom: 6px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); }
    .shots { display: grid; gap: 10px; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 0; padding: 0; list-style: none; }
    @media (min-width: 720px) { .shots { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    .shots a { display: block; color: var(--fg); text-decoration: none; font-size: 0.85rem; text-align: center; }
    .shots img { display: block; width: 100%; height: auto; margin-bottom: 4px; border-radius: 12px; border: 1px solid var(--border); }
    .draft { display: inline-block; margin-left: 6px; padding: 2px 10px; border-radius: 6px; background: #fff3cd; color: #6b4e00; font-size: 0.8rem; font-weight: 800; vertical-align: middle; letter-spacing: 0.02em; }
    @media (prefers-color-scheme: dark) { .draft { background: #4a3b10; color: #ffe08a; } }
    .mt { margin-top: 12px; }
    .mt-lg { margin-top: 24px; }
    .apply { text-align: center; }
    .apply .card { max-width: 680px; margin: 0 auto; text-align: left; }
@endsection

@section('content')
    <section class="p-hero" aria-labelledby="partners-title">
        <div class="wrap">
            <div>
                <span class="eyebrow">{{ $fr ? 'Programme partenaires' : 'Partner program' }}</span>
                <h1 id="partners-title">{{ $fr ? 'Devenez partenaire de Brainy Clan' : 'Partner with Brainy Clan' }}</h1>
                <p class="lead">{{ $fr
                    ? 'Pour les parents et les créateurs qui partagent notre mission : que les enfants utilisent les écrans pour apprendre et grandir plutôt que pour perdre leur temps. Recommandez Brainy Clan à d’autres parents avec votre propre code et touchez une commission sur les forfaits payés.'
                    : 'For parents and creators who share our mission: kids using screens to learn and grow rather than waste their time and mind. Recommend Brainy Clan to other parents with your own code and earn a commission on paid plans.' }}</p>
                <p><a class="cta" href="#apply">{{ $fr ? 'Comment postuler' : 'How to apply' }}</a></p>
                <ul class="jump" aria-label="{{ $fr ? 'Sur cette page' : 'On this page' }}">
                    @foreach ($toc as [$anchor, $label])
                        <li><a href="#{{ $anchor }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
            <ul class="hero-clan" aria-hidden="true">
                @foreach (['owl-mom', 'fox', 'lion-grandpa', 'panda', 'tiger-teen', 'rabbit-grandma'] as $key)
                    <li><img src="/images/site/clan/{{ $key }}.svg" width="90" height="90" alt="" decoding="async"></li>
                @endforeach
            </ul>
        </div>
    </section>

    <section id="what" class="alt" aria-labelledby="what-title">
        <div class="wrap">
            <h2 id="what-title">{{ $fr ? 'Brainy Clan en bref' : 'What Brainy Clan is' }}</h2>
            <p class="section-lead">{{ $fr
                ? 'Une application d’apprentissage en famille. Voici ce que vous pouvez dire : chaque point est vrai aujourd’hui.'
                : 'A family learning app. Here is what you can say about it: every point is true today.' }}</p>
            <ul class="checks">
                @foreach ($fr ? [
                    'Quiz, sudoku (de 4x4 à 9x9), sprint de maths, jeux de mots (anagrammes, lettre manquante, mots cachés) et jeux de cerveau (mémoire, suites, logimages, jeu des différences).',
                    'Chacun joue à son niveau : les questions s’adaptent au groupe d’âge et à la difficulté de chaque joueur.',
                    'Objectifs familiaux (quotidiens, hebdomadaires, mensuels) : faites la course ou faites équipe pour une récompense dans la vraie vie.',
                    'Les enfants s’inscrivent avec un simple surnom : pas de courriel, pas de mot de passe, pas de vrai nom, pas de photo. Un parent donne son consentement pour chaque enfant.',
                    'Aucune publicité pour les enfants, jamais. Pas de contact avec des inconnus : la seule discussion est celle de votre famille, et les parents peuvent la désactiver.',
                    'Chaque question est révisée avant d’être publiée. En anglais et en français, sur iPhone, iPad et Android.',
                ] : [
                    'Quizzes, Sudoku (4×4 to 9×9), Math Sprint, word puzzles (anagrams, missing letters, word search) and brain games (memory match, pattern sequence, nonograms, spot the difference).',
                    'Everyone plays at their own level: questions adapt to each player’s age group and difficulty.',
                    'Family goals (daily, weekly, monthly): race each other or team up for a real-world reward.',
                    'Kids join with just a nickname — no email, no password, no real name, no photo. A parent gives consent for each child.',
                    'No ads for children, ever. No contact with strangers: the only chat is your own private family chat, and parents can turn it off.',
                    'Every question is reviewed before it is published. English and French, on iPhone, iPad and Android.',
                ] as $claim)
                    <li>{{ $claim }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section id="how" aria-labelledby="how-title">
        <div class="wrap">
            <h2 id="how-title">{{ $fr ? 'Comment ça marche' : 'How it works' }}</h2>
            <ol class="steps4">
                <li class="card">
                    <h3>{{ $fr ? 'Postulez' : 'Apply' }}</h3>
                    <p>{{ $fr ? 'Écrivez-nous avec vos chaînes et votre public. Nous vérifions que votre contenu s’adresse aux parents et respecte notre mission.' : 'Email us with your channels and audience. We check that your content speaks to parents and fits our mission.' }}</p>
                </li>
                <li class="card">
                    <h3>{{ $fr ? 'Recevez votre code' : 'Get your code' }}</h3>
                    <p>{{ $fr ? 'Nous créons votre code personnel (par ex. MAYA) et votre lien' : 'We create your personal code (e.g. MAYA) and your link' }} <code>{{ $refLink }}</code>.</p>
                </li>
                <li class="card">
                    <h3>{{ $fr ? 'Partagez avec #pub' : 'Share with #ad' }}</h3>
                    <p>{{ $fr ? 'Parlez de Brainy Clan aux parents, avec une divulgation claire dans chaque publication.' : 'Tell parents about Brainy Clan, with a clear disclosure in every post.' }}</p>
                </li>
                <li class="card">
                    <h3>{{ $fr ? 'Touchez une commission' : 'Earn' }}</h3>
                    <p>{{ $fr ? 'Quand une famille utilise votre code et paie le forfait Famille, vous touchez une commission selon les conditions ci-dessous.' : 'When a family uses your code and pays for the Family plan, you earn a commission on the terms below.' }}</p>
                </li>
            </ol>
        </div>
    </section>

    <section id="audience" class="alt" aria-labelledby="audience-title">
        <div class="wrap">
            <h2 id="audience-title">{{ $fr ? 'Ce que votre public reçoit' : 'What your audience gets' }}</h2>
            <p class="section-lead">{{ $fr
                ? 'Le forfait gratuit reste gratuit : n’importe qui peut essayer Brainy Clan sans payer. Votre code relie la famille à vous. Si votre code comprend une offre, vos abonnés en profitent aussi :'
                : 'The free plan stays free: anyone can try Brainy Clan without paying. Your code links the family to you. If your code includes an offer, your audience also gets it:' }}</p>
            <ul class="checks">
                <li>{{ $fr
                    ? 'un code d’offre de l’App Store ou de Google Play, à utiliser lors de l’achat dans l’application;'
                    : 'an App Store or Google Play offer code, redeemed when buying in the app;' }}</li>
                @if ($webCheckout)
                    <li>{{ $fr
                        ? 'un rabais appliqué automatiquement quand ils s’abonnent sur '.\App\Modules\Core\Support\Brand::domain().' avec votre lien ou votre code.'
                        : 'a discount applied automatically when they subscribe on '.\App\Modules\Core\Support\Brand::domain().' through your link or code.' }}</li>
                @endif
            </ul>
            <p class="note">{{ $fr
                ? 'Nous vous dirons quelle offre votre code comprend quand nous le créons. Ne promettez jamais un rabais que nous n’avons pas confirmé.'
                : 'We tell you which offer your code includes when we create it. Never promise a discount we have not confirmed.' }}</p>
        </div>
    </section>

    <section id="commission" aria-labelledby="commission-title">
        <div class="wrap">
            <h2 id="commission-title">{{ $fr ? 'Commission et paiements' : 'Commission and payouts' }}</h2>
            <div class="two">
                <div class="card">
                    <h3>{{ $fr ? 'Conditions standard' : 'Standard terms' }}</h3>
                    <ul class="terms">
                        @if ($percent !== null)
                            <li id="term-rate"><span class="big">{{ $percent }}</span><p><strong>{{ $fr ? 'des revenus nets' : 'of net revenue' }}</strong>
                                <span>{{ $fr ? 'de chaque paiement d’une famille liée à votre code. Net = prix payé moins les taxes de vente et les frais de la boutique ou de Paddle.' : 'from each payment of a family linked to your code. Net = the price paid minus sales tax and the store or Paddle fee.' }}</span></p></li>
                        @endif
                        @if ($terms['months'] !== null)
                            <li id="term-months"><span class="big">{{ $terms['months'] }}</span><p><strong>{{ $fr ? 'mois par famille' : 'months per family' }}</strong>
                                <span>{{ $fr ? 'à compter du premier paiement de la famille : renouvellements compris.' : 'counted from the family’s first payment, renewals included.' }}</span></p></li>
                        @endif
                        @if ($terms['refund_days'] !== null)
                            <li id="term-hold"><span class="big">{{ $terms['refund_days'] }}</span><p><strong>{{ $fr ? 'jours de retenue' : 'day refund hold' }}</strong>
                                <span>{{ $fr ? 'Chaque commission attend la fin de la période de remboursement avant de devenir payable. Les remboursements et rétrofacturations sont déduits.' : 'Each commission waits out the refund window before it becomes payable. Refunds and chargebacks are deducted.' }}</span></p></li>
                        @endif
                        @if ($terms['schedule'] === 'monthly')
                            <li id="term-schedule"><span class="big">{{ $fr ? '1×/mois' : '1×/mo' }}</span><p><strong>{{ $fr ? 'Paiement mensuel' : 'Monthly payout' }}</strong>
                                <span>{{ $fr ? 'Nous faisons les paiements à la main une fois par mois, avec un relevé par courriel.' : 'We pay by hand once a month, with a statement by email.' }}</span></p></li>
                        @endif
                    </ul>
                    @if ($terms['currencies'] !== [])
                        <p class="note" id="term-currencies">{{ $fr ? 'Devises : ' : 'Currencies: ' }}{{ $list($terms['currencies']) }}{{ $fr ? ' (la devise de la vente).' : ' (the currency of the sale).' }}</p>
                    @endif
                    @if ($terms['minimum'] !== [])
                        <p class="note" id="term-minimum">{{ $fr ? 'Paiement minimum : ' : 'Minimum payout: ' }}{{ $list($terms['minimum']) }}{{ $fr ? '. Un solde plus petit est reporté au mois suivant.' : '. A smaller balance rolls over to the next month.' }}</p>
                    @endif
                    <p class="note">{{ $fr ? 'Votre entente écrite peut prévoir d’autres conditions; elle a priorité.' : 'Your written agreement may set different terms; it takes precedence.' }}</p>
                </div>
                <div class="card">
                    <h3>{{ $fr ? 'Comment vous êtes payé' : 'How you get paid' }}</h3>
                    <ul class="checks">
                        @if ($methods !== [])
                            <li id="term-methods">{{ $fr ? 'Par ' : 'By ' }}{{ $list($methods) }}{{ $fr ? ' : paiement fait à la main par notre équipe.' : ', sent by hand by our team.' }}</li>
                        @endif
                        <li>{{ $fr ? 'Votre relevé indique les montants par mois et par devise, jamais l’identité des familles.' : 'Your statement shows amounts per month and currency, never who the families are.' }}</li>
                        <li>{{ $fr ? 'Vous êtes responsable de vos impôts, de vos taxes et de vos factures. Nous pouvons vous demander une facture ou vos renseignements fiscaux quand la loi l’exige.' : 'You handle your own taxes and invoices. We may ask for an invoice or tax details where the law requires it.' }}</li>
                        <li>{{ $fr ? 'Ne nous envoyez jamais un numéro de carte, ni un numéro de compte complet par simple courriel : nous conviendrons d’un moyen sûr.' : 'Never email us a card number or a full account number: we agree on a safe way together.' }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section id="rules" class="alt" aria-labelledby="rules-title">
        <div class="wrap">
            <h2 id="rules-title">{{ $fr ? 'Les règles' : 'The rules' }}</h2>
            <p class="section-lead">{{ $fr
                ? 'Brainy Clan est une application pour les familles. Ces règles protègent les enfants, vos abonnés et vous.'
                : 'Brainy Clan is an app for families. These rules protect children, your audience and you.' }}</p>
            <div class="two">
                <div class="card">
                    <h3>{{ $fr ? 'Toujours' : 'Always' }}</h3>
                    <ul class="checks">
                        <li>{{ $fr
                            ? 'Divulguez dans chaque publication, au début et de façon visible : « #pub », « #ad » ou « Partenariat rémunéré avec Brainy Clan », et dites-le à voix haute dans les vidéos (FTC, Bureau de la concurrence, Normes de la publicité).'
                            : 'Disclose in every post, at the start and clearly: “#ad” or “Paid partnership with Brainy Clan”, and say it out loud in videos (FTC, Competition Bureau, Ad Standards).' }}</li>
                        <li>{{ $fr ? 'Adressez-vous aux parents et aux adultes.' : 'Speak to parents and adults.' }}</li>
                        <li>{{ $fr ? 'Utilisez seulement les affirmations de la section « Brainy Clan en bref » et de la trousse de marque.' : 'Use only the claims in “What Brainy Clan is” and the brand kit.' }}</li>
                        <li>{{ $fr ? 'Restez fidèle à notre mission et à nos règles de contenu : positif, honnête, éducatif, sans prise de position politique, idéologique ou religieuse.' : 'Stay true to our mission and content rules: positive, honest and educational, with no political, ideological or religious messaging.' }}</li>
                        <li>{{ $fr ? 'Ne filmez un enfant qu’avec le consentement écrit de ses parents, et ne montrez jamais le nom ou le visage d’un enfant dans l’application.' : 'Film a child only with their parents’ written consent, and never show a child’s name or face in the app.' }}</li>
                    </ul>
                </div>
                <div class="card">
                    <h3>{{ $fr ? 'Jamais' : 'Never' }}</h3>
                    <ul class="donts">
                        <li>{{ $fr ? 'Cibler ou interpeller des enfants, ou publier sur des chaînes destinées aux enfants.' : 'Target or address children, or post on kid-directed channels.' }}</li>
                        <li>{{ $fr ? 'Faire des affirmations fausses, médicales ou sur les résultats scolaires (« meilleures notes garanties », « prouvé »), ni parler de certifications que nous n’avons pas.' : 'Make false, medical or guaranteed-grades claims (“better grades guaranteed”, “proven”), or mention certifications we don’t hold.' }}</li>
                        <li>{{ $fr ? 'Envoyer du pourriel ou des messages non sollicités.' : 'Spam or send unsolicited messages.' }}</li>
                        <li>{{ $fr ? 'Acheter des annonces sur le mot-clé « Brainy Clan » dans les moteurs de recherche.' : 'Bid on “Brainy Clan” in search ads.' }}</li>
                        <li>{{ $fr ? 'Publier de faux avis, payer ou récompenser des installations, faire du bourrage de témoins, ou utiliser votre propre code.' : 'Post fake reviews, offer incentivised installs, use cookie stuffing, or use your own code (self-referrals).' }}</li>
                    </ul>
                </div>
            </div>
            <p class="card warn mt">{{ $fr
                ? 'En cas de manquement, nous pouvons mettre fin au partenariat et retenir les commissions liées.'
                : 'If these rules are broken, we may end the partnership and withhold the related commission.' }}</p>
        </div>
    </section>

    <section id="brand" aria-labelledby="brand-title">
        <div class="wrap">
            <h2 id="brand-title">{{ $fr ? 'Trousse de marque' : 'Brand kit' }}</h2>
            <div class="two">
                <div class="card">
                    <h3>{{ $fr ? 'Le nom, le hibou et le Clan' : 'The name, the owl and the Clan' }}</h3>
                    <ul class="checks">
                        <li>{{ $fr ? 'Écrivez « Brainy Clan » : deux mots, B et C majuscules.' : 'Write “Brainy Clan”: two words, capital B and C.' }}</li>
                        <li>{{ $fr ? 'Ne modifiez pas le hibou ni les personnages du Clan : pas de nouvelles couleurs, poses, paroles ou scènes.' : 'Don’t alter the owl or the Clan characters: no new colours, poses, words or scenes.' }}</li>
                        <li>{{ $fr ? 'Ne les mettez jamais dans des publicités destinées aux enfants.' : 'Never put them in ads aimed at kids.' }}</li>
                        <li>{{ $fr ? 'Demandez-nous avant de les utiliser dans une publicité payée : les illustrations peuvent encore changer.' : 'Ask us before using them in paid ads: the artwork may still change.' }}</li>
                    </ul>
                </div>
                <div class="card">
                    <h3>{{ $fr ? 'Captures d’écran' : 'Screenshots' }}</h3>
                    <p>{{ $fr ? 'Utilisez les captures de notre famille de démonstration (aucune donnée réelle) :' : 'Use the screenshots of our demo family (no real user data):' }}</p>
                    <ul class="shots">
                        @foreach ($screens as [$file, $label])
                            <li><a href="/images/site/screens/{{ $file }}.webp"><img src="/images/site/screens/{{ $file }}.webp" width="120" height="260" loading="lazy" decoding="async" alt="">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                    <p class="note mt">{{ $fr ? 'D’autres sur la' : 'More on the' }} <a href="{{ route('website.home', $lang) }}">{{ $fr ? 'page d’accueil' : 'home page' }}</a>.</p>
                </div>
            </div>

            <h3 class="mt-lg">{{ $fr ? 'Phrases approuvées' : 'Approved one-liners' }}</h3>
            <div class="copy">
                <blockquote lang="en"><span class="tag">English</span>
                    <p>Quizzes, puzzles and brain games the whole family plays together, each at their own level.</p>
                    <p>A few minutes a day that turn into a family habit.</p>
                    <p>Kids join with just a nickname, never see ads, and only chat with their own family.</p>
                </blockquote>
                <blockquote lang="fr"><span class="tag">Français</span>
                    <p>Des quiz, des casse-têtes et des jeux de cerveau pour toute la famille, chacun à son niveau.</p>
                    <p>Quelques minutes par jour qui deviennent une habitude familiale.</p>
                    <p>Les enfants s’inscrivent avec un simple surnom, ne voient jamais de publicité et ne discutent qu’avec leur famille.</p>
                </blockquote>
            </div>
            <h3>{{ $fr ? 'Description courte' : 'Short description' }}</h3>
            <div class="copy">
                <blockquote lang="en"><span class="tag">English</span>
                    <p>Brainy Clan is a family learning app: quizzes, Sudoku, Math Sprint, word puzzles and brain games where kids, teens, parents and grandparents each play at their own level and work toward family goals together.</p>
                </blockquote>
                <blockquote lang="fr"><span class="tag">Français</span>
                    <p>Brainy Clan est une application d’apprentissage en famille : quiz, sudoku, sprint de maths, jeux de mots et jeux de cerveau où enfants, ados, parents et grands-parents jouent chacun à leur niveau et visent ensemble des objectifs familiaux.</p>
                </blockquote>
            </div>
            <h3>{{ $fr ? 'Description longue' : 'Long description' }}</h3>
            <div class="copy">
                <blockquote lang="en"><span class="tag">English</span>
                    <p>Brainy Clan turns a few minutes of screen time into learning the whole family shares. Everyone plays short challenges at their own level — quizzes from geography and science to logic, art and music, plus Sudoku, Math Sprint, word puzzles and brain games — and the points go toward family goals: race each other or team up for a real-world reward.</p>
                    <p>It is built for families: kids join with just a nickname (no email, no password, no photo), a parent gives consent for each child, children never see ads, and the only chat is your family’s own private chat, which parents can turn off. Every question is reviewed before it is published. In English and French, on iPhone, iPad and Android.</p>
                </blockquote>
                <blockquote lang="fr"><span class="tag">Français</span>
                    <p>Brainy Clan transforme quelques minutes d’écran en apprentissage partagé par toute la famille. Chacun relève de courts défis à son niveau — des quiz de géographie, de sciences, de logique, d’art et de musique, du sudoku, des sprints de maths, des jeux de mots et des jeux de cerveau — et les points servent aux objectifs familiaux : faites la course ou faites équipe pour une récompense dans la vraie vie.</p>
                    <p>L’application est pensée pour les familles : les enfants s’inscrivent avec un simple surnom (pas de courriel, pas de mot de passe, pas de photo), un parent donne son consentement pour chaque enfant, les enfants ne voient jamais de publicité et la seule discussion est celle de votre famille, que les parents peuvent désactiver. Chaque question est révisée avant d’être publiée. En anglais et en français, sur iPhone, iPad et Android.</p>
                </blockquote>
            </div>
        </div>
    </section>

    <section id="faq" class="alt" aria-labelledby="faq-title">
        <div class="wrap">
            <h2 id="faq-title">{{ $fr ? 'Questions fréquentes' : 'Frequently asked questions' }}</h2>
            <details id="faq-attribution" open>
                <summary>{{ $fr ? 'Comment une famille m’est-elle attribuée ?' : 'How does attribution work?' }}</summary>
                <p>{{ $fr
                    ? 'Une famille est liée au premier code de partenaire qu’elle utilise : le premier code l’emporte. Le code doit être utilisé avant le premier paiement de la famille; il est ensuite verrouillé. Nous n’utilisons ni témoins (cookies) ni pistage : une famille n’est attribuée que si votre code est réellement utilisé.'
                    : 'A family is linked to the first partner code it uses: the first code wins. The code must be used before the family’s first payment; after that it is locked. We use no cookies and no tracking, so a family is credited only when your code is actually used.' }}</p>
            </details>
            <details id="faq-store">
                <summary>{{ $fr ? 'Et les achats dans l’App Store ou Google Play ?' : 'What about App Store and Google Play purchases?' }}</summary>
                <p>{{ $fr
                    ? 'Ils comptent quand le parent a entré votre code dans l’application (à l’inscription ou sous « Vous avez un code ? ») avant de payer, ou quand il utilise un code d’offre de boutique lié à votre code.'
                    : 'They count when the parent entered your code in the app (at sign-up or under “Have a code?”) before paying, or redeemed a store offer code linked to your code.' }}
                    @if ($webCheckout)
                        {{ $fr ? 'Votre lien remplit aussi le code sur le formulaire d’abonnement du site.' : 'Your link also fills in the code on the website’s subscribe form.' }}
                    @endif
                </p>
            </details>
            <details id="faq-paid">
                <summary>{{ $fr ? 'Quand suis-je payé ?' : 'When do I get paid?' }}</summary>
                <p>{{ $fr ? 'Une commission devient payable après la période de remboursement' : 'A commission becomes payable after the refund window' }}@if ($terms['refund_days'] !== null) ({{ $terms['refund_days'] }} {{ $fr ? 'jours' : 'days' }})@endif{{ $fr ? ', puis elle est versée avec le paiement suivant' : ', then it is paid with the next payout' }}@if ($terms['schedule'] === 'monthly'){{ $fr ? ' (une fois par mois)' : ' (once a month)' }}@endif.</p>
            </details>
            <details id="faq-stats">
                <summary>{{ $fr ? 'Puis-je voir mes statistiques ?' : 'Can I see my stats?' }}</summary>
                <p>{{ $fr
                    ? 'Vous recevez un relevé mensuel par courriel (montants par mois et par devise). Une page de statistiques est prévue. Nous ne partageons jamais l’identité des familles.'
                    : 'You get a monthly statement by email (amounts per month and currency). A stats page is planned. We never share who the families are.' }}</p>
            </details>
            <details id="faq-refunds">
                <summary>{{ $fr ? 'Que se passe-t-il en cas de remboursement ?' : 'What happens with refunds?' }}</summary>
                <p>{{ $fr
                    ? 'Si une famille est remboursée ou fait une rétrofacturation, la commission de ce paiement est annulée et déduite de votre prochain paiement.'
                    : 'If a family gets a refund or a chargeback, the commission on that payment is reversed and deducted from your next payout.' }}</p>
            </details>
            <details id="faq-own">
                <summary>{{ $fr ? 'Puis-je utiliser mon propre code ?' : 'Can I use my own code?' }}</summary>
                <p>{{ $fr ? 'Non. Les auto-parrainages ne donnent pas droit à une commission.' : 'No. Self-referrals don’t earn commission.' }}</p>
            </details>
        </div>
    </section>

    <section id="apply" class="apply" aria-labelledby="apply-title">
        <div class="wrap">
            <h2 id="apply-title">{{ $fr ? 'Comment postuler' : 'How to apply' }}</h2>
            <div class="card">
                <p>{{ $fr ? 'Écrivez à' : 'Email' }} <a href="{{ $applyUrl }}">{{ $supportEmail }}</a> {{ $fr ? 'avec l’objet' : 'with the subject' }} <span class="mono">Partner application</span> {{ $fr ? 'et :' : 'and:' }}</p>
                <ul class="checks">
                    <li>{{ $fr ? 'les liens vers vos chaînes ou votre site;' : 'links to your channels or website;' }}</li>
                    <li>{{ $fr ? 'la taille de votre public (abonnés, visites);' : 'your audience size (followers, visits);' }}</li>
                    <li>{{ $fr ? 'votre pays (et la langue de votre public).' : 'your country (and your audience’s language).' }}</li>
                </ul>
                <p class="note mt">{{ $fr
                    ? 'Si votre demande est acceptée, nous créons votre compte de partenaire et votre code, et nous vous envoyons l’entente.'
                    : 'If we accept, we create your partner account and code and send you the agreement.' }}</p>
                <p><a class="cta" href="{{ $applyUrl }}">{{ $fr ? 'Postuler par courriel' : 'Apply by email' }}</a></p>
            </div>
        </div>
    </section>

    <section id="agreement" class="alt" aria-labelledby="agreement-title">
        <div class="wrap">
            <h2 id="agreement-title">{{ $fr ? 'Entente de partenariat — résumé' : 'Partner agreement — summary' }} <span class="draft">[DRAFT FOR LAWYER REVIEW]</span></h2>
            <p class="section-lead">{{ $fr
                ? 'Ébauche à titre d’information seulement; l’entente signée a priorité.'
                : 'A draft for information only; the signed agreement takes precedence.' }}</p>
            <ul>
                @foreach ($fr ? [
                    'Vous êtes un partenaire indépendant, pas un employé ni un mandataire de Brainy Clan. Le partenariat n’est pas exclusif.',
                    'Vous touchez la commission décrite ci-dessus (ou dans votre entente) sur les paiements des familles attribuées à votre code.',
                    'Vous respectez les règles de cette page, les lois sur la divulgation de la publicité et la protection des enfants, et nos règles de contenu.',
                    'Nous vous accordons une licence limitée et révocable d’utiliser le nom, le logo et la trousse de marque, seulement pour promouvoir Brainy Clan selon ces règles.',
                    'Nous ne partageons jamais les renseignements personnels des familles; vos relevés ne contiennent que des montants.',
                    'Vous êtes responsable de vos impôts, de vos taxes et de vos factures.',
                    'Chaque partie peut mettre fin au partenariat par courriel. Les commissions valides gagnées avant la fin sont versées normalement, sauf en cas de manquement aux règles.',
                    'Nous pouvons modifier ces conditions en vous avisant par courriel à l’avance.',
                    'Droit applicable et tribunaux compétents : [à compléter].',
                ] : [
                    'You are an independent partner, not an employee or agent of Brainy Clan. The partnership is non-exclusive.',
                    'You earn the commission described above (or in your agreement) on payments of families attributed to your code.',
                    'You follow the rules on this page, advertising disclosure and children’s privacy laws, and our content rules.',
                    'We grant you a limited, revocable licence to use the name, logo and brand kit only to promote Brainy Clan under these rules.',
                    'We never share families’ personal information; your statements contain amounts only.',
                    'You are responsible for your own taxes and invoices.',
                    'Either side may end the partnership by email. Valid commission earned before the end is paid as usual, unless the rules were broken.',
                    'We may change these terms with advance notice by email.',
                    'Governing law and courts: [to be completed].',
                ] as $clause)
                    <li>{{ $clause }}</li>
                @endforeach
            </ul>
        </div>
    </section>
@endsection
