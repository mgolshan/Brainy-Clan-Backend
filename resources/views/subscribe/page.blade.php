{{--
    Website web checkout pages (SubscribePageController): the subscribe form (plan tier + billing
    period pickers), "check your email", the emailed link's outcomes, Paddle's return page and the
    Paddle.js payment page. Same layout as the landing and support pages; no cookies except the
    CSRF session on the form, no scripts (the per-tier prices follow the tier picker with :has()).
--}}
@php
    $fr = $locale === 'fr';
    $lang = $fr ? ['lang' => 'fr'] : [];
    $names = [
        'annual' => $fr ? 'Annuel' : 'Annual',
        'semiannual' => $fr ? '6 mois' : '6 months',
        'monthly' => $fr ? 'Mensuel' : 'Monthly',
    ];
    $symbol = fn (string $currency): string => match ($currency) { 'USD' => 'US$', 'CAD' => 'CA$', default => $currency.' ' };
    $money = fn (string $amount, string $currency): string => $fr
        ? str_replace('.', ',', $amount).' '.rtrim($symbol($currency))
        : $symbol($currency).$amount;
    $cur = $pricing['currency'];
    /** Paid tiers (rank order) with their display prices and whether web checkout is open for them. */
    $tiers = collect($pricing['tiers'])->keyBy('id');
    $available = $tiers->filter(fn (array $tier): bool => $tier['available'])->keys()->all();
    $tierName = fn (string $tier): string => \App\Modules\Family\Models\Family::tierLabel($tier, $fr ? 'fr' : 'en');
    $chosenTier = old('tier', $selectedTier);
    $chosenTier = in_array($chosenTier, $available, true) ? $chosenTier : ($available[0] ?? 'family');
    $bestFor = [
        'family' => $fr ? 'Parents et enfants' : 'Parents and kids',
        'big_clan' => $fr ? 'Grands-parents, cousins et groupes d’école-maison' : 'Grandparents, cousins and homeschool groups',
        'classroom' => $fr ? 'Enseignants, tuteurs et entraîneurs' : 'Teachers, tutors and coaches',
    ];
    $monthlyOf = fn (array $tier): ?array => collect($tier['plans'])->firstWhere('id', 'monthly');
    $periods = collect($tiers->first()['plans'] ?? [])->pluck('id')->all();
    $pageQuery = fn (array $extra = []): array => [...$lang, 'currency' => $cur, 'tier' => $chosenTier, 'plan' => $selectedPlan, ...($code !== null ? ['code' => $code] : []), ...($src !== null ? ['src' => $src] : []), ...$extra];
@endphp
@extends('website.layout')

@section('title', $fr ? 'S’abonner à Brainy Clan — Forfaits' : 'Subscribe to Brainy Clan — Plans')
@section('description', $fr
    ? 'Abonnez-vous à Brainy Clan sur le Web : Famille (jusqu’à '.$members['family'].' membres), Grand Clan (jusqu’à '.$members['big_clan'].') ou Classe (jusqu’à '.$members['classroom'].').'
    : 'Subscribe to Brainy Clan on the web: Family (up to '.$members['family'].' members), Big Clan (up to '.$members['big_clan'].') or Classroom (up to '.$members['classroom'].').')

