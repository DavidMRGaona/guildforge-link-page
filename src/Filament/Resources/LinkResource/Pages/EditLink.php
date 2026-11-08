<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Resources\LinkResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\LinkPage\Filament\Resources\LinkResource;

final class EditLink extends EditRecord
{
    protected static string $resource = LinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LinkResource::deleteAction(DeleteAction::make()),
        ];
    }
}
