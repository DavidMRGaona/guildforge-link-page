<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous counters of the link page per display day: visits, scans per QR code and clicks
 * per link. Nothing about who visited, scanned or clicked is ever stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_page_stats', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // visit, scan or click
            $table->string('kind', 8);
            // The QR code id for a scan, the stable link id for a click and '' for a visit: never
            // NULL, since PostgreSQL never matches two NULLs in a unique index and every visit
            // would then add a row instead of adding one to the day's count
            $table->string('subject', 120)->default('');
            $table->date('day');
            $table->unsignedInteger('count')->default(0);

            $table->unique(['kind', 'subject', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_page_stats');
    }
};
