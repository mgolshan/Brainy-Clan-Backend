{{-- PasswordChanged (Identity). Markdown: keep every line at column 0. --}}
@if ($locale === 'fr')
<x-mail::message reason="Vous recevez ce courriel parce que le mot de passe du compte Brainy Clan de cette adresse a changé.">
# Votre mot de passe a été modifié

Bonjour,

Le mot de passe de votre compte Brainy Clan vient d’être modifié avec un lien de réinitialisation. Tous vos appareils ont été déconnectés : ouvrez l’application et connectez-vous avec le nouveau mot de passe.

Si ce n’était pas vous, écrivez-nous tout de suite à [{{ $supportEmail }}](mailto:{{ $supportEmail }}).
</x-mail::message>
@else
<x-mail::message reason="You received this email because the password of the Brainy Clan account with this address changed.">
# Your password was changed

Hello,

The password of your Brainy Clan account was just changed with a reset link. All your devices were signed out: open the app and sign in with the new password.

If this wasn’t you, email us right away at [{{ $supportEmail }}](mailto:{{ $supportEmail }}).
</x-mail::message>
@endif
