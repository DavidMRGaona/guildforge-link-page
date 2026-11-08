<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Resources\QrCodeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\LinkPage\Filament\Resources\QrCodeResource;

final class CreateQrCode extends CreateRecord
{
    protected static string $resource = QrCodeResource::class;

    protected function getRedirectUrl(): string
    {
        // The edit page has the download and print actions
        return QrCodeResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
