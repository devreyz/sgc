<?php

namespace App\Services;

use App\Models\SalesProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProjectReceiptNumberingService
{
    public const TENANT_YEAR = 'tenant_year';

    public const PROJECT_YEAR = 'project_year';

    public const DEFAULT_TENANT_FORMAT = '{prefix}{number}/{year}';

    public const DEFAULT_PROJECT_FORMAT = '{prefix}{number}/{year}-{project}';

    /** @var array<int, string> */
    private const PLACEHOLDERS = ['prefix', 'number', 'year', 'project'];

    public function nextNumber(
        string $receiptModel,
        int $tenantId,
        int $year,
        ?SalesProject $project = null,
    ): int {
        if (Schema::hasTable('receipt_number_sequences')) {
            return $this->reserveNumber($receiptModel, $tenantId, $year, $project);
        }

        return $this->currentMaximum($receiptModel, $tenantId, $year, $project) + 1;
    }

    private function reserveNumber(
        string $receiptModel,
        int $tenantId,
        int $year,
        ?SalesProject $project,
    ): int {
        $projectScoped = $project && $this->usesProjectSequence($project);
        $scopeKey = $projectScoped ? 'project:'.$project->getKey() : 'tenant';
        $receiptType = str_contains($receiptModel, 'CustomerBillingReceipt') ? 'customer' : 'associate';

        return DB::transaction(function () use (
            $receiptModel,
            $tenantId,
            $year,
            $project,
            $projectScoped,
            $scopeKey,
            $receiptType,
        ): int {
            $maximum = $this->currentMaximum($receiptModel, $tenantId, $year, $project);

            DB::table('receipt_number_sequences')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'sales_project_id' => $projectScoped ? $project?->getKey() : null,
                'scope_key' => $scopeKey,
                'receipt_type' => $receiptType,
                'receipt_year' => $year,
                'last_number' => $maximum,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('receipt_number_sequences')
                ->where('tenant_id', $tenantId)
                ->where('scope_key', $scopeKey)
                ->where('receipt_type', $receiptType)
                ->where('receipt_year', $year)
                ->lockForUpdate()
                ->first();

            $next = max($maximum, (int) ($sequence?->last_number ?? 0)) + 1;
            DB::table('receipt_number_sequences')->where('id', $sequence->id)->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

            return $next;
        });
    }

    private function currentMaximum(
        string $receiptModel,
        int $tenantId,
        int $year,
        ?SalesProject $project,
    ): int {
        /** @var Builder<Model> $query */
        $query = $receiptModel::query()
            ->where('tenant_id', $tenantId)
            ->where('receipt_year', $year);

        if ($project && $this->usesProjectSequence($project)) {
            $query->where('sales_project_id', $project->getKey());
        }

        return (int) $query->max('receipt_number');
    }

    public function format(
        SalesProject $project,
        int $number,
        int $year,
        string $prefix = '',
    ): string {
        $format = $this->validatedFormat($project->receipt_number_format)
            ?? ($this->usesProjectSequence($project)
                ? self::DEFAULT_PROJECT_FORMAT
                : self::DEFAULT_TENANT_FORMAT);

        $reference = trim((string) $project->receipt_project_reference);
        if ($reference === '') {
            $reference = 'P'.$project->getKey();
        }

        $label = strtr($format, [
            '{prefix}' => $prefix,
            '{number}' => str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            '{year}' => (string) $year,
            '{project}' => $reference,
        ]);

        return mb_substr(trim($label), 0, 80);
    }

    public function usesProjectSequence(SalesProject $project): bool
    {
        return $project->receipt_numbering_scope === self::PROJECT_YEAR;
    }

    public function validatedFormat(mixed $format): ?string
    {
        $format = trim((string) $format);
        if ($format === '') {
            return null;
        }

        if (mb_strlen($format) > 80 || ! preg_match('/^[A-Za-z0-9\s._\-\/{\}]+$/u', $format)) {
            return null;
        }

        preg_match_all('/\{([^}]+)\}/', $format, $matches);
        $placeholders = $matches[1] ?? [];
        if (array_diff($placeholders, self::PLACEHOLDERS) !== []) {
            return null;
        }

        if (! in_array('number', $placeholders, true) || ! in_array('year', $placeholders, true)) {
            return null;
        }

        return $format;
    }
}
