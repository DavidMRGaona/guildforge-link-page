<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Resources\LinkResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\LinkPage\Filament\Resources\LinkResource;

final class CreateLink extends CreateRecord
{
    protected static string $resource = LinkResource::class;
}
