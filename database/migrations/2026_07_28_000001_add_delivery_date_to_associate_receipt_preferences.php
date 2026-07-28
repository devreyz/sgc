<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_projects', 'associate_receipt_columns')) {
            return;
        }

        DB::table('sales_projects')
            ->select(['id', 'associate_receipt_columns'])
            ->whereNotNull('associate_receipt_columns')
            ->orderBy('id')
            ->chunkById(200, function ($projects): void {
                foreach ($projects as $project) {
                    $columns = $this->decodeColumns($project->associate_receipt_columns);

                    if (! in_array('delivery_date', $columns, true)) {
                        array_unshift($columns, 'delivery_date');
                        DB::table('sales_projects')
                            ->where('id', $project->id)
                            ->update(['associate_receipt_columns' => json_encode(array_values($columns))]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sales_projects', 'associate_receipt_columns')) {
            return;
        }

        DB::table('sales_projects')
            ->select(['id', 'associate_receipt_columns'])
            ->whereNotNull('associate_receipt_columns')
            ->orderBy('id')
            ->chunkById(200, function ($projects): void {
                foreach ($projects as $project) {
                    $columns = array_values(array_filter(
                        $this->decodeColumns($project->associate_receipt_columns),
                        fn ($column): bool => $column !== 'delivery_date',
                    ));

                    DB::table('sales_projects')
                        ->where('id', $project->id)
                        ->update(['associate_receipt_columns' => json_encode($columns)]);
                }
            });
    }

    private function decodeColumns(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
