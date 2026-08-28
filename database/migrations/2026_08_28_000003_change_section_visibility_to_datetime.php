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
    public function up(): void
    {
        if (! Schema::hasTable('sections')) {
            return;
        }

        $connection = Schema::getConnection();

        // Remove the expression index before Laravel rebuilds the table for
        // `change()`, then restore it afterwards when the dialect supports it.
        if (Schema::hasIndex('sections', 'sections_page_id_index')) {
            Schema::table('sections', function (Blueprint $table): void {
                $table->dropIndex('sections_page_id_index');
            });
        }

        Schema::table('sections', function (Blueprint $table): void {
            $table->dateTime('visible_from')->nullable()->change();
            $table->dateTime('visible_until')->nullable()->change();
        });

        $schema = CapellDatabase::for($connection)->schemaDialect();

        if ($schema->supports(DatabaseCapability::JsonPathIndex, $connection)
            && ! Schema::hasIndex('sections', 'sections_page_id_index')) {
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

    public function down(): void
    {
        // DATETIME is required for the publication sentinel and cannot be safely reverted.
    }
};
