<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Resources\QrCodeResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\LinkPage\Filament\Resources\QrCodeResource;

final class ListQrCodes extends ListRecords
{
    protected static string $resource = QrCodeResource::class;

    public function getSubheading(): string
    {
        return __('link-page::messages.qr_codes.list_help');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
