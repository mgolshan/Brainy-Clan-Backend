<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Filament\Pages\ContentPreview;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Modules\Content\Actions\SaveContentItem;
use App\Modules\Content\Models\ContentItem;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditContentItem extends EditRecord
{
    protected static string $resource = ContentItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')->icon(Heroicon::OutlinedEye)
                ->url(fn () => ContentPreview::getUrl(['item' => $this->getRecord()->getKey()])),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ContentItem $record */
        return app(SaveContentItem::class)->handle($data, $record);
    }
}
