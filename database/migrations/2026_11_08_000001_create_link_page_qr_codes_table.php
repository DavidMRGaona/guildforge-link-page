<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_page_qr_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            // Unique across deleted rows too: a printed code is never handed out again
            $table->string('code', 16)->unique();
            $table->boolean('is_active')->default(true);
            $table->string('foreground_color', 7)->default('#000000');
            $table->string('background_color', 7)->default('#FFFFFF');
            $table->unsignedTinyInteger('margin')->default(8);
            $table->string('logo_source', 16)->default('none');
            $table->string('logo_path')->nullable();
            $table->string('tagline', 150)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_page_qr_codes');
    }
};
