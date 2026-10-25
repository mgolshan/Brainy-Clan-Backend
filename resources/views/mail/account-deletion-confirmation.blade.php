{{-- AccountDeletionConfirmation (Family). Markdown: keep every line at column 0. --}}
@if ($locale === 'fr')
<x-mail::message reason="Vous recevez ce courriel parce que la suppression du compte de cette adresse a été demandée sur notre site.">
# Confirmez la suppression de votre compte

Bonjour,

Nous avons reçu une demande de suppression du compte Brainy Clan associé à cette adresse courriel.

Pour confirmer, ouvrez la page de confirmation (lien valide {{ $minutes }} minutes) puis appuyez sur « Supprimer mon compte ».

<x-mail::button :url="$confirmUrl">
Ouvrir la page de confirmation
</x-mail::button>

Si vous n’avez pas fait cette demande, ignorez ce courriel : votre compte ne sera pas supprimé.

<x-mail::link :url="$confirmUrl">
Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :
</x-mail::link>
</x-mail::message>
@else
<x-mail::message reason="You received this email because the deletion of the account with this address was requested on our website.">
# Confirm the deletion of your account

Hello,

We received a request to delete the Brainy Clan account that uses this email address.

To confirm, open the confirmation page (link valid for {{ $minutes }} minutes) and press "Delete my account".

<x-mail::button :url="$confirmUrl">
Open the confirmation page
</x-mail::button>

If you did not ask for this, ignore this email: your account will not be deleted.

<x-mail::link :url="$confirmUrl">
If the button doesn’t work, copy this link into your browser:
</x-mail::link>
</x-mail::message>
@endif
