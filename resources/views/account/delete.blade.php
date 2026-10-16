@php
    $fr = $locale === 'fr';
@endphp
@extends('legal.layout')

@section('title', $fr ? 'Supprimer votre compte Brainy Clan' : 'Delete your Brainy Clan account')

@section('content')
    @if ($state === 'form' || $state === 'sent')
        <h2>{{ $fr ? 'Dans l’application' : 'In the app' }}</h2>
        <p>{{ $fr
            ? 'Ouvrez Brainy Clan, puis Profil → Confidentialité → Supprimer le compte. Si vous êtes propriétaire d’une famille comptant d’autres membres, supprimez d’abord la famille (les comptes des enfants sont alors effacés aussi).'
            : 'Open Brainy Clan, then Profile → Privacy → Delete account. If you own a family that has other members, delete the family first (the children’s accounts are then erased too).' }}</p>
        <p>{{ $fr
            ? 'Les enfants n’ont pas d’adresse courriel : un administrateur de la famille retire l’enfant de la famille, ce qui efface son compte.'
            : 'Children have no email address: a family admin removes the child from the family, which erases the child’s account.' }}</p>

        <h2>{{ $fr ? 'Sans l’application' : 'Without the app' }}</h2>
        @if ($state === 'sent')
            <p class="notice" role="status">{{ $fr
                ? 'Si un compte utilise cette adresse, nous venons d’y envoyer un lien de confirmation (valide 60 minutes). Ouvrez-le pour terminer la suppression.'
                : 'If an account uses this address, we just sent it a confirmation link (valid for 60 minutes). Open it to finish the deletion.' }}</p>
        @else
            <p>{{ $fr
                ? 'Entrez l’adresse courriel de votre compte. Nous y enverrons un lien pour confirmer la suppression.'
                : 'Enter the email address of your account. We will send a link to it to confirm the deletion.' }}</p>
            <form method="post" action="{{ route('account.delete.request', $fr ? ['lang' => 'fr'] : []) }}">
                @csrf
                <label for="email">{{ $fr ? 'Adresse courriel' : 'Email address' }}</label>
                <input id="email" name="email" type="email" required maxlength="255" autocomplete="email" value="{{ old('email') }}">
                @error('email')
                    <p class="error">{{ $message }}</p>
                @enderror
                <div class="hp" aria-hidden="true">
                    <label for="website">Website</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>
                <button type="submit">{{ $fr ? 'Envoyer le lien de confirmation' : 'Send the confirmation link' }}</button>
            </form>
        @endif

        <h2>{{ $fr ? 'Ce qui est supprimé' : 'What is deleted' }}</h2>
        <p>{{ $fr
            ? 'Votre nom, courriel, mot de passe, données de naissance, avatar, appareils, jetons de notification et comptes de connexion liés sont effacés immédiatement. Les points de jeu restent seulement sous forme anonyme (« Joueur supprimé ») pour que les pointages de la famille restent exacts. Les sauvegardes chiffrées sont effacées après 14 jours. Un abonnement actif doit être annulé dans l’App Store ou Google Play.'
            : 'Your name, email, password, birth data, avatar, devices, push tokens and linked sign-in accounts are erased immediately. Game points stay only in anonymous form ("Deleted player") so the family’s scores stay correct. Encrypted backups are erased after 14 days. An active subscription must be cancelled in the App Store or Google Play.' }}</p>
    @elseif ($state === 'confirm')
        <p>{{ $fr
            ? 'Appuyez sur le bouton pour supprimer définitivement votre compte. Cette action est irréversible.'
            : 'Press the button to permanently delete your account. This cannot be undone.' }}</p>
        <form method="post" action="{{ route('account.delete.confirm.submit', ['token' => $token, ...($fr ? ['lang' => 'fr'] : [])]) }}">
            @csrf
            <button type="submit">{{ $fr ? 'Supprimer mon compte' : 'Delete my account' }}</button>
        </form>
    @elseif ($state === 'deleted')
        <p class="notice" role="status">{{ $fr ? 'Votre compte a été supprimé.' : 'Your account has been deleted.' }}</p>
    @elseif ($state === 'family_owner')
        <p class="notice" role="status">{{ $fr
            ? 'Vous êtes propriétaire d’une famille qui compte d’autres membres. Supprimez la famille dans l’application (Profil → Confidentialité → Supprimer la famille) ou écrivez-nous à '.$contactEmail.'.'
            : 'You own a family that has other members. Delete the family in the app (Profile → Privacy → Delete family) or email us at '.$contactEmail.'.' }}</p>
    @elseif ($state === 'child_account')
        <p class="notice" role="status">{{ $fr
            ? 'Ce compte appartient à un jeune membre d’une famille : demandez à un administrateur de la famille de le retirer, ou écrivez-nous à '.$contactEmail.'.'
            : 'This account belongs to a young family member: ask a family admin to remove it, or email us at '.$contactEmail.'.' }}</p>
    @else
        <p class="notice" role="status">{{ $fr
            ? 'Ce lien n’est plus valide (déjà utilisé, remplacé ou expiré). Faites une nouvelle demande.'
            : 'This link is no longer valid (already used, replaced or expired). Please make a new request.' }}</p>
        <p><a href="{{ route('account.delete', $fr ? ['lang' => 'fr'] : []) }}">{{ $fr ? 'Nouvelle demande' : 'New request' }}</a></p>
    @endif
@endsection
