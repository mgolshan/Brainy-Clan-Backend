{{--
    SupportMessageReceived (Support), to the support inbox, English only. Markdown: keep every line
    at column 0. Everything people typed goes through the `details` / `user-text` mail components
    (escaped, one line): it can never become Markdown or HTML.
--}}
<x-mail::message reason="You received this email because this address is the Brainy Clan support inbox.">
# New support message #{{ $id }}

<x-mail::details :rows="$details" />

<x-mail::user-text :text="$body" />

<x-mail::button :url="$inboxUrl">
Open in the Support inbox
</x-mail::button>

Answer from the Support inbox so the reply is kept with the message and the status is updated. Replying to this email also reaches the sender, but is not recorded in the inbox.
</x-mail::message>
