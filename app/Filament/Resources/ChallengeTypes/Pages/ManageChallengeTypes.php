<?php

namespace App\Filament\Resources\ChallengeTypes\Pages;

use App\Filament\Resources\ChallengeTypes\ChallengeTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageChallengeTypes extends ManageRecords
{
    protected static string $resource = ChallengeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
