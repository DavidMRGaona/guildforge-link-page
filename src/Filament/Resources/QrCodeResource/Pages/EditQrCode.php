<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Resources\QrCodeResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\LinkPage\Domain\Enums\PrintPaper;
use Modules\LinkPage\Filament\Resources\QrCodeResource;

final class EditQrCode extends EditRecord
{
    protected static string $resource = QrCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            QrCodeResource::downloadAction(),
            QrCodeResource::printAction(PrintPaper::A4),
            QrCodeResource::printAction(PrintPaper::A5),
            QrCodeResource::deleteAction(DeleteAction::make()),
        ];
    }
}
