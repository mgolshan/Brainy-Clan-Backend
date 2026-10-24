<?php

namespace App\Filament\Resources\CommissionEntries\Pages;

use App\Filament\Resources\CommissionEntries\CommissionEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissionEntries extends ListRecords
{
    protected static string $resource = CommissionEntryResource::class;
}
