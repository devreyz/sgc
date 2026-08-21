<?php

namespace App\Services;

use App\Models\SalesProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
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

    /**
     * Reserve both identifiers. The project setting only chooses which one is
     * printed; changing that setting must never require renumbering a receipt.
     *
     * @return array{receipt_year:int,receipt_number:int,receipt_label:string,tenant_receipt_year:int,tenant_receipt_number:int,project_receipt_year:int,project_receipt_number:int}
     */
    public function numberingFor(
        string $receiptModel,
        SalesProject $project,
        string $prefix = '',
        mixed $issuedAt = null,
    ): array {
        if (! Schema::hasColumn((new $receiptModel)->getTable(), 'tenant_receipt_number')) {
            $year = (int) ($project->reference_year ?: now()->year);
            $number = $this->nextNumber($receiptModel, (int) $project->tenant_id, $year, $project);

            return [
                'receipt_year' => $year,
                'receipt_number' => $number,
                'receipt_label' => $this->format($project, $number, $year, $prefix),
            ];
        }

        $tenantYear = $issuedAt
            ? Carbon::parse($issuedAt)->year
            : now()->year;
        $projectYear = (int) ($project->reference_year ?: $tenantYear);
        $tenantNumber = $this->nextNumberForScope(
            $receiptModel,
            (int) $project->tenant_id,
            $tenantYear,
            null,
            'tenant'
        );
        $projectNumber = $this->nextNumberForScope(
            $receiptModel,
            (int) $project->tenant_id,
            $projectYear,
            $project,
            'project:'.$project->getKey()
        );

        $displayNumber = $this->usesProjectSequence($project) ? $projectNumber : $tenantNumber;
        $displayYear = $this->usesProjectSequence($project) ? $projectYear : $tenantYear;

        return [
            // Legacy columns remain populated for integrations created before dual numbering.
            'receipt_year' => $displayYear,
            'receipt_number' => $displayNumber,
            'receipt_label' => $this->format($project, $displayNumber, $displayYear, $prefix),
            'tenant_receipt_year' => $tenantYear,
            'tenant_receipt_number' => $tenantNumber,
            'project_receipt_year' => $projectYear,
            'project_receipt_number' => $projectNumber,
        ];
    }

    private function nextNumberForScope(
        string $receiptModel,
        int $tenantId,
        int $year,
        ?SalesProject $project,
        string $scopeKey,
    ): int {
        if (! Schema::hasTable('receipt_number_sequences')) {
            return $this->currentMaximumForScope($receiptModel, $tenantId, $year, $project) + 1;
        }

        $receiptType = str_contains($receiptModel, 'CustomerBillingReceipt') ? 'customer' : 'associate';

        return DB::transaction(function () use ($receiptModel, $tenantId, $year, $project, $scopeKey, $receiptType): int {
            $maximum = $this->currentMaximumForScope($receiptModel, $tenantId, $year, $project);

            DB::table('receipt_number_sequences')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'sales_project_id' => $project?->getKey(),
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
        }, 5);
    }

    private function currentMaximumForScope(
        string $receiptModel,
        int $tenantId,
        int $year,
        ?SalesProject $project,
    ): int {
        $query = $receiptModel::query()->where('tenant_id', $tenantId);

        if ($project) {
            return (int) $query
                ->where('sales_project_id', $project->getKey())
                ->where('project_receipt_year', $year)
                ->max('project_receipt_number');
        }

        return (int) $query
            ->where('tenant_receipt_year', $year)
            ->max('tenant_receipt_number');
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
        }, 5);
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

    public function formatTenant(int $number, int $year, string $prefix = ''): string
    {
        return strtr(self::DEFAULT_TENANT_FORMAT, [
            '{prefix}' => $prefix,
            '{number}' => str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            '{year}' => (string) $year,
        ]);
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
