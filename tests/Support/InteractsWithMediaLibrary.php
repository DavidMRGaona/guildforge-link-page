<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Support;

use App\Filament\Forms\Components\MediaLibraryPicker;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;

/**
 * Files of the core media library and the «Elegir de la biblioteca» picker on the module's fields.
 */
trait InteractsWithMediaLibrary
{
    /**
     * Puts a file in the library index, as media:sync would after reading the catalog.
     */
    protected function indexLibraryFile(string $fileId, string $format, int $bytes = 1000): MediaFileModel
    {
        $position = strrpos($fileId, '/');

        return MediaFileModel::query()->create([
            'file_id' => $fileId,
            'resource_type' => 'image',
            'folder' => $position === false ? '' : substr($fileId, 0, $position + 1),
            'format' => $format,
            'bytes' => $bytes,
            'width' => 1200,
            'height' => 630,
            'uploaded_at' => now()->subMonth(),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * @template TComponent of Component
     *
     * @param  Testable<TComponent>  $page
     */
    protected function assertFieldOffersLibrary(Testable $page, string $name, string $schema = 'form'): void
    {
        $field = $this->fileUpload($page, $name, $schema);

        // Hint actions live in the schema after the label, so they are found through the page
        $page->assertActionVisible(TestAction::make(MediaLibraryPicker::ACTION)->schemaComponent(Str::after((string) $field->getKey(), $schema.'.'), schema: $schema));
        $this->assertTrue($field->shouldPreventFilePathTampering(), $name);
    }

    /**
     * @template TComponent of Component
     *
     * @param  Testable<TComponent>  $page
     */
    protected function fileUpload(Testable $page, string $name, string $schema = 'form'): FileUpload
    {
        $component = $page->instance();
        $this->assertInstanceOf(HasSchemas::class, $component);
        $form = $component->getSchema($schema);
        $this->assertInstanceOf(Schema::class, $form);

        foreach ($form->getFlatFields(withHidden: true) as $field) {
            if ($field instanceof FileUpload && $field->getName() === $name) {
                return $field;
            }
        }

        $this->fail("No file upload named {$name}");
    }
}
