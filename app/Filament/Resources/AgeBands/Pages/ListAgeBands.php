<?php

namespace App\Filament\Resources\AgeBands\Pages;

use App\Filament\Resources\AgeBands\AgeBandResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgeBands extends ListRecords
{
    protected static string $resource = AgeBandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
