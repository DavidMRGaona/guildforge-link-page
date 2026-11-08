<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Resources\LinkResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\LinkPage\Filament\Resources\LinkResource;

final class ListLinks extends ListRecords
{
    use HasKnowledgeGuideAction;

    public const string GUIDE = 'link-page.panel.links-and-socials';

    protected static string $resource = LinkResource::class;

    public function getSubheading(): string
    {
        return __('link-page::messages.links.list_help');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            $this->knowledgeGuideAction(self::GUIDE),
            CreateAction::make(),
        ]);
    }
}
