{{--
    SupportReplyMail (Support), to the person who wrote to support, en/fr. Markdown: keep every line
    at column 0. The staff reply and the original message go through the `user-text` mail component
    (escaped, one line): they can never become Markdown or HTML.
--}}
@if ($locale === 'fr')
<x-mail::message reason="Vous recevez ce courriel parce que vous avez écrit à l’assistance de Brainy Clan.">
# Réponse de l’assistance Brainy Clan

Bonjour,

<x-mail::user-text :text="$replyBody" />

Pour nous répondre, répondez simplement à ce courriel. Pour votre sécurité, n’envoyez jamais votre mot de passe.

**Votre message du {{ $sentOn }} :**

<x-mail::user-text :text="$original" :quote="true" />
</x-mail::message>
@else
<x-mail::message reason="You received this email because you wrote to Brainy Clan support.">
# A reply from Brainy Clan support

Hello,

<x-mail::user-text :text="$replyBody" />

To answer, simply reply to this email. For your safety, never send us your password.

**Your message of {{ $sentOn }}:**

<x-mail::user-text :text="$original" :quote="true" />
</x-mail::message>
@endif
