<?php

namespace App\Filament\Resources\ContentBatches\Pages;

use App\Filament\Resources\ContentBatches\ContentBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditContentBatch extends EditRecord
{
    protected static string $resource = ContentBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
