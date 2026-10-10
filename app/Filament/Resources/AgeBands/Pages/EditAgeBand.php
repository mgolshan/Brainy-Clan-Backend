<?php

namespace App\Filament\Resources\AgeBands\Pages;

use App\Filament\Resources\AgeBands\AgeBandResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAgeBand extends EditRecord
{
    protected static string $resource = AgeBandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
