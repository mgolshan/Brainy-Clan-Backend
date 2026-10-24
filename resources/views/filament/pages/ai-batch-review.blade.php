<x-filament-panels::page>
    @php $batch = $this->record; $skipped = $batch->skipped ?? []; @endphp

    <x-filament::section>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:.75rem 1.5rem; font-size:.9rem;">
            <div><div style="opacity:.6;">Category</div>{{ $this->categoryPath() }}</div>
            <div><div style="opacity:.6;">Type</div>{{ $batch->typeLabel() }} · {{ $batch->locale }}</div>
            <div><div style="opacity:.6;">Status</div><x-filament::badge :color="\App\Modules\Content\Models\ContentBatch::statusColor($batch->status)" style="display:inline-flex;">{{ $batch->status }}</x-filament::badge></div>
            <div><div style="opacity:.6;">Difficulty / ages</div>{{ $batch->min_difficulty ?? '?' }}–{{ $batch->max_difficulty ?? '?' }} · {{ $batch->min_age ?? 0 }}–{{ $batch->max_age ?? '∞' }}</div>
            <div><div style="opacity:.6;">Asked / stored</div>{{ $batch->requested_count ?? '—' }} / {{ $batch->item_count }}</div>
            <div><div style="opacity:.6;">Model / tokens in · out</div>{{ $batch->model ?? '—' }} · {{ number_format((int) $batch->input_tokens) }} · {{ number_format((int) $batch->output_tokens) }}</div>
            <div><div style="opacity:.6;">Created by</div>{{ $batch->creator?->display_name ?? '—' }}, {{ $batch->created_at?->diffForHumans() }}</div>
        </div>

        @if ($batch->note)
            <div style="margin-top:.75rem; font-size:.9rem;"><span style="opacity:.6;">Note:</span> {{ $batch->note }}</div>
        @endif

        @if ($batch->error)
            <div style="margin-top:.75rem; color:#DC2626; font-size:.9rem;">{{ $batch->error }}</div>
        @endif
    </x-filament::section>

    <x-filament::section collapsible>
        <x-slot name="heading">Reviewer checklist</x-slot>
        <x-slot name="description">Mission: kids use screens to learn and grow. Clean, happy, neutral. Reject or edit any item that fails one line.</x-slot>
        <ul style="list-style:disc; padding-left:1.25rem; font-size:.85rem; display:grid; gap:.2rem;">
            <li>Correct: one defensible answer, every distractor clearly wrong, no fact that changes quickly. If in doubt, reject.</li>
            <li>Neutral: no ideological or activist message from any side, no moralising or persuasion toward a worldview, facts not judgments.</li>
            <li>No current politics or politicians, no culture-war topics, no religion.</li>
            <li>No sexual content, nothing about sexuality or gender identity topics.</li>
            <li>No hate: nothing demeaning about any group, nationality, body or ability; no stereotypes.</li>
            <li>Child-safe: no violence detail, gore or scary content; history stated plainly (who, what, when).</li>
            <li>Positive and kind tone; the explanation teaches something; original text or public-domain quotes only.</li>
        </ul>
    </x-filament::section>

    @if (! empty($skipped['invalid']) || ! empty($skipped['duplicates']))
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Not stored: {{ count($skipped['invalid'] ?? []) }} invalid, {{ count($skipped['duplicates'] ?? []) }} duplicates</x-slot>
            <ul style="list-style:disc; padding-left:1.25rem; font-size:.85rem;">
                @foreach ($skipped['invalid'] ?? [] as $entry)
                    <li>Item {{ $entry['item'] }}: {{ implode(' ', $entry['errors']) }}</li>
                @endforeach
                @foreach ($skipped['duplicates'] ?? [] as $text)
                    <li>Duplicate: {{ $text }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($batch->prompt)
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Prompt sent to the model</x-slot>
            <pre style="font-size:.8rem; white-space:pre-wrap;">{{ $batch->prompt }}</pre>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
