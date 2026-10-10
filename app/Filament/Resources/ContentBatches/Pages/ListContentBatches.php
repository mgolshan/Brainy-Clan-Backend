<?php

namespace App\Filament\Resources\ContentBatches\Pages;

use App\Filament\Resources\ContentBatches\ContentBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContentBatches extends ListRecords
{
    protected static string $resource = ContentBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
