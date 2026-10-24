<?php

namespace App\Filament\Resources\AffiliateCodes\Pages;

use App\Filament\Resources\AffiliateCodes\AffiliateCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAffiliateCodes extends ManageRecords
{
    protected static string $resource = AffiliateCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
