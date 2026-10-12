<x-filament-panels::page>
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
            <label style="display:flex; flex-direction:column; gap:.25rem;">
                <span style="font-size:.85rem; font-weight:600;">…or a generated sample</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="sample">
                        <option value="">— content item —</option>
                        @foreach ($samples as $key => $sampleDef)
                            <option value="{{ $key }}">{{ $sampleDef[2] }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            @if ($sample)
                <label style="display:flex; flex-direction:column; gap:.25rem;">
                    <span style="font-size:.85rem; font-weight:600;">Difficulty (1–10)</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="number" min="1" max="10" wire:model.live.debounce.500ms="difficulty" />
                    </x-filament::input.wrapper>
                </label>
                <label style="display:flex; flex-direction:column; gap:.25rem;">
                    <span style="font-size:.85rem; font-weight:600;">Seed</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model.live.debounce.500ms="seed" />
                    </x-filament::input.wrapper>
                </label>
            @endif
            <x-filament::button wire:click="randomItem" color="gray" icon="heroicon-o-arrow-path">Random published item</x-filament::button>
        </div>
    </x-filament::section>

    @if ($record === null)
        <x-filament::section>
            Enter an item id (or open "Preview" from the content items table).
        </x-filament::section>
    @elseif ($preview !== null)

        <div style="display:grid; grid-template-columns: minmax(280px, 380px) 1fr; gap:1.5rem; align-items:start;">
            @include('filament.partials.content-phone', ['preview' => $preview, 'record' => $record])

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
