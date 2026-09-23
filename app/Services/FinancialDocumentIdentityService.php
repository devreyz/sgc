<?php

namespace App\Services;

use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\FinancialDocumentIdentity;
use App\Models\FinancialReceipt;
use App\Models\ServiceObligation;
use App\Models\ServicePaymentPlanInstallment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SimpleSoftwareIO\QrCode\Generator;

class FinancialDocumentIdentityService
{
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
        if (! in_array($document::class, self::SUPPORTED_TYPES, true) || ! isset($document->tenant_id)) {
            throw new InvalidArgumentException('Este tipo de documento financeiro ainda não possui identidade verificável.');
        }

        $existing = FinancialDocumentIdentity::withoutGlobalScopes()
            ->where('documentable_type', $document->getMorphClass())
            ->where('documentable_id', $document->getKey())
            ->first();
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
            $identity = FinancialDocumentIdentity::withoutGlobalScopes()
                ->where('documentable_type', $document->getMorphClass())
                ->where('documentable_id', $document->getKey())
                ->first();
            if ($identity) {
                return $identity;
            }

            throw $exception;
        }
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
}
