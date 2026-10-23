<?php

namespace App\Filament\Resources\ThemeWorlds\Pages;

use App\Filament\Resources\ThemeWorlds\ThemeWorldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageThemeWorlds extends ManageRecords
{
    protected static string $resource = ThemeWorldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
