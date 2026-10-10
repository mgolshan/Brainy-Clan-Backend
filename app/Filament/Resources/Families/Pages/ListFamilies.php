<?php

namespace App\Filament\Resources\Families\Pages;

use App\Filament\Resources\Families\FamilyResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Families are created by the app, not in the admin.
 */
class ListFamilies extends ListRecords
{
    protected static string $resource = FamilyResource::class;
}
