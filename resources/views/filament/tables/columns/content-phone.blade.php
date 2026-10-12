{{-- AI batch review: the shared phone preview of one item (state = PreviewContentItem result). --}}
<div style="padding:.5rem 0; max-width:360px;">
    @include('filament.partials.content-phone', ['preview' => $getState(), 'record' => $getRecord(), 'compact' => true])
</div>
