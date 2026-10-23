@php
    $fr = $locale === 'fr';
@endphp
@extends('legal.layout')

@section('title', $fr ? 'Nouveau mot de passe Brainy Clan' : 'New Brainy Clan password')

@section('content')
    <nav class="nav">
        <a href="{{ $otherLocaleUrl }}" hreflang="{{ $otherLocale }}" lang="{{ $otherLocale }}">{{ $fr ? 'English' : 'Français' }}</a>
    </nav>

    @if ($state === 'form')
        <p>{{ $fr
            ? 'Choisissez un nouveau mot de passe (au moins 8 caractères). Tous vos appareils seront ensuite déconnectés.'
            : 'Choose a new password (at least 8 characters). All your devices will then be signed out.' }}</p>
        <form method="post" action="{{ route('password.reset.submit', $fr ? ['lang' => 'fr'] : []) }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="password">{{ $fr ? 'Nouveau mot de passe' : 'New password' }}</label>
            <input id="password" name="password" type="password" required minlength="8" maxlength="255" autocomplete="new-password">
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror
            <label for="password_confirmation" class="spaced">{{ $fr ? 'Confirmez le mot de passe' : 'Confirm the password' }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" maxlength="255" autocomplete="new-password">
            <button type="submit" class="primary">{{ $fr ? 'Enregistrer le mot de passe' : 'Save the password' }}</button>
        </form>
    @elseif ($state === 'done')
        <p class="notice" role="status">{{ $fr
            ? 'Votre mot de passe a été changé et tous vos appareils ont été déconnectés. Ouvrez l’application Brainy Clan et connectez-vous avec le nouveau mot de passe.'
            : 'Your password was changed and all your devices were signed out. Open the Brainy Clan app and sign in with the new password.' }}</p>
    @else
        <p class="notice" role="status">{{ $fr
            ? 'Ce lien n’est plus valide (déjà utilisé, remplacé par un lien plus récent ou expiré après 60 minutes).'
            : 'This link is no longer valid (already used, replaced by a newer link or expired after 60 minutes).' }}</p>
        <p>{{ $fr
            ? 'Pour recevoir un nouveau lien, ouvrez l’application Brainy Clan et touchez « Mot de passe oublié ? » dans l’écran de connexion.'
            : 'To get a new link, open the Brainy Clan app and tap "Forgot password?" on the sign-in screen.' }}</p>
        <p>{{ $fr ? 'Une question ? Écrivez-nous à ' : 'Questions? Email us at ' }}<a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
    @endif
@endsection
