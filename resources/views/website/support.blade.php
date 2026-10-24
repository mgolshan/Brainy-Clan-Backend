{{-- Support page (/support): FAQ, contact, links to account deletion and password reset. --}}
@php
    $fr = $locale === 'fr';
    $lang = ['lang' => $locale];
    $resetUrl = Route::has('password.reset') ? route('password.reset', $lang) : url('/password/reset').'?lang='.$locale;
@endphp
@extends('website.layout')

@section('backToTop', '1')
@section('title', $fr ? 'Aide — Brainy Clan' : 'Support — Brainy Clan')
@section('description', $fr
    ? 'Aide pour Brainy Clan : connexion d’un enfant sur un nouvel appareil, mot de passe oublié, suppression de compte et contact.'
    : 'Brainy Clan help: signing a child in on a new device, forgotten passwords, deleting an account and how to contact us.')

@section('content')
    <section aria-labelledby="support-title">
        <div class="wrap">
            <h1 id="support-title">{{ $fr ? 'Aide' : 'Support' }}</h1>
            <p class="lead">{{ $fr ? 'Une question ou un problème ? Écrivez-nous à' : 'A question or a problem? Email us at' }}
                <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.
                {{ $fr ? 'Nous répondons en français et en anglais.' : 'We answer in English and French.' }}</p>
            <p class="card" id="how-to-play">{{ $fr ? 'Comment ça marche ? Le guide des parents explique chaque écran en quelques étapes :' : 'How does something work? The parents’ guide explains every screen in a few steps:' }}
                <a href="{{ route('website.help', $lang) }}"><strong>{{ $fr ? 'Comment jouer' : 'How to play' }}</strong></a>.</p>
        </div>
    </section>

    <section aria-labelledby="faq-title">
        <div class="wrap">
            <h2 id="faq-title">{{ $fr ? 'Questions fréquentes' : 'Frequently asked questions' }}</h2>

            <details id="child-sign-in" open>
                <summary>{{ $fr ? 'Comment connecter mon enfant sur un nouvel appareil ?' : 'How do I sign my child in on a new device?' }}</summary>
                <p>{{ $fr
                    ? 'Les enfants n’ont ni courriel ni mot de passe. Un administrateur de la famille crée un code de connexion à usage unique :'
                    : 'Children have no email and no password. A family admin makes a one-time sign-in code:' }}</p>
                <ol>
                    <li>{{ $fr
                        ? 'Sur votre téléphone : onglet Famille → touchez le nom de l’enfant → « Se connecter sur un autre appareil » → « Créer un code de connexion ».'
                        : 'On your phone: Family tab → tap the child’s name → “Sign in on another device” → “Make a sign-in code”.' }}</li>
                    <li>{{ $fr
                        ? 'Sur l’appareil de l’enfant : ouvrez Brainy Clan et touchez « J’ai un code de connexion ».'
                        : 'On the child’s device: open Brainy Clan and tap “I have a sign-in code”.' }}</li>
                    <li>{{ $fr
                        ? 'Entrez les 8 lettres et chiffres. Le code fonctionne une seule fois, pendant '.$signInCodeMinutes.' minutes.'
                        : 'Type the 8 letters and numbers. The code works once, for '.$signInCodeMinutes.' minutes.' }}</li>
                </ol>
                <p>{{ $fr
                    ? 'Le même écran permet de déconnecter un appareil perdu.'
                    : 'The same screen lets you sign out a lost device.' }}</p>
            </details>

            <details id="password">
                <summary>{{ $fr ? 'J’ai oublié mon mot de passe' : 'I forgot my password' }}</summary>
                <p>{{ $fr
                    ? 'Dans l’application, touchez « Mot de passe oublié ? » sur l’écran de connexion et entrez votre courriel. Nous vous envoyons un lien vers la'
                    : 'In the app, tap “Forgot password?” on the sign-in screen and enter your email. We send you a link to the' }}
                    <a href="{{ $resetUrl }}">{{ $fr ? 'page de réinitialisation du mot de passe' : 'password reset page' }}</a>{{ $fr
                    ? ' (valide 60 minutes). Choisir un nouveau mot de passe déconnecte tous vos appareils.'
                    : ' (valid for 60 minutes). Choosing a new password signs you out on all your devices.' }}</p>
            </details>

            <details id="locked">
                <summary>{{ $fr ? 'Mon compte est temporairement bloqué' : 'My sign-in is temporarily locked' }}</summary>
                <p>{{ $fr
                    ? 'Après plusieurs essais ratés, la connexion est bloquée pendant un moment pour protéger votre compte. Attendez la fin du délai, réinitialisez votre mot de passe, connectez-vous avec Google, Apple ou Facebook si vous les avez liés, ou demandez un code de connexion à un administrateur de votre famille.'
                    : 'After several failed attempts, sign-in is locked for a while to protect your account. Wait for the timer, reset your password, sign in with Google, Apple or Facebook if you linked them, or ask a family admin for a sign-in code.' }}</p>
            </details>

            <details id="delete">
                <summary>{{ $fr ? 'Comment supprimer mon compte ou celui de mon enfant ?' : 'How do I delete my account or my child’s?' }}</summary>
                <p>{{ $fr
                    ? 'Dans l’application : Profil → Confidentialité → Supprimer le compte. Le compte d’un enfant est effacé quand un administrateur le retire de la famille. Sans l’application, utilisez la'
                    : 'In the app: Profile → Privacy → Delete account. A child’s account is erased when a family admin removes the child from the family. Without the app, use the' }}
                    <a href="{{ route('account.delete', $lang) }}">{{ $fr ? 'page de suppression de compte' : 'account deletion page' }}</a>.</p>
            </details>

            <details id="subscription">
                <summary>{{ $fr ? 'Comment gérer ou annuler le forfait Famille ?' : 'How do I manage or cancel the Family plan?' }}</summary>
                <p>{{ $fr
                    ? 'L’abonnement est géré par la boutique où il a été acheté : réglages d’abonnement de l’App Store (Apple) ou de Google Play. Si l’abonnement prend fin, votre famille garde ses données et repasse au forfait gratuit.'
                    : 'The subscription is managed by the store you bought it from: your App Store (Apple) or Google Play subscription settings. If it ends, your family keeps its data and goes back to the free plan.' }}</p>
            </details>

            <details id="privacy">
                <summary>{{ $fr ? 'Quelles données recueillez-vous ?' : 'What data do you collect?' }}</summary>
                <p>{{ $fr
                    ? 'Le minimum nécessaire. Pour un enfant : un surnom, un avatar, l’année et le mois de naissance. Tous les détails sont dans notre'
                    : 'Only what the app needs. For a child: a nickname, an avatar and a birth year and month. All the details are in our' }}
                    <a href="{{ route('legal.privacy', $lang) }}">{{ $fr ? 'politique de confidentialité' : 'privacy policy' }}</a>.</p>
            </details>
        </div>
    </section>

    <section aria-labelledby="contact-title">
        <div class="wrap">
            <h2 id="contact-title">{{ $fr ? 'Nous joindre' : 'Contact us' }}</h2>
            <p>{{ $fr ? 'Courriel :' : 'Email:' }} <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></p>
            <p class="note">{{ $fr
                ? 'Pour votre sécurité, n’envoyez jamais votre mot de passe par courriel.'
                : 'For your safety, never send your password by email.' }}</p>
            <p id="partners">{{ $fr
                ? 'Vous êtes un créateur ou un parent et vous aimeriez recommander Brainy Clan ? Voyez le'
                : 'A creator or a parent who would like to recommend Brainy Clan? See the' }}
                <a href="{{ route('website.partners', $lang) }}">{{ $fr ? 'programme partenaires' : 'partner program' }}</a>.</p>
        </div>
    </section>
@endsection
