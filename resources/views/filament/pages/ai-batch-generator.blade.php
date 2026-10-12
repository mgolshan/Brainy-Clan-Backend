<x-filament-panels::page>
    @if (! $this->isConfigured())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <x-slot name="heading">AI generation is not configured</x-slot>
            Set <code>ANTHROPIC_API_KEY</code> in <code>.env</code> (optionally <code>CONTENT_AI_MODEL</code>), then run
            <code>docker compose exec app php artisan config:cache</code> and <code>docker compose restart app horizon</code>.
            See the README section "AI batch generator". Existing batches can still be reviewed below.
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">New batch</x-slot>
        <x-slot name="description">
            Items are stored as drafts and never reach players until approved. Content rules sent with every batch:
            age-appropriate, factual, explanation on every item, only original text or public-domain quotes (no modern authors, no book summaries).
        </x-slot>

        <form wire:submit="generate" style="display:flex; flex-direction:column; gap:1rem;">
            {{ $this->form }}

            <div>
                <x-filament::button type="submit" icon="heroicon-o-sparkles" :disabled="! $this->isConfigured()">
                    Generate drafts
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
