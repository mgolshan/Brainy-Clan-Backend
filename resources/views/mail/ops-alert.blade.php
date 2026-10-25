{{--
    OpsAlertMail (Core): staff-only operational alert, English, kept plain inside the same frame.
    The body is plain text (class names, queues, counts); each line is escaped into a raw HTML
    paragraph so backslashes in class names are never read as Markdown.
--}}
<x-mail::message reason="You received this alert because this address is the ops alert address (OPS_ALERT_EMAIL).">
# {{ $title }}

<p class="ops-alert">
@foreach ($lines as $line)
{{ $line }}<br>
@endforeach
</p>
</x-mail::message>
