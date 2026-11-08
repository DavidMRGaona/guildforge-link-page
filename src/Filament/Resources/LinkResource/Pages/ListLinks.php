<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Resources\LinkResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\LinkPage\Filament\Resources\LinkResource;

final class ListLinks extends ListRecords
{
    protected static string $resource = LinkResource::class;

    public function getSubheading(): string
    {
        return __('link-page::messages.links.list_help');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
