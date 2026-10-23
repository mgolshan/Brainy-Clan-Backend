@if ($locale === 'fr')
Bonjour,

Pour choisir un nouveau mot de passe pour votre compte Brainy Clan, ouvrez ce lien (valide {{ $minutes }} minutes, une seule fois) :
{{ $resetUrl }}

Une fois le mot de passe changé, tous vos appareils sont déconnectés : ouvrez l'application et connectez-vous avec le nouveau mot de passe.

Si vous n'avez pas fait cette demande, ignorez ce courriel : votre mot de passe ne change pas.

Une question ? Écrivez-nous à {{ $supportEmail }}.

— Brainy Clan
@else
Hello,

To choose a new password for your Brainy Clan account, open this link (valid for {{ $minutes }} minutes, works once):
{{ $resetUrl }}

Once the password is changed, all your devices are signed out: open the app and sign in with the new password.

If you did not ask for this, ignore this email: your password stays the same.

Questions? Email us at {{ $supportEmail }}.

— Brainy Clan
@endif
