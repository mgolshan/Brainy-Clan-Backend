{{-- Plain-text version of the Brainy Clan email frame (html/message.blade.php). --}}
@props(['reason' => null])
@php
    $fr = app()->getLocale() === 'fr';
    $supportEmail = (string) config('legal.support_email');
    $websiteUrl = \App\Modules\Core\Support\Brand::websiteUrl($fr ? 'fr' : 'en');
    $reason ??= $fr
        ? 'Vous recevez ce courriel parce que cette adresse a été utilisée avec '.config('app.name').'.'
        : 'You received this email because this address was used with '.config('app.name').'.';
@endphp
<x-mail::layout>
<x-slot:header>
{{ config('app.name') }}
</x-slot:header>

{{ $slot }}

@isset($subcopy)
<x-slot:subcopy>
{{ $subcopy }}
</x-slot:subcopy>
@endisset

<x-slot:footer>
--
{{ $reason }}
{{ $fr ? 'Des questions ?' : 'Questions?' }} {{ $supportEmail }} · {{ $websiteUrl }}
© {{ date('Y') }} {{ config('app.name') }}
</x-slot:footer>
</x-mail::layout>
