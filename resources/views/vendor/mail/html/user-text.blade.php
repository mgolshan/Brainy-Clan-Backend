{{-- Text typed by a person (App\Modules\Core\Support\MailText): escaped, one line, never Markdown. Use with a blank line before and after. --}}
@props(['text' => '', 'quote' => false])
<div class="user-text" style="margin: 0 0 16px; padding: 12px 16px; border-left: 4px solid {{ $quote ? '#d9cbb5' : '#ff8a3d' }}; background-color: #fffaf3; border-radius: 4px; color: {{ $quote ? '#5b6475' : '#1b2a4a' }}; font-size: 15px; line-height: 1.5; overflow-wrap: anywhere;">{!! \App\Modules\Core\Support\MailText::html($text) !!}</div>
