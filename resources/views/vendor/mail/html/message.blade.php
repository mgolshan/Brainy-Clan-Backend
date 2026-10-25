{{--
    Brainy Clan email frame: owl + name header, the Markdown body, then a footer with why the
    reader got the mail (`reason`, or a generic line), the support address, the website and ©.
    Locale = the mailable's locale (Mailable::locale()), en or fr.
--}}
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
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="$websiteUrl">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ $reason }}

{{ $fr ? 'Des questions ?' : 'Questions?' }} [{{ $supportEmail }}](mailto:{{ $supportEmail }}) · [{{ \App\Modules\Core\Support\Brand::websiteLabel() }}]({{ $websiteUrl }})

© {{ date('Y') }} {{ config('app.name') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
