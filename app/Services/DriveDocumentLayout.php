<?php

namespace App\Services;

use App\Models\{AssociateReceipt, CustomerBillingReceipt, DeliveryConferenceSheet, Document, Expense, ServiceExecution, ServiceOrder};
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DriveDocumentLayout
{
    public static function resolve(string $type, Model $owner, string $filename): ?array
    {
        $attachment = null;
        $record = $owner;
        if (str_starts_with($type, 'service_expense_attachment_')) {
            $typeKey = 'expense_document';
            $attachment = 'Anexo '.Str::upper(substr(hash('sha256', $type), 0, 8));
        } elseif (str_starts_with($type, 'service_evidence_') && $owner instanceof ServiceExecution) {
            $typeKey = 'service_evidence';
            $attachment = 'Evidência '.Str::upper(substr(hash('sha256', $type), 0, 8));
            $record = $owner->order ?? $owner;
        } elseif ($type === 'delivery_conference_signed' && $owner instanceof Document) {
            $typeKey = $type;
            $attachment = 'Folha '.$owner->getKey();
            $record = $owner->documentable instanceof DeliveryConferenceSheet ? $owner->documentable : $owner;
        } else {
            $typeKey = $type;
        }

        [$category, $prefix, $dateField, $fallback] = match ($typeKey) {
            'expense_document' => ['Despesas', 'DES', 'date', 'Despesa'],
            'revenue_document' => ['Receitas', 'REC', 'date', 'Receita'],
            'asset_document' => ['Patrimônio', 'PAT', 'acquisition_date', 'Bem patrimonial'],
            'collective_purchase_document' => ['Compras', 'COL', 'purchase_date', 'Compra coletiva'],
            'direct_purchase_invoice' => ['Compras', 'COM', 'purchase_date', 'Compra direta'],
            'provider_payment_receipt' => ['Pagamentos', 'PPR', 'request_date', 'Pagamento a prestador'],
            'service_order_payment_receipt' => ['Pagamentos', 'POS', 'payment_date', 'Pagamento de serviço'],
            'sales_project_document' => ['Projetos', 'PRO', 'start_date', 'Projeto de venda'],
            'service_order_receipt' => ['Ordens de serviço', 'OS', 'execution_date', 'Ordem de serviço'],
            'service_evidence' => ['Evidências de serviços', 'OS', 'execution_date', 'Execução de serviço'],
            'associate_receipt', 'associate_receipt_payments' => ['Comprovantes', 'ENT', 'issued_at', 'Entrega'],
            'customer_billing_receipt' => ['Comprovantes', 'FAT', 'issued_at', 'Faturamento'],
            'delivery_conference_signed' => ['Conferências', 'CON', 'issued_at', 'Conferência'],
            default => [null, null, null, null],
        };
        if ($category === null || ! $record->getKey()) {
            return null;
        }

        $date = $record->getAttribute($dateField)
            ?? ($record instanceof ServiceOrder ? $record->scheduled_at : null)
            ?? $owner->getAttribute('document_date')
            ?? $record->created_at
            ?? $owner->created_at
            ?? now();
        $month = $date instanceof DateTimeInterface ? $date->format('Y-m') : CarbonImmutable::parse($date)->format('Y-m');
        $reference = $record instanceof ServiceOrder ? preg_replace('/^OS-/i', '', (string) $record->number) : '';
        $reference = trim(preg_replace('/[^A-Za-z0-9-]+/', '-', (string) $reference) ?? '', '-')
            ?: str_pad((string) $record->getKey(), 4, '0', STR_PAD_LEFT);

        $title = match (true) {
            $record instanceof Expense => $record->description,
            $record instanceof ServiceOrder => $record->service?->name,
            $record instanceof AssociateReceipt => $record->associate?->display_name,
            $record instanceof CustomerBillingReceipt => $record->recipient_name,
            $record instanceof DeliveryConferenceSheet => $record->project?->driveFolderName(),
            default => $record->getAttribute('title') ?: ($record->getAttribute('name') ?: $record->getAttribute('description')),
        };
        $title = trim(preg_replace('~[\x00-\x1F\x7F\\/:*?"<>|·]+~u', ' ', (string) $title) ?? '');
        $title = Str::limit(Str::squish($title ?: $fallback), 95, '');
        if ($typeKey === 'associate_receipt_payments') {
            $attachment = 'Pagamentos';
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $extension = preg_match('/\A[a-z0-9]{1,12}\z/', $extension) ? '.'.$extension : '';

        return [
            'folders' => [$category, $month],
            'filename' => $prefix.'-'.$reference.' - '.$title.($attachment ? ' · '.$attachment : '').$extension,
        ];
    }
}
