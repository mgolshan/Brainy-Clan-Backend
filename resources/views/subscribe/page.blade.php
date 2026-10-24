{{--
    Website web checkout pages (SubscribePageController): the subscribe form, "check your email",
    the emailed link's outcomes, Paddle's return page and the Paddle.js payment page. Same layout
    as the landing and support pages; no cookies except the CSRF session on the form.
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
@endphp
@extends('website.layout')

@section('title', $fr ? 'S’abonner au forfait Famille — Brainy Clan' : 'Subscribe to the Family plan — Brainy Clan')
@section('description', $fr
    ? 'Abonnez votre famille à Brainy Clan sur le Web : un forfait pour toute la famille, jusqu’à 7 membres.'
    : 'Subscribe your family to Brainy Clan on the web: one plan for the whole family, up to 7 members.')

@section('styles')
    .page { max-width: 760px; padding: 24px 0 56px; }
    .price-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin: 16px 0 8px; }
    .price { display: block; background: var(--surface); border: 2px solid var(--border); border-radius: 16px; padding: 16px; cursor: pointer; position: relative; }
    .price input { position: absolute; opacity: 0; }
    .price:has(input:checked) { border-color: var(--brand); }
    .price:has(input:focus-visible) { outline: 3px solid var(--brand); outline-offset: 2px; }
    .price .name { font-weight: 800; font-size: 1.1rem; }
    .price .amount { font-size: 1.5rem; font-weight: 800; margin: 4px 0; }
    .price .meta { color: var(--muted); font-size: .92rem; }
    .badge { display: inline-block; background: var(--brand); color: #fff; border-radius: 999px; padding: 2px 10px; font-size: .78rem; font-weight: 700; margin-left: 6px; vertical-align: middle; }
    label.field { display: block; font-weight: 700; margin: 16px 0 4px; }
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
            <h1>{{ $fr ? 'Le forfait Famille' : 'The Family plan' }}</h1>
            <p class="lead">{{ $fr
                ? 'Un seul abonnement pour toute la famille, jusqu’à 7 membres : toutes les catégories, des objectifs illimités, aucune publicité.'
                : 'One subscription for the whole family, up to 7 members: every category, unlimited goals, no ads.' }}</p>

            @if ($code !== null)
                <p class="notice">{{ $fr ? 'Code '.$code.' appliqué.' : 'Code '.$code.' applied.' }}</p>
            @endif

            <p class="currency">
                @foreach ($currencies as $currency)
                    @if ($currency === $cur)
                        <strong>{{ $currency }}</strong>
                    @else
                        <a href="{{ route('website.subscribe', [...$lang, 'currency' => $currency, 'plan' => $selectedPlan, ...($code !== null ? ['code' => $code] : []), ...($src !== null ? ['src' => $src] : [])]) }}">{{ $currency }}</a>
                    @endif
                    @if (! $loop->last) · @endif
                @endforeach
            </p>

            @if ($state === 'unavailable')
                <div class="price-grid">
                    @foreach ($pricing['plans'] as $plan)
                        <div class="price">
                            <span class="name">{{ $names[$plan['id']] ?? $plan['id'] }}</span>
                            <div class="amount">{{ $money($plan['price'], $cur) }}</div>
                        </div>
                    @endforeach
                </div>
                <p class="notice" role="status">{{ $fr
                    ? 'L’abonnement sur le Web arrive bientôt. En attendant, abonnez-vous dans l’application Brainy Clan (App Store ou Google Play).'
                    : 'Subscribing on the web is coming soon. Meanwhile, subscribe in the Brainy Clan app (App Store or Google Play).' }}</p>
            @else
                <form method="post" action="{{ route('website.subscribe.request', $lang) }}">
                    @csrf
                    <fieldset style="border:0;padding:0;margin:0">
                        <legend class="field" style="margin-bottom:0">{{ $fr ? 'Choisissez votre forfait' : 'Choose your plan' }}</legend>
                        <div class="price-grid">
                            @foreach ($pricing['plans'] as $plan)
                                <label class="price">
                                    <input type="radio" name="plan" value="{{ $plan['id'] }}" @checked(old('plan', $selectedPlan) === $plan['id'])>
                                    <span class="name">{{ $names[$plan['id']] ?? $plan['id'] }}</span>
                                    @if ($plan['highlighted'])
                                        <span class="badge">{{ $fr ? 'Meilleur choix' : 'Best value' }}</span>
                                    @endif
                                    <div class="amount">{{ $money($plan['price'], $cur) }}</div>
                                    <div class="meta">
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
                                    </div>
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
            @endif

            <p class="note">{{ $fr
                ? 'Le paiement est traité par Paddle.com, notre revendeur officiel (« merchant of record »), qui perçoit les taxes de vente applicables. Des taxes peuvent s’ajouter au paiement selon votre lieu de résidence. Le forfait se renouvelle automatiquement jusqu’à son annulation. Les achats sur le Web sont régis par les conditions d’achat de Paddle, en plus de nos'
                : 'Payment is handled by Paddle.com, our authorized reseller (merchant of record), which collects any applicable sales tax. Sales tax may be added at checkout depending on where you live. The plan renews automatically until cancelled. Web purchases are governed by Paddle’s buyer terms in addition to our' }}
                <a href="{{ route('legal.terms', $lang) }}">{{ $fr ? 'conditions d’utilisation' : 'terms of service' }}</a>.</p>
        @elseif ($state === 'sent')
            <h1>{{ $fr ? 'Vérifiez vos courriels' : 'Check your email' }}</h1>
            <p class="notice" role="status">{{ $fr
                ? 'Si cette adresse appartient à un compte Brainy Clan, nous venons d’y envoyer un courriel : soit votre lien de paiement (valide 30 minutes), soit la raison pour laquelle l’abonnement sur le Web n’est pas possible pour ce compte.'
                : 'If this address belongs to a Brainy Clan account, we just sent it an email: either your checkout link (valid for 30 minutes) or the reason subscribing on the web isn’t possible for that account.' }}</p>
            <p>{{ $fr ? 'Rien reçu ? Vérifiez vos courriels indésirables ou écrivez-nous à' : 'Nothing arrived? Check your spam folder or email us at' }} <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
        @elseif ($state === 'thanks')
            <h1>{{ $fr ? 'Merci !' : 'Thank you!' }}</h1>
            <p class="notice" role="status">{{ $fr
                ? 'Votre paiement est confirmé par Paddle. Le forfait Famille s’active dans l’application Brainy Clan d’ici une minute : ouvrez l’application et tirez pour actualiser au besoin. Votre reçu vient de Paddle.'
                : 'Paddle confirmed your payment. The Family plan turns on in the Brainy Clan app within a minute: open the app and pull to refresh if needed. Your receipt comes from Paddle.' }}</p>
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
