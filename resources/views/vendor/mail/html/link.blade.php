{{-- Fallback for a button: the full URL as text, so it can be copied when the button does not work. --}}
@props(['url'])
<p class="link-fallback">{{ $slot }}<br>
<a href="{{ $url }}" class="break-all" target="_blank" rel="noopener">{{ $url }}</a></p>
