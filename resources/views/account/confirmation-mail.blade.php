@if ($locale === 'fr')
Bonjour,

Nous avons reçu une demande de suppression du compte Brainy Clan associé à cette adresse courriel.

Pour confirmer, ouvrez ce lien (valide {{ $minutes }} minutes) puis appuyez sur « Supprimer mon compte » :
{{ $confirmUrl }}

Si vous n'avez pas fait cette demande, ignorez ce courriel : votre compte ne sera pas supprimé.

— Brainy Clan
@else
Hello,

We received a request to delete the Brainy Clan account that uses this email address.

To confirm, open this link (valid for {{ $minutes }} minutes) and press "Delete my account":
{{ $confirmUrl }}

If you did not ask for this, ignore this email: your account will not be deleted.

— Brainy Clan
@endif
