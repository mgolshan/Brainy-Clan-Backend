{{--
    ChildDataExport (Family). Markdown: keep every line at column 0. The nickname is family-chosen
    text, so its paragraph is a raw HTML block (<p>…</p>): escaped by Blade, never read as Markdown.
--}}
@if ($locale === 'fr')
<x-mail::message reason="Vous recevez ce courriel parce que vous êtes le propriétaire de cette famille et avez demandé une copie des données.">
# Copie des données d’un profil

Bonjour,

<p>Comme demandé, voici en pièce jointe une copie de toutes les données que Brainy Clan conserve sur le profil « {{ $nickname }} » de votre famille (fichier JSON : profil, points, parties, objectifs, consentements, appareils et ses messages du clavardage familial).</p>

**Le fichier contient des renseignements personnels : conservez-le en lieu sûr.**

Des questions ? Écrivez-nous à [{{ $supportEmail }}](mailto:{{ $supportEmail }}).
</x-mail::message>
@else
<x-mail::message reason="You received this email because you own this family and asked for a copy of the data.">
# A copy of a profile’s data

Hello,

<p>As requested, attached is a copy of all the data Brainy Clan holds about the profile "{{ $nickname }}" in your family (a JSON file: profile, points, games, goals, consents, devices and their family chat messages).</p>

**The file contains personal information: please keep it somewhere safe.**

Questions? Email us at [{{ $supportEmail }}](mailto:{{ $supportEmail }}).
</x-mail::message>
@endif
