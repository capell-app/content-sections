<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $databaseVersion = $driver === 'mysql' ? (string) DB::selectOne('select version() as v')->v : null;

        Schema::create('sections', function (Blueprint $table) use ($databaseVersion, $driver): void {
            $table->id();
            $table->uuid('uuid')->nullable()->index();
            $table->unsignedBigInteger('workspace_id')->default(0)->index();
            $table->unsignedBigInteger('shadowed_by_workspace_id')->default(0)->index();
            $table->string('name');
            $table->foreignId('blueprint_id')->constrained('blueprints');
            $table->foreignId('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('meta')->nullable();
            $table->unsignedInteger('order')->default(0)->index();
            $table->visibleDates();
            $table->nestedSet();
            $table->userstamps();
            $table->timestamps();
            $table->softDeletes();

            if ($driver === 'pgsql') {
                $table->index('meta->page_id', 'sections_page_id_index');
            }

            if (
                $driver === 'mysql' &&
                $databaseVersion !== null &&
                version_compare($databaseVersion, '8.0.13', '>=') &&
                ! str_contains($databaseVersion, 'MariaDB')
            ) {
                $table->rawIndex(
                    '(cast(json_unquote(json_extract(`meta`, \'$."page_id"\')) as unsigned))',
                    'sections_page_id_index',
                );
            }

            $table->index(['site_id', 'blueprint_id', 'order']);
            $table->index(['site_id', 'blueprint_id', 'parent_id']);
            $table->index(['site_id', 'blueprint_id', 'visible_from', 'visible_until']);
            $table->nestedSetDepth();
            $table->nestedSetIndex();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
