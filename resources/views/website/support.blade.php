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

@section('styles')
    form.contact { max-width: 640px; margin-top: 16px; }
    form.contact .field { margin-bottom: 16px; }
    form.contact label { display: block; font-weight: 700; margin-bottom: 4px; }
    form.contact input[type=text], form.contact input[type=email], form.contact select, form.contact textarea { width: 100%; padding: 10px 12px; font: inherit; color: var(--fg); background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
    form.contact textarea { resize: vertical; min-height: 160px; }
    form.contact input:focus-visible, form.contact select:focus-visible, form.contact textarea:focus-visible { outline: 3px solid var(--brand); outline-offset: 1px; }
    form.contact [aria-invalid=true] { border-color: #c81e1e; }
    form.contact .hint { color: var(--muted); font-size: 0.9rem; margin: 4px 0 0; }
    form.contact .error, .form-alert { color: #c81e1e; font-weight: 600; }
    @media (prefers-color-scheme: dark) { form.contact .error, .form-alert { color: #ff8f8f; } form.contact [aria-invalid=true] { border-color: #ff8f8f; } }
    form.contact .error { margin: 4px 0 0; }
    .form-alert { border-left: 4px solid currentColor; background: var(--surface); padding: 10px 14px; border-radius: 8px; }
    .form-alert ul { margin: 4px 0 0; }
    form.contact button { padding: 12px 22px; font: inherit; font-weight: 700; border: 0; border-radius: 999px; background: var(--brand); color: #1b2a4a; cursor: pointer; }
    form.contact button:focus-visible { outline: 3px solid var(--fg); outline-offset: 2px; }
    form.contact .note { margin-top: 12px; }
    .hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
    .card.sent { border-left: 4px solid var(--ok); }
    .card.sent p { margin-bottom: 8px; }
@endsection

@section('content')
    <section aria-labelledby="support-title">
        <div class="wrap">
            <h1 id="support-title">{{ $fr ? 'Aide' : 'Support' }}</h1>
            <p class="lead">{{ $fr ? 'Une question ou un problème ? Écrivez-nous avec le' : 'A question or a problem? Write to us with the' }}
                <a href="#contact">{{ $fr ? 'formulaire de contact' : 'contact form' }}</a>{{ $fr
                    ? ', ou dans l’application : Profil → Contacter l’assistance (administrateurs de la famille). Nous répondons par courriel, en français et en anglais.'
                    : ', or in the app: Profile → Contact support (family admins). We answer by email, in English and French.' }}</p>
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

    <section id="contact" aria-labelledby="contact-title">
        <div class="wrap">
            <h2 id="contact-title">{{ $fr ? 'Nous joindre' : 'Contact us' }}</h2>
            @if ($sent)
                <div class="card sent" role="status">
                    <h3>{{ $fr ? 'Merci, votre message est envoyé.' : 'Thank you, your message was sent.' }}</h3>
                    <p>{{ $fr
                        ? 'Nous répondons par courriel à l’adresse que vous avez donnée, en français ou en anglais. Pensez à vérifier vos courriels indésirables.'
                        : 'We will answer by email at the address you gave us, in English or French. Remember to check your spam folder.' }}</p>
                    <p><a href="{{ route('website.support', $lang) }}#contact">{{ $fr ? 'Envoyer un autre message' : 'Send another message' }}</a></p>
                </div>
            @else
                <p>{{ $fr
                    ? 'Écrivez-nous ici : nous répondons par courriel. Les administrateurs de la famille peuvent aussi nous écrire dans l’application : Profil → Contacter l’assistance.'
                    : 'Write to us here: we answer by email. Family admins can also write to us in the app: Profile → Contact support.' }}</p>

                @if ($formError === 'expired')
                    <p class="form-alert" role="alert">{{ $fr
                        ? 'Le formulaire a expiré parce que la page est restée ouverte longtemps. Votre texte est conservé ci-dessous : envoyez-le de nouveau.'
                        : 'The form expired because the page was open for a long time. Your text is kept below: please send it again.' }}</p>
                @elseif ($formError === 'rate_limited')
                    <p class="form-alert" role="alert">{{ $fr
                        ? 'Vous nous avez envoyé plusieurs messages récemment. Réessayez dans une heure; votre texte est conservé ci-dessous.'
                        : 'You sent us several messages recently. Please try again in an hour; your text is kept below.' }}</p>
                @elseif ($fieldErrors !== null)
                    <div class="form-alert" role="alert">
                        <p>{{ $fr ? 'Veuillez corriger ces champs :' : 'Please fix these fields:' }}</p>
                        <ul>
                            @foreach (['name', 'email', 'topic', 'message'] as $field)
                                @if ($fieldErrors->has($field))
                                    <li><a href="#contact-{{ $field }}">{{ $fieldErrors->first($field) }}</a></li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form class="contact" method="post" action="{{ route('website.support.submit', $lang) }}#contact">
                    @foreach (['name' => [$fr ? 'Votre nom' : 'Your name', 'text', 'name', 100], 'email' => [$fr ? 'Votre courriel' : 'Your email', 'email', 'email', 254]] as $field => [$label, $type, $autocomplete, $max])
                        @php($invalid = $fieldErrors !== null && $fieldErrors->has($field))
                        <div class="field">
                            <label for="contact-{{ $field }}">{{ $label }}</label>
                            <input id="contact-{{ $field }}" name="{{ $field }}" type="{{ $type }}" required maxlength="{{ $max }}" autocomplete="{{ $autocomplete }}" value="{{ $old[$field] }}"@if ($field === 'email') aria-describedby="contact-email-hint{{ $invalid ? ' contact-email-error' : '' }}"@elseif ($invalid) aria-describedby="contact-{{ $field }}-error"@endif @if ($invalid) aria-invalid="true"@endif>
                            @if ($field === 'email')
                                <p class="hint" id="contact-email-hint">{{ $fr ? 'Nous répondons à cette adresse.' : 'We answer at this address.' }}</p>
                            @endif
                            @if ($invalid)
                                <p class="error" id="contact-{{ $field }}-error">{{ $fieldErrors->first($field) }}</p>
                            @endif
                        </div>
                    @endforeach

                    @php($invalid = $fieldErrors !== null && $fieldErrors->has('topic'))
                    <div class="field">
                        <label for="contact-topic">{{ $fr ? 'Sujet' : 'Topic' }}</label>
                        <select id="contact-topic" name="topic" required @if ($invalid) aria-invalid="true" aria-describedby="contact-topic-error"@endif>
                            <option value="" @selected($old['topic'] === '')>{{ $fr ? 'Choisissez un sujet' : 'Choose a topic' }}</option>
                            @foreach ($topics as $key => $labels)
                                <option value="{{ $key }}" @selected($old['topic'] === $key)>{{ $labels[$locale] ?? $labels['en'] }}</option>
                            @endforeach
                        </select>
                        @if ($invalid)
                            <p class="error" id="contact-topic-error">{{ $fieldErrors->first('topic') }}</p>
                        @endif
                    </div>

                    @php($invalid = $fieldErrors !== null && $fieldErrors->has('message'))
                    <div class="field">
                        <label for="contact-message">{{ $fr ? 'Votre message' : 'Your message' }}</label>
                        <textarea id="contact-message" name="message" rows="7" required minlength="10" maxlength="5000" aria-describedby="contact-message-hint{{ $invalid ? ' contact-message-error' : '' }}" @if ($invalid) aria-invalid="true"@endif>{{ $old['message'] }}</textarea>
                        <p class="hint" id="contact-message-hint">{{ $fr ? 'De 10 à 5000 caractères. N’écrivez jamais de mot de passe.' : '10 to 5000 characters. Never include a password.' }}</p>
                        @if ($invalid)
                            <p class="error" id="contact-message-error">{{ $fieldErrors->first('message') }}</p>
                        @endif
                    </div>

                    <div class="hp" aria-hidden="true">
                        <label for="contact-website">{{ $fr ? 'Laissez ce champ vide' : 'Leave this field empty' }}</label>
                        <input id="contact-website" name="website" type="text" tabindex="-1" autocomplete="off">
                    </div>
                    <input type="hidden" name="form_token" value="{{ $formToken }}">

                    <button type="submit">{{ $fr ? 'Envoyer le message' : 'Send message' }}</button>
                    <p class="note">{{ $fr
                        ? 'Nous utilisons votre nom, votre courriel et votre message seulement pour vous répondre. Détails dans notre'
                        : 'We use your name, email and message only to answer you. Details in our' }}
                        <a href="{{ route('legal.privacy', $lang) }}#support-messages">{{ $fr ? 'politique de confidentialité' : 'privacy policy' }}</a>.</p>
                </form>
            @endif
            <p class="note">{{ $fr
                ? 'Pour votre sécurité, n’envoyez jamais votre mot de passe.'
                : 'For your safety, never send us your password.' }}</p>
            <p id="partners">{{ $fr
                ? 'Vous êtes un créateur ou un parent et vous aimeriez recommander Brainy Clan ? Voyez le'
                : 'A creator or a parent who would like to recommend Brainy Clan? See the' }}
                <a href="{{ route('website.partners', $lang) }}">{{ $fr ? 'programme partenaires' : 'partner program' }}</a>.</p>
        </div>
    </section>
@endsection
