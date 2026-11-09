{{-- Plain-text version of the details mail component. --}}
@props(['rows' => []])
@foreach ($rows as $label => $value)
{{ $label }}: {{ $value }}
@endforeach
