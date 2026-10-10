<x-filament-panels::page>
    @php
        $themes = [
            'playful' => ['bg' => '#FFF7E6', 'card' => '#FFFFFF', 'accent' => '#FF8A00', 'font' => '1.1rem', 'radius' => '22px'],
            'sleek' => ['bg' => '#0F172A', 'card' => '#1E293B', 'accent' => '#38BDF8', 'font' => '1rem', 'radius' => '12px'],
            'modern' => ['bg' => '#F4F4F8', 'card' => '#FFFFFF', 'accent' => '#4F46E5', 'font' => '1rem', 'radius' => '16px'],
            'classic' => ['bg' => '#FBF8F1', 'card' => '#FFFFFF', 'accent' => '#1E3A5F', 'font' => '1.2rem', 'radius' => '8px'],
        ];
    @endphp

    <x-filament::section>
        <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end;">
            <label style="display:flex; flex-direction:column; gap:.25rem;">
                <span style="font-size:.85rem; font-weight:600;">Item id</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="number" wire:model.live.debounce.500ms="item" placeholder="e.g. 42" />
                </x-filament::input.wrapper>
            </label>
            <label style="display:flex; flex-direction:column; gap:.25rem;">
                <span style="font-size:.85rem; font-weight:600;">Age band</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="band">
                        @foreach ($bands as $b)
                            <option value="{{ $b->code }}">{{ $b->name }} ({{ $b->min_age }}–{{ $b->max_age ?? '∞' }})</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label style="display:flex; flex-direction:column; gap:.25rem;">
                <span style="font-size:.85rem; font-weight:600;">Exact age (optional)</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="number" wire:model.live.debounce.500ms="age" placeholder="band min + 1" />
                </x-filament::input.wrapper>
            </label>
            <x-filament::button wire:click="randomItem" color="gray" icon="heroicon-o-arrow-path">Random published item</x-filament::button>
        </div>
    </x-filament::section>

    @if ($record === null)
        <x-filament::section>
            Enter an item id (or open "Preview" from the content items table).
        </x-filament::section>
    @elseif ($preview !== null)
        @php $t = $themes[$preview['ui_theme']] ?? $themes['modern']; $dark = $preview['ui_theme'] === 'sleek'; @endphp

        <div style="display:grid; grid-template-columns: minmax(280px, 380px) 1fr; gap:1.5rem; align-items:start;">
            <div style="background:{{ $t['bg'] }}; color:{{ $dark ? '#E2E8F0' : '#111827' }}; border-radius:28px; padding:1.25rem; border:8px solid #111827; min-height:520px; font-size:{{ $t['font'] }};">
                <div style="font-size:.75rem; opacity:.7; margin-bottom:.75rem;">
                    #{{ $record->id }} · {{ $preview['type'] }} · {{ $preview['ui_theme'] }} theme
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

                @foreach ($preview['payload']['choices'] ?? [] as $choice)
                    @php $isCorrect = ($preview['correct_response']['choice_id'] ?? null) === $choice['id']; @endphp
                    <div style="background:{{ $t['card'] }}; border-radius:{{ $t['radius'] }}; padding:.75rem 1rem; margin-bottom:.5rem; border:2px solid {{ $isCorrect ? '#16A34A' : 'transparent' }};">
                        <strong style="color:{{ $t['accent'] }};">{{ strtoupper($choice['id']) }}</strong> {{ $choice['text'] }}
                    </div>
                @endforeach

                <div style="font-size:.75rem; opacity:.7; margin-top:.75rem;">Green border = correct answer (only revealed after answering).</div>
            </div>

            <div style="display:flex; flex-direction:column; gap:1rem;">
                <x-filament::section>
                    <x-slot name="heading">Served to this band?</x-slot>
                    @if ($preview['served'])
                        <x-filament::badge color="success">Yes</x-filament::badge>
                    @else
                        <x-filament::badge color="warning">Not as a regular item</x-filament::badge>
                        <ul style="margin-top:.5rem; list-style:disc; padding-left:1.25rem;">
                            @foreach ($preview['reasons'] as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    @endif
                </x-filament::section>

                <x-filament::section collapsible>
                    <x-slot name="heading">Payload sent to the app (GET /play/sessions/…)</x-slot>
                    <pre style="font-size:.8rem; white-space:pre-wrap;">{{ $json($preview['payload']) }}</pre>
                </x-filament::section>

                <x-filament::section collapsible>
                    <x-slot name="heading">After answering (POST …/answers)</x-slot>
                    <pre style="font-size:.8rem; white-space:pre-wrap;">{{ $json(['correct_response' => $preview['correct_response'], 'explanation' => $preview['explanation']]) }}</pre>
                </x-filament::section>
            </div>
        </div>
    @endif
</x-filament-panels::page>
