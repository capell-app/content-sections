<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            if (! Schema::hasColumn('sections', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id')->index();
            }
        });

        DB::table('sections')
            ->whereNull('uuid')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $section): void {
                DB::table('sections')
                    ->where('id', $section->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            if (Schema::hasColumn('sections', 'uuid')) {
                $table->dropIndex(['uuid']);
                $table->dropColumn('uuid');
            }
        });
    }
};
