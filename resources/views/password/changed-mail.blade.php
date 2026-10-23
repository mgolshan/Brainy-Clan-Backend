@if ($locale === 'fr')
Bonjour,

Le mot de passe de votre compte Brainy Clan vient d'être modifié avec un lien de réinitialisation. Tous vos appareils ont été déconnectés : ouvrez l'application et connectez-vous avec le nouveau mot de passe.

Si ce n'était pas vous, écrivez-nous tout de suite à {{ $supportEmail }}.

— Brainy Clan
@else
Hello,

The password of your Brainy Clan account was just changed with a reset link. All your devices were signed out: open the app and sign in with the new password.

If this wasn't you, email us right away at {{ $supportEmail }}.

— Brainy Clan
@endif
