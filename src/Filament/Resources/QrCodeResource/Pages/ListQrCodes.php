<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Resources\QrCodeResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\LinkPage\Filament\Resources\QrCodeResource;

final class ListQrCodes extends ListRecords
{
    use HasKnowledgeGuideAction;

    public const string GUIDE = 'link-page.panel.qr-codes';

    protected static string $resource = QrCodeResource::class;

    public function getSubheading(): string
    {
        return __('link-page::messages.qr_codes.list_help');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            $this->knowledgeGuideAction(self::GUIDE),
            CreateAction::make(),
        ]);
    }
}
