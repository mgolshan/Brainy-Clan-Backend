<?php

namespace App\Filament\Resources\ContentBatches\Pages;

use App\Filament\Resources\ContentBatches\ContentBatchResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContentBatch extends ViewRecord
{
    protected static string $resource = ContentBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
