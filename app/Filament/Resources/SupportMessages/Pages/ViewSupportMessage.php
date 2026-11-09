<?php

namespace App\Filament\Resources\SupportMessages\Pages;

use App\Filament\Resources\SupportMessages\SupportMessageResource;
use App\Modules\Support\Actions\ChangeSupportMessageStatus;
use App\Modules\Support\Models\SupportMessage;
use Filament\Resources\Pages\ViewRecord;

/**
 * Opening a `new` message marks it `open` (seen by staff; the badge and the dashboard card count
 * only `new`).
 */
class ViewSupportMessage extends ViewRecord
{
    protected static string $resource = SupportMessageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $message = $this->getRecord();

        if ($message instanceof SupportMessage) {
            app(ChangeSupportMessageStatus::class)->markSeen($message);
        }
    }

    public function getTitle(): string
    {
        $message = $this->getRecord();

        return $message instanceof SupportMessage ? 'Support message #'.$message->id : 'Support message';
    }

    protected function getHeaderActions(): array
    {
        return SupportMessageResource::messageActions();
    }
}
