<?php

namespace App\Services;

use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\FinancialDocumentIdentity;
use App\Models\FinancialReceipt;
use App\Models\ServiceObligation;
use App\Models\ServicePaymentPlanInstallment;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SimpleSoftwareIO\QrCode\Generator;

class FinancialDocumentIdentityService
{
    private const SUPERSEDED_REASON = 'Uma nova versão do documento foi emitida após alteração de seu conteúdo.';

    /** @var list<class-string<Model>> */
    public const SUPPORTED_TYPES = [
        AssociateReceipt::class,
        CustomerBillingReceipt::class,
        FinancialReceipt::class,
        ServiceObligation::class,
        ServicePaymentPlanInstallment::class,
    ];

    public function ensure(Model $document, ?User $actor = null): ?FinancialDocumentIdentity
    {
        if (! Schema::hasTable('financial_document_identities')) {
            return null;
        }
        $this->assertSupported($document);
        if (! $this->supportsVersioning()) {
            return $this->ensureLegacy($document, $actor);
        }

        $snapshot = $this->snapshot($document);
        $hash = $this->hash($snapshot);

        return DB::transaction(function () use ($document, $actor, $snapshot, $hash): FinancialDocumentIdentity {
            $identities = $this->documentIdentities($document)->lockForUpdate()->get();
            $current = $identities->whereNull('invalidated_at')->sortByDesc('revision')->first();

            if ($current && ($current->document_hash === $hash || blank($current->document_hash))) {
                if (blank($current->document_hash)) {
                    $current->forceFill([
                        'document_hash' => $hash,
                        'document_snapshot' => $snapshot,
                    ])->save();
                }

                return $current;
            }

            // Compatibilidade com a implementação anterior, que incluiu por
            // engano a versão visual do PDF no hash financeiro. Se apenas esse
            // metadado mudou, preserva o UUID/QR e atualiza o hash no lugar.
            if ($current && $this->sameMaterialSnapshot($current->document_snapshot, $snapshot)) {
                $current->forceFill([
                    'document_hash' => $hash,
                    'document_snapshot' => $snapshot,
                ])->save();

                return $current;
            }

            if ($current) {
                $this->invalidateIdentity(
                    $current,
                    self::SUPERSEDED_REASON,
                    $actor,
                );
            }

            return $this->createIdentity(
                $document,
                ((int) $identities->max('revision')) + 1,
                $snapshot,
                $hash,
                $actor,
            );
        }, 5);
    }

    public function isCurrent(FinancialDocumentIdentity $identity): bool
    {
        if ($identity->invalidated_at || ! $identity->documentable) {
            return false;
        }

        // Identidades antigas recebem o hash na primeira reemissão. Até lá, o
        // status do próprio documento continua sendo a fonte de validade.
        return blank($identity->document_hash)
            || hash_equals($identity->document_hash, $this->hash($this->snapshot($identity->documentable)));
    }

    /**
     * Retorna a identidade vigente quando uma via histórica possui exatamente
     * o mesmo conteúdo material. A identidade antiga permanece invalidada no
     * histórico e nunca ganha permissão para movimentações financeiras.
     */
    public function equivalentCurrentIdentity(FinancialDocumentIdentity $identity): ?FinancialDocumentIdentity
    {
        if (! $this->supportsVersioning() || ! is_array($identity->document_snapshot)
            || ! $identity->invalidated_at
            || (filled($identity->invalidation_reason) && $identity->invalidation_reason !== self::SUPERSEDED_REASON)) {
            return null;
        }

        $document = $identity->relationLoaded('documentable')
            ? $identity->documentable
            : $identity->documentable()->withoutGlobalScopes()->first();
        if (! $document || $this->documentCannotBeValidated($document)) {
            return null;
        }

        $current = $this->documentIdentities($document)
            ->whereNull('invalidated_at')
            ->latest('revision')->latest('id')->first();
        if (! $current || (int) $current->id === (int) $identity->id) {
            return null;
        }

        $current->setRelation('documentable', $document);
        if (! $this->isCurrent($current)) {
            return null;
        }

        return $this->sameEssentialSnapshot($identity->document_snapshot, $this->snapshot($document))
            ? $current
            : null;
    }

    public function isEquivalentToCurrent(FinancialDocumentIdentity $identity): bool
    {
        return $this->equivalentCurrentIdentity($identity) !== null;
    }

    /**
     * Resolve a identidade que pode receber ações financeiras. Uma via antiga
     * nunca é reativada: quando equivalente, a operação é vinculada à revisão
     * vigente e a equivalência é conferida novamente no momento da ação.
     */
    public function actionableIdentity(FinancialDocumentIdentity $identity): ?FinancialDocumentIdentity
    {
        return $this->isCurrent($identity)
            ? $identity
            : $this->equivalentCurrentIdentity($identity);
    }

