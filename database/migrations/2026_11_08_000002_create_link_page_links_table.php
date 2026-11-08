<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_page_links', function (Blueprint $table): void {
            // The id is what the statistics count a link by: it never changes
            $table->uuid('id')->primary();
            $table->string('title', 100);
            $table->string('url', 2048);
            $table->string('description', 160)->nullable();
            $table->string('icon', 16)->nullable();
            $table->string('placement', 8)->default('before');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            // Instants in the storage zone, like every datetime of the core
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_page_links');
    }
};
