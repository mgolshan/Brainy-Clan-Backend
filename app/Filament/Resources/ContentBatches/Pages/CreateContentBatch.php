<?php

namespace App\Filament\Resources\ContentBatches\Pages;

use App\Filament\Resources\ContentBatches\ContentBatchResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateContentBatch extends CreateRecord
{
    protected static string $resource = ContentBatchResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