    public function invalidateDocument(Model $document, string $reason, ?User $actor = null): int
    {
        if (! Schema::hasTable('financial_document_identities')
            || ! Schema::hasColumns('financial_document_identities', ['invalidated_at', 'invalidated_by', 'invalidation_reason'])) {
            return 0;
        }

        $this->assertSupported($document);

        return $this->documentIdentities($document)
            ->whereNull('invalidated_at')
            ->update([
                'invalidated_at' => now(),
                'invalidated_by' => $actor?->id,
                'invalidation_reason' => Str::limit(trim($reason), 255, ''),
            ]);
    }

    public function find(string $publicId): ?FinancialDocumentIdentity
    {
        if (! Str::isUuid($publicId) || ! Schema::hasTable('financial_document_identities')) {
            return null;
        }

        $identity = FinancialDocumentIdentity::withoutGlobalScopes()
            ->where('public_id', strtolower($publicId))
            ->with(['checks' => fn ($query) => $query->latest('id')])
            ->first();

        if ($identity) {
            // A validação pública não depende do tenant ativo na sessão. A relação
            // é resolvida sem o escopo global e os detalhes continuam protegidos
            // pelo presenter (tenant, vínculo e permissões).
            $identity->setRelation(
                'documentable',
                $identity->documentable()->withoutGlobalScopes()->first(),
            );
        }

        return $identity;
    }

    public function url(FinancialDocumentIdentity $identity): string
    {
        return route('financial-documents.show', $identity->public_id);
    }