@section('styles')
    .page { max-width: 820px; padding: 24px 0 56px; }
    fieldset { border: 0; padding: 0; margin: 0; }
    legend.field { margin-bottom: 0; }
    .tier-grid, .price-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin: 16px 0 8px; }
    .tier, .price { display: flex; flex-direction: column; gap: 2px; background: var(--surface); border: 2px solid var(--border); border-radius: 16px; padding: 16px; cursor: pointer; position: relative; }
    .tier input, .price input { position: absolute; opacity: 0; }
    .tier:has(input:checked), .price:has(input:checked) { border-color: var(--brand); box-shadow: 0 6px 18px rgba(27, 42, 74, .12); }
    .tier:has(input:focus-visible), .price:has(input:focus-visible) { outline: 3px solid var(--brand); outline-offset: 2px; }
    .tier.soon { cursor: default; opacity: .72; }
    .tier .name, .price .name { font-weight: 800; font-size: 1.1rem; }
    .tier .meta, .price .meta { color: var(--muted); font-size: .92rem; }
    .tier .from { font-weight: 700; margin-top: 6px; }
    .tier-prices { margin: 8px 0 0; padding-left: 1.1em; }
    .price .amount { display: block; font-size: 1.5rem; font-weight: 800; margin: 4px 0; }
    .badge { display: inline-block; align-self: flex-start; background: var(--brand); color: #fff; border-radius: 999px; padding: 2px 10px; font-size: .78rem; font-weight: 700; }
    .badge.soon-badge { background: var(--muted); }
    .for-tier { display: block; margin-top: 8px; }
    .for-tier .tier-label { display: block; font-weight: 700; font-size: .85rem; color: var(--muted); }
    /* With :has(), each period card shows only the chosen plan's price; without it every plan's price is listed. */
    @supports selector(:has(*)) {
        .subscribe-form .for-tier, .subscribe-form .for-tier .tier-label { display: none; }
        .subscribe-form:has(input[name=tier][value=family]:checked) .for-tier[data-tier=family],
        .subscribe-form:has(input[name=tier][value=big_clan]:checked) .for-tier[data-tier=big_clan],
        .subscribe-form:has(input[name=tier][value=classroom]:checked) .for-tier[data-tier=classroom] { display: block; }
    }
    label.field, legend.field { display: block; font-weight: 700; margin: 16px 0 4px; }
    input[type=email], input[type=text] { width: 100%; max-width: 420px; padding: 11px 12px; font-size: 1rem; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); color: var(--fg); }
    button.primary { margin-top: 18px; padding: 12px 22px; font-size: 1.05rem; font-weight: 700; border: 0; border-radius: 12px; background: var(--brand); color: #fff; cursor: pointer; }
    .hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
    .notice { border-left: 4px solid var(--brand); background: var(--surface); padding: 12px 16px; border-radius: 8px; margin: 16px 0; }
    .error { color: #c81e1e; margin: 4px 0 0; }
    .currency { font-size: .95rem; }
@endsection

@section('content')
    <div class="wrap page">
        @if ($state === 'form' || $state === 'unavailable')
            <h1>{{ $fr ? 'Choisissez votre forfait Brainy Clan' : 'Choose your Brainy Clan plan' }}</h1>
            <p class="lead">{{ $fr
                ? 'Un seul abonnement pour tout le groupe : toutes les catégories, des objectifs illimités, aucune publicité. Choisissez le forfait selon le nombre de membres.'
                : 'One subscription for the whole group: every category, unlimited goals, no ads. Pick the plan by how many members you need.' }}</p>

            @if ($code !== null)
                <p class="notice">{{ $fr ? 'Code '.$code.' appliqué.' : 'Code '.$code.' applied.' }}</p>
            @endif

            <p class="currency">
                @foreach ($currencies as $currency)
                    @if ($currency === $cur)
                        <strong>{{ $currency }}</strong>
                    @else
                        <a href="{{ route('website.subscribe', $pageQuery(['currency' => $currency])) }}">{{ $currency }}</a>
                    @endif
                    @if (! $loop->last) · @endif
                @endforeach
            </p>

            @if ($state === 'unavailable')
                <div class="tier-grid">
                    @foreach ($tiers as $id => $tier)
                        <div class="tier soon" id="tier-{{ $id }}">
                            <span class="name">{{ $tierName($id) }}</span>
                            <span class="meta">{{ $fr ? 'Jusqu’à '.$tier['members'].' membres' : 'Up to '.$tier['members'].' members' }}</span>
                            <span class="meta">{{ $bestFor[$id] ?? '' }}</span>
                            <ul class="tier-prices">
                                @foreach ($tier['plans'] as $plan)
                                    <li>{{ $names[$plan['id']] ?? $plan['id'] }}{{ $fr ? ' : ' : ': ' }}<strong>{{ $money($plan['price'], $cur) }}</strong></li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
                <p class="notice" role="status">{{ $fr
                    ? 'L’abonnement sur le Web arrive bientôt. En attendant, abonnez-vous dans l’application Brainy Clan (App Store ou Google Play).'
                    : 'Subscribing on the web is coming soon. Meanwhile, subscribe in the Brainy Clan app (App Store or Google Play).' }}</p>
            @else
                <form method="post" action="{{ route('website.subscribe.request', $lang) }}" class="subscribe-form">
                    @csrf
                    <fieldset>
                        <legend class="field">{{ $fr ? '1. Choisissez votre forfait' : '1. Choose your plan' }}</legend>
                        <div class="tier-grid">
                            @foreach ($tiers as $id => $tier)
                                @php $monthly = $monthlyOf($tier); @endphp
                                <label class="tier{{ $tier['available'] ? '' : ' soon' }}" id="tier-{{ $id }}">
                                    <input type="radio" name="tier" value="{{ $id }}" @checked($tier['available'] && $chosenTier === $id) @disabled(! $tier['available'])>
                                    <span class="name">{{ $tierName($id) }}</span>
                                    @if (! $tier['available'])
                                        <span class="badge soon-badge">{{ $fr ? 'Bientôt sur le Web' : 'Coming soon on the web' }}</span>
                                    @elseif ($tier['highlighted'])
                                        <span class="badge">{{ $fr ? 'Le plus populaire' : 'Most popular' }}</span>
                                    @endif
                                    <span class="meta">{{ $fr ? 'Jusqu’à '.$tier['members'].' membres' : 'Up to '.$tier['members'].' members' }}</span>
                                    <span class="meta">{{ $bestFor[$id] ?? '' }}</span>
                                    @if ($monthly !== null)
                                        <span class="from">{{ $fr ? 'Dès '.$money($monthly['price'], $cur).' / mois' : 'From '.$money($monthly['price'], $cur).' / month' }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @error('tier')
                        <p class="error">{{ $message }}</p>
                    @enderror

                    <fieldset>
                        <legend class="field">{{ $fr ? '2. Choisissez la durée' : '2. Choose how you pay' }}</legend>
                        <div class="price-grid">
                            @foreach ($periods as $planId)
                                <label class="price">
                                    <input type="radio" name="plan" value="{{ $planId }}" @checked(old('plan', $selectedPlan) === $planId)>
                                    <span class="name">{{ $names[$planId] ?? $planId }}</span>
                                    @foreach ($tiers as $id => $tier)
                                        @php $plan = collect($tier['plans'])->firstWhere('id', $planId); @endphp
                                        @if ($plan !== null && $tier['available'])
                                            <span class="for-tier" data-tier="{{ $id }}">
                                                <span class="tier-label">{{ $tierName($id) }}</span>
                                                @if ($plan['highlighted'])
                                                    <span class="badge">{{ $fr ? 'Meilleur choix' : 'Best value' }}</span>
                                                @endif
                                                <span class="amount">{{ $money($plan['price'], $cur) }}</span>
                                                <span class="meta">
                                                    @if ($plan['id'] !== 'monthly')
                                                        {{ $money($plan['per_month'], $cur) }} {{ $fr ? '/ mois' : '/ month' }}
                                                        @if ($plan['saving_percent'] > 0)
                                                            · {{ $fr ? 'économisez '.$plan['saving_percent'].' %' : 'save '.$plan['saving_percent'].'%' }}
                                                        @endif
                                                        <br>
                                                    @endif
                                                    @if ($plan['trial_days'] > 0)
                                                        {{ $fr ? $plan['trial_days'].' jours d’essai gratuit' : $plan['trial_days'].'-day free trial' }}
                                                    @else
                                                        {{ $fr ? 'Sans engagement' : 'Cancel anytime' }}
                                                    @endif
                                                </span>
                                            </span>
                                        @endif
                                    @endforeach
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    @error('plan')
                        <p class="error">{{ $message }}</p>
                    @enderror

                    <label class="field" for="email">{{ $fr ? 'Adresse courriel de votre compte Brainy Clan' : 'Email address of your Brainy Clan account' }}</label>
                    <input id="email" name="email" type="email" required maxlength="255" autocomplete="email" value="{{ old('email') }}">
                    @error('email')
                        <p class="error">{{ $message }}</p>
                    @enderror

                    <label class="field" for="code">{{ $fr ? 'Code de parrainage (facultatif)' : 'Referral code (optional)' }}</label>
                    <input id="code" name="code" type="text" maxlength="20" autocomplete="off" autocapitalize="characters" value="{{ old('code', $code) }}">
                    @error('code')
                        <p class="error">{{ $message }}</p>
                    @enderror

                    @if ($src !== null)
                        <input type="hidden" name="src" value="{{ $src }}">
                    @endif
                    <div class="hp" aria-hidden="true">
                        <label for="website">Website</label>
                        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                    </div>
                    <button type="submit" class="primary">{{ $fr ? 'Recevoir mon lien de paiement' : 'Email me my checkout link' }}</button>
                </form>
                <p class="note">{{ $fr
                    ? 'Pas de mot de passe ici : nous envoyons un lien de paiement unique (valide 30 minutes) à l’adresse de votre compte. Seul un administrateur adulte de la famille peut s’abonner.'
                    : 'No password here: we email a one-time checkout link (valid for 30 minutes) to your account’s address. Only an adult admin of the family can subscribe.' }}</p>
                <p class="note">{{ $fr
                    ? 'Pour changer de forfait après un achat sur le Web : annulez-le dans le portail client de Paddle, puis abonnez-vous au forfait voulu.'
                    : 'To switch plans after buying on the web: cancel in the Paddle customer portal, then subscribe to the plan you want.' }}</p>
            @endif

            <p class="note">{{ $fr
                ? 'Payez par carte ou avec PayPal. Le paiement est traité par Paddle.com, notre revendeur officiel (« merchant of record »), qui perçoit les taxes de vente applicables. Des taxes peuvent s’ajouter au paiement selon votre lieu de résidence. Le forfait se renouvelle automatiquement jusqu’à son annulation. Les achats sur le Web sont régis par les conditions d’achat de Paddle, en plus de nos'
                : 'Pay by card or with PayPal. Payment is handled by Paddle.com, our authorized reseller (merchant of record), which collects any applicable sales tax. Sales tax may be added at checkout depending on where you live. The plan renews automatically until cancelled. Web purchases are governed by Paddle’s buyer terms in addition to our' }}
                <a href="{{ route('legal.terms', $lang) }}">{{ $fr ? 'conditions d’utilisation' : 'terms of service' }}</a>.</p>
        @elseif ($state === 'sent')
            <h1>{{ $fr ? 'Vérifiez vos courriels' : 'Check your email' }}</h1>
            <p class="notice" role="status">{{ $fr
                ? 'Si cette adresse appartient à un compte Brainy Clan, nous venons d’y envoyer un courriel : soit votre lien de paiement (valide 30 minutes), soit la raison pour laquelle l’abonnement sur le Web n’est pas possible pour ce compte.'
                : 'If this address belongs to a Brainy Clan account, we just sent it an email: either your checkout link (valid for 30 minutes) or the reason subscribing on the web isn’t possible for that account.' }}</p>
            <p>{{ $fr ? 'Rien reçu ? Vérifiez vos courriels indésirables ou' : 'Nothing arrived? Check your spam folder or' }} <a href="{{ \App\Modules\Core\Support\Website::contactUrl($locale, 'billing') }}">{{ $fr ? 'écrivez-nous' : 'write to us' }}</a>.</p>
        @elseif ($state === 'thanks')
            <h1>{{ $fr ? 'Merci !' : 'Thank you!' }}</h1>
            <p class="notice" role="status">{{ $fr
                ? 'Votre paiement est confirmé par Paddle. Votre forfait s’active dans l’application Brainy Clan d’ici une minute : ouvrez l’application et tirez pour actualiser au besoin. Votre reçu vient de Paddle.'
                : 'Paddle confirmed your payment. Your plan turns on in the Brainy Clan app within a minute: open the app and pull to refresh if needed. Your receipt comes from Paddle.' }}</p>
        @elseif ($state === 'pay')
            <h1>{{ $fr ? 'Paiement sécurisé' : 'Secure checkout' }}</h1>
            <p>{{ $fr ? 'Le formulaire de paiement de Paddle s’ouvre…' : 'Paddle’s checkout is opening…' }}</p>
            <div id="paddle-checkout" data-paddle-token="{{ $clientToken }}" data-paddle-environment="{{ $paddleEnvironment }}" data-locale="{{ $locale }}" data-success-url="{{ $successUrl }}"></div>
            <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
            <script src="/js/paddle-checkout.js"></script>
        @elseif ($state === 'ineligible')
            <h1>{{ $fr ? 'Abonnement impossible pour le moment' : 'Can’t subscribe right now' }}</h1>
            <p class="notice" role="status">{{ match ($reason) {
                'store_plan_active' => $fr ? 'Votre famille a déjà un abonnement acheté dans l’App Store ou Google Play. Gérez-le à cet endroit.' : 'Your family already has a subscription bought in the App Store or Google Play. Manage it there.',
                'web_plan_active' => $fr ? 'Votre famille a déjà un abonnement actif acheté sur le Web. Merci !' : 'Your family already has an active subscription bought on the web. Thank you!',
                'owner_too_young' => $fr ? 'Le forfait Classe demande un propriétaire de famille d’au moins '.$classroomOwnerMinAge.' ans.' : 'A Classroom needs a family owner who is at least '.$classroomOwnerMinAge.'.',
                default => $fr ? 'Seul un administrateur adulte de la famille peut s’abonner.' : 'Only an adult admin of the family can subscribe.',
            } }}</p>
        @elseif ($state === 'error')
            <h1>{{ $fr ? 'Un problème est survenu' : 'Something went wrong' }}</h1>
            <p class="notice" role="status">{{ $fr
                ? 'Nous n’avons pas pu ouvrir le paiement. Réessayez dans un instant avec le même lien.'
                : 'We could not open the checkout. Please try again in a moment with the same link.' }}</p>
        @else
            <h1>{{ $fr ? 'Lien expiré' : 'Link expired' }}</h1>
            <p class="notice" role="status">{{ $fr
                ? 'Ce lien n’est plus valide (expiré ou remplacé par une nouvelle demande).'
                : 'This link is no longer valid (expired or replaced by a newer request).' }}</p>
            <p><a href="{{ route('website.subscribe', $lang) }}">{{ $fr ? 'Demander un nouveau lien' : 'Ask for a new link' }}</a></p>
        @endif
    </div>
@endsection
