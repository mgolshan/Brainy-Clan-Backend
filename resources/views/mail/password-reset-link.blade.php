{{-- PasswordResetLink (Identity). Markdown: keep every line at column 0 (indent = code block). --}}
@if ($locale === 'fr')
<x-mail::message reason="Vous recevez ce courriel parce qu’une réinitialisation du mot de passe a été demandée pour cette adresse.">
# Choisissez un nouveau mot de passe

Bonjour,

Pour choisir un nouveau mot de passe pour votre compte Brainy Clan, utilisez ce bouton (valide {{ $minutes }} minutes, une seule fois).

<x-mail::button :url="$resetUrl">
Choisir un nouveau mot de passe
</x-mail::button>

Une fois le mot de passe changé, tous vos appareils sont déconnectés : ouvrez l’application et connectez-vous avec le nouveau mot de passe.

Si vous n’avez pas fait cette demande, ignorez ce courriel : votre mot de passe ne change pas.

<x-mail::link :url="$resetUrl">
Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :
</x-mail::link>
</x-mail::message>
@else
<x-mail::message reason="You received this email because a password reset was requested for this address.">
# Choose a new password

Hello,

To choose a new password for your Brainy Clan account, use this button (valid for {{ $minutes }} minutes, works once).

<x-mail::button :url="$resetUrl">
Choose a new password
</x-mail::button>

Once the password is changed, all your devices are signed out: open the app and sign in with the new password.

If you did not ask for this, ignore this email: your password stays the same.

<x-mail::link :url="$resetUrl">
If the button doesn’t work, copy this link into your browser:
</x-mail::link>
</x-mail::message>
@endif
