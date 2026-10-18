<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * after() is a column modifier. Chained after constrained() it lands on the foreign key definition instead, where no
 * grammar reads it, so on MySQL/MariaDB the column was appended at the end of the table (PostgreSQL ignores after()).
 */
class MigrationColumnPlacementTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function placedForeignIds(): array
    {
        return [
            'orders.courier_id' => ['2026_05_28_204912_add_courier_id_to_orders_table.php', 'orders', 'courier_id', 'user_id'],
            'users.branch_id' => ['2026_06_05_130629_add_status_and_relations_to_tables.php', 'users', 'branch_id', 'store_id'],
            'orders.manager_id' => ['2026_06_05_130629_add_status_and_relations_to_tables.php', 'orders', 'manager_id', 'courier_id'],
        ];
    }

    #[DataProvider('placedForeignIds')]
    public function test_the_foreign_id_column_itself_carries_the_after_modifier(string $migration, string $table, string $column, string $after): void
    {
        $blueprints = $this->blueprintsBuiltBy($migration);

        $definition = collect($blueprints[$table]->getColumns())->firstWhere('name', $column);

        $this->assertInstanceOf(ColumnDefinition::class, $definition);
        $this->assertSame($after, $definition->get('after'));
    }

    /**
     * The blueprints a migration's up() builds, without running any of them.
     *
     * @return array<string, Blueprint>
     */
    private function blueprintsBuiltBy(string $migration): array
    {
        $blueprints = [];

        Schema::shouldReceive('table')->andReturnUsing(function (string $table, Closure $callback) use (&$blueprints) {
            $blueprints[$table] = new Blueprint(DB::connection(), $table);
            $callback($blueprints[$table]);
        });

        (require database_path("migrations/{$migration}"))->up();

        return $blueprints;
    }
}
