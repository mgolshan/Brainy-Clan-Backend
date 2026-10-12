{{--
    Phone-style rendering of a content item exactly as the app receives it (shared by the content
    preview page and the AI batch review). Needs: $preview (PreviewContentItem result), $record
    (ContentItem), optional $compact (smaller, for review lists).
--}}
@php
    $themes = [
        'playful' => ['bg' => '#FFF7E6', 'card' => '#FFFFFF', 'accent' => '#FF8A00', 'font' => '1.1rem', 'radius' => '22px'],
        'sleek' => ['bg' => '#0F172A', 'card' => '#1E293B', 'accent' => '#38BDF8', 'font' => '1rem', 'radius' => '12px'],
        'modern' => ['bg' => '#F4F4F8', 'card' => '#FFFFFF', 'accent' => '#4F46E5', 'font' => '1rem', 'radius' => '16px'],
        'classic' => ['bg' => '#FBF8F1', 'card' => '#FFFFFF', 'accent' => '#1E3A5F', 'font' => '1.2rem', 'radius' => '8px'],
    ];
    $compact ??= false;
    $t = $themes[$preview['ui_theme']] ?? $themes['modern'];
    $dark = $preview['ui_theme'] === 'sleek';
@endphp
<div style="background:{{ $t['bg'] }}; color:{{ $dark ? '#E2E8F0' : '#111827' }}; border-radius:{{ $compact ? '16px' : '28px' }}; padding:{{ $compact ? '.75rem' : '1.25rem' }}; border:{{ $compact ? '3px' : '8px' }} solid #111827; min-height:{{ $compact ? '0' : '520px' }}; font-size:{{ $compact ? '.9rem' : $t['font'] }};">
    <div style="font-size:.75rem; opacity:.7; margin-bottom:.75rem;">
        {{ $record->id ? '#'.$record->id : 'generated sample' }} · {{ $preview['type'] }}@if (isset($preview['payload']['variant'])) ({{ $preview['payload']['variant'] }})@endif · {{ $preview['points'] }} {{ $preview['points'] === 1 ? 'point' : 'points' }} · {{ $preview['ui_theme'] }} theme
    </div>

    @if (isset($preview['payload']['card']))
        <div style="background:{{ $t['card'] }}; border-radius:{{ $t['radius'] }}; padding:1rem; margin-bottom:1rem; border-left:4px solid {{ $t['accent'] }};">
            <div style="font-style:italic;">“{{ $preview['payload']['card']['text'] }}”</div>
            @if ($preview['payload']['card']['author'])
                <div style="margin-top:.5rem; font-size:.85em; opacity:.8;">— {{ $preview['payload']['card']['author'] }}@if ($preview['payload']['card']['source']), <em>{{ $preview['payload']['card']['source'] }}</em>@endif</div>
            @endif
        </div>
    @endif

    <div style="font-weight:700; margin-bottom:1rem;">{{ $preview['payload']['question'] ?? '' }}</div>

    @php $p = $preview['payload']; $cell = 'display:inline-flex; align-items:center; justify-content:center; width:1.9em; height:1.9em; font-weight:700;'; @endphp

    @if (isset($p['answer_format']))
        <div style="background:{{ $t['card'] }}; border-radius:{{ $t['radius'] }}; padding:.75rem 1rem; margin-bottom:.5rem;">
            Answer ({{ $p['answer_format'] }}): <strong>{{ $preview['correct_response']['value'] ?? '' }}</strong>
            @if ($p['time_limit_ms']) <span style="opacity:.7;">· timer {{ $p['time_limit_ms'] / 1000 }} s (advisory)</span>@endif
        </div>
    @endif

    @if (isset($p['size'], $p['grid']))
        @php $solution = $preview['correct_response']['grid'] ?? []; [$boxRows, $boxCols] = $p['box']; @endphp
        <div style="display:inline-block; background:{{ $t['card'] }}; border:2px solid {{ $t['accent'] }}; margin-bottom:.5rem;">
            @foreach ($p['grid'] as $r => $row)
                <div style="display:flex;">
                    @foreach ($row as $c => $value)
                        <span style="{{ $cell }} border-right:{{ ($c + 1) % $boxCols === 0 ? '2px' : '1px' }} solid #9CA3AF; border-bottom:{{ ($r + 1) % $boxRows === 0 ? '2px' : '1px' }} solid #9CA3AF; {{ $value === 0 ? 'color:#16A34A; font-weight:400;' : '' }}">{{ $value ?: ($solution[$r][$c] ?? '') }}</span>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div style="font-size:.75rem; opacity:.7;">Black = givens, green = solution.</div>
    @endif

    @if (($p['variant'] ?? null) === 'anagram')
        <div style="font-size:.85em; opacity:.8;">{{ $p['hint'] ?? '' }}</div>
        <div style="display:flex; gap:.35rem; margin:.5rem 0;">@foreach ($p['letters'] as $letter)<span style="{{ $cell }} background:{{ $t['card'] }}; border-radius:6px; border:1px solid {{ $t['accent'] }};">{{ $letter }}</span>@endforeach</div>
        <div style="color:#16A34A;">{{ $preview['correct_response']['word'] ?? '' }}</div>
    @elseif (($p['variant'] ?? null) === 'missing_letter')
        <div style="font-size:.85em; opacity:.8;">{{ $p['hint'] ?? '' }}</div>
        <div style="font-size:1.6em; letter-spacing:.3em; font-weight:700; margin:.5rem 0;">{{ $p['pattern'] }}</div>
        <div style="color:#16A34A;">{{ $preview['correct_response']['word'] ?? '' }}</div>
    @elseif (($p['variant'] ?? null) === 'word_search')
        @php $hits = []; foreach ($preview['correct_response']['found'] ?? [] as $f) { $len = max(abs($f['end'][0] - $f['start'][0]), abs($f['end'][1] - $f['start'][1])); $dr = $f['end'][0] <=> $f['start'][0]; $dc = $f['end'][1] <=> $f['start'][1]; for ($i = 0; $i <= $len; $i++) { $hits[($f['start'][0] + $i * $dr).':'.($f['start'][1] + $i * $dc)] = true; } } @endphp
        <div style="display:inline-block; background:{{ $t['card'] }}; padding:.25rem; border-radius:{{ $t['radius'] }}; margin-bottom:.5rem;">
            @foreach ($p['grid'] as $r => $row)
                <div style="display:flex;">@foreach ($row as $c => $letter)<span style="{{ $cell }} {{ isset($hits[$r.':'.$c]) ? 'color:#16A34A;' : 'opacity:.6;' }}">{{ $letter }}</span>@endforeach</div>
            @endforeach
        </div>
        <div>{{ implode(' · ', $p['words']) }}</div>
    @endif

    @foreach ($preview['payload']['choices'] ?? [] as $choice)
        @php $isCorrect = ($preview['correct_response']['choice_id'] ?? null) === $choice['id']; @endphp
        <div style="background:{{ $t['card'] }}; border-radius:{{ $t['radius'] }}; padding:.75rem 1rem; margin-bottom:.5rem; border:2px solid {{ $isCorrect ? '#16A34A' : 'transparent' }};">
            <strong style="color:{{ $t['accent'] }};">{{ strtoupper($choice['id']) }}</strong> {{ $choice['text'] }}
        </div>
    @endforeach

    <div style="font-size:.75rem; opacity:.7; margin-top:.75rem;">Green border = correct answer (only revealed after answering).</div>
</div>