    public function qrDataUri(FinancialDocumentIdentity $identity, int $size = 180): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->qrSvg($identity, $size));
    }

    public function qrSvg(FinancialDocumentIdentity $identity, int $size = 180): string
    {
        return (string) (new Generator)
            ->format('svg')
            ->size($size)
            ->margin(1)
            ->generate($this->url($identity));
    }

    private function assertSupported(Model $document): void
    {
        if (! in_array($document::class, self::SUPPORTED_TYPES, true) || ! isset($document->tenant_id)) {
            throw new InvalidArgumentException('Este tipo de documento financeiro ainda não possui identidade verificável.');
        }
    }

    private function documentIdentities(Model $document)
    {
        return FinancialDocumentIdentity::withoutGlobalScopes()
            ->where('documentable_type', $document->getMorphClass())
            ->where('documentable_id', $document->getKey());
    }

    private function supportsVersioning(): bool
    {
        return Schema::hasColumns('financial_document_identities', [
            'revision',
            'document_hash',
            'document_snapshot',
            'invalidated_at',
            'invalidation_reason',
        ]);
    }

    private function ensureLegacy(Model $document, ?User $actor): FinancialDocumentIdentity
    {
        $existing = $this->documentIdentities($document)->first();
        if ($existing) {
            return $existing;
        }

        $uuid = (string) Str::uuid();

        try {
            return FinancialDocumentIdentity::withoutGlobalScopes()->create([
                'tenant_id' => $document->tenant_id,
                'public_id' => $uuid,
                'reference_code' => 'CP-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 12)),
                'documentable_type' => $document->getMorphClass(),
                'documentable_id' => $document->getKey(),
                'created_by' => $actor?->id,
            ]);
        } catch (QueryException $exception) {
            return $this->documentIdentities($document)->first() ?? throw $exception;
        }
    }

    private function createIdentity(
        Model $document,
        int $revision,
        array $snapshot,
        string $hash,
        ?User $actor,
    ): FinancialDocumentIdentity {
        $uuid = (string) Str::uuid();

        try {
            return FinancialDocumentIdentity::withoutGlobalScopes()->create([
                'tenant_id' => $document->tenant_id,
                'public_id' => $uuid,
                'reference_code' => 'CP-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 12)),
                'revision' => max(1, $revision),
                'document_hash' => $hash,
                'document_snapshot' => $snapshot,
                'documentable_type' => $document->getMorphClass(),
                'documentable_id' => $document->getKey(),
                'created_by' => $actor?->id,
            ]);
        } catch (QueryException $exception) {
            $identity = $this->documentIdentities($document)
                ->whereNull('invalidated_at')
                ->where('document_hash', $hash)
                ->latest('revision')
                ->first();
            if ($identity) {
                return $identity;
            }

            throw $exception;
        }
    }

    private function invalidateIdentity(
        FinancialDocumentIdentity $identity,
        string $reason,
        ?User $actor,
    ): void {
        $identity->forceFill([
            'invalidated_at' => now(),
            'invalidated_by' => $actor?->id,
            'invalidation_reason' => Str::limit(trim($reason), 255, ''),
        ])->save();
    }

    /** @return array<string, mixed> */
    private function snapshot(Model $document): array
    {
        $fields = match ($document::class) {
            AssociateReceipt::class => [
                'sales_project_id', 'associate_id', 'receipt_year', 'receipt_number',
                'receipt_label', 'issued_at', 'from_date', 'to_date', 'notes',
                'report_annotations', 'report_annotations_position',
                'delivery_ids', 'total_gross', 'total_fees', 'total_net', 'fee_snapshot',
            ],
            CustomerBillingReceipt::class => [
                'sales_project_id', 'customer_id', 'organization_id', 'receipt_year',
                'receipt_number', 'receipt_label', 'issued_at', 'from_date', 'to_date',
                'notes', 'report_annotations', 'report_annotations_position',
                'delivery_ids', 'total_gross', 'total_fees', 'total_net', 'fee_snapshot',
            ],
            FinancialReceipt::class => [
                'receipt_year', 'receipt_number', 'payer_type', 'payer_name',
                'received_on', 'total_amount', 'purpose', 'notes', 'issued_at',
            ],
            ServiceObligation::class => [
                'number', 'service_execution_id', 'direction', 'associate_id',
                'service_provider_id', 'principal_amount', 'adjustment_amount', 'due_date',
                'party_snapshot', 'composition_snapshot', 'snapshot_hash', 'frozen_at',
            ],
            ServicePaymentPlanInstallment::class => [
                'service_payment_plan_id', 'number', 'kind', 'due_date', 'amount',
            ],
            default => [],
        };

        $snapshot = [
            'type' => $document->getMorphClass(),
            'id' => (int) $document->getKey(),
            'tenant_id' => (int) $document->tenant_id,
            'fields' => collect($fields)->mapWithKeys(
                fn (string $field): array => [$field => $this->normalize($document->getAttribute($field))]
            )->all(),
        ];

        return $this->normalize($snapshot);
    }

    private function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function sameMaterialSnapshot(mixed $stored, array $current): bool
    {
        if (! is_array($stored)) {
            return false;
        }

        unset($stored['renderer_version']);

        return hash_equals($this->hash($this->normalize($stored)), $this->hash($current));
    }

    private function sameEssentialSnapshot(mixed $stored, array $current): bool
    {
        if (! is_array($stored)) {
            return false;
        }

        return hash_equals(
            $this->hash($this->essentialSnapshot($stored)),
            $this->hash($this->essentialSnapshot($current)),
        );
    }

    /**
     * Remove somente metadados de implementação. Campos vazios adicionados em
     * versões futuras não tornam comprovantes antigos divergentes; qualquer
     * dado financeiro, parte, distribuição ou observação preenchida permanece.
     */
    private function essentialSnapshot(array $snapshot): array
    {
        // A posição só produz conteúdo quando existem anotações. Esse campo foi
        // adicionado depois do início do versionamento com valor padrão "after"
        // e, sozinho, invalidou vias antigas que renderizavam o mesmo documento.
        if (isset($snapshot['fields']) && is_array($snapshot['fields'])) {
            $annotations = $snapshot['fields']['report_annotations'] ?? null;
            if ($annotations === null || $annotations === '' || $annotations === []) {
                unset(
                    $snapshot['fields']['report_annotations'],
                    $snapshot['fields']['report_annotations_position'],
                );
            }
        }

        $technicalKeys = [
            'renderer_version', 'snapshot_version', 'rounding', 'fee_source',
            'draft_preview', 'generated_at', 'generated_by',
        ];

        $clean = function (mixed $value) use (&$clean, $technicalKeys): mixed {
            if (! is_array($value)) {
                if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+(?:\.\d+)?$/', $value))) {
                    $number = rtrim(rtrim(number_format((float) $value, 8, '.', ''), '0'), '.');

                    return $number === '-0' || $number === '' ? '0' : $number;
                }

                return $value;
            }

            if (array_is_list($value)) {
                $items = array_map($clean, $value);
                $items = array_values(array_filter($items, fn (mixed $item): bool => ! $this->emptyMaterialValue($item)));
                usort($items, fn (mixed $left, mixed $right): int => strcmp(
                    json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ));

                return $items;
            }

            $result = [];
            foreach ($value as $key => $item) {
                if (in_array((string) $key, $technicalKeys, true)) {
                    continue;
                }
                $normalized = $clean($item);
                if (! $this->emptyMaterialValue($normalized)) {
                    $result[(string) $key] = $normalized;
                }
            }
            ksort($result);

            return $result;
        };

        return $clean($snapshot);
    }

    private function emptyMaterialValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function documentCannotBeValidated(Model $document): bool
    {
        $status = $document->getAttribute('status');
        $status = $status instanceof \BackedEnum ? $status->value : (string) $status;

        return in_array($status, ['draft', 'obsolete', 'cancelled', 'canceled'], true);
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:sP');
        }
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }
        if (! is_array($value)) {
            return is_float($value) ? number_format($value, 8, '.', '') : $value;
        }

        if (array_is_list($value)) {
            $normalized = array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
            sort($normalized);

            return $normalized;
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
