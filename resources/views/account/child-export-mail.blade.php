@if ($locale === 'fr')
Bonjour,

Comme demandé, voici en pièce jointe une copie de toutes les données que Brainy Clan conserve sur le profil « {{ $nickname }} » de votre famille (fichier JSON : profil, points, parties, objectifs, consentements, appareils et ses messages du clavardage familial).

Le fichier contient des renseignements personnels : conservez-le en lieu sûr.

Des questions ? Écrivez-nous à {{ $supportEmail }}.

— Brainy Clan
@else
Hello,

As requested, attached is a copy of all the data Brainy Clan holds about the profile "{{ $nickname }}" in your family (a JSON file: profile, points, games, goals, consents, devices and their family chat messages).

The file contains personal information: please keep it somewhere safe.

Questions? Email us at {{ $supportEmail }}.

— Brainy Clan
@endif
