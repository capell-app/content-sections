<?php

declare(strict_types=1);

use Capell\Core\Data\Database\DatabaseIndexDefinition;
use Capell\Core\Enums\Database\DatabaseCapability;
use Capell\Core\Facades\CapellDatabase;
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
        Schema::create('sections', function (Blueprint $table): void {
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

            $table->index(['site_id', 'blueprint_id', 'order']);
            $table->index(['site_id', 'blueprint_id', 'parent_id']);
            $table->index(['site_id', 'blueprint_id', 'visible_from', 'visible_until']);
            $table->nestedSetDepth();
            $table->nestedSetIndex();
        });

        $connection = Schema::getConnection();
        $schema = CapellDatabase::for($connection)->schemaDialect();

        if ($schema->supports(DatabaseCapability::JsonPathIndex, $connection)) {
            $index = $schema->jsonPathIndex(
                new DatabaseIndexDefinition('sections', 'sections_page_id_index', ['meta']),
                'meta',
                '$.page_id',
            );

            if ($index !== null) {
                DB::statement($index->sql, $index->bindings);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
