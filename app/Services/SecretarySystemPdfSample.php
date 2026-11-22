<?php

namespace App\Services;

use App\Enums\FinancialReceiptStatus;
use App\Enums\PaymentMethod;

class SecretarySystemPdfSample
{
    public const SUPPORTED = ['deliveries_associate', 'deliveries_product', 'financial_receipt'];

    public function data(string $key): array
    {
        $date = now();
        if ($key === 'financial_receipt') {
            return [
                'receipt' => (object) [
                    'id' => 0, 'status' => FinancialReceiptStatus::ISSUED, 'formatted_number' => 'EXEMPLO',
                    'received_on' => $date, 'issued_at' => $date, 'total_amount' => 60,
                    'payer_name' => 'Pagador de exemplo', 'payer_document' => null,
                    'payment_method' => PaymentMethod::PIX,
                    'payment_reference' => null, 'purpose' => 'Pagamento ilustrativo',
                    'bankAccount' => null, 'chartAccount' => null, 'notes' => 'Amostra sem valor financeiro.',
                    'items' => collect([(object) ['description' => 'Item de exemplo', 'reference' => '01',
                        'quantity' => 2, 'unit' => 'un', 'unit_price' => 30, 'total_amount' => 60]]),
                ],
                'receiverName' => 'Responsável de exemplo', 'amountInWords' => 'sessenta reais',
            ];
        }

        $row = [
            'delivery_date' => $date->format('d/m/Y'), 'project' => 'Projeto de exemplo', 'customer' => 'Cliente de exemplo',
            'associate' => 'Associado de exemplo', 'product' => 'Produto de exemplo', 'quantity' => 20,
            'unit_price' => 3, 'gross_value' => 60, 'admin_fee' => 3, 'net_value' => 57,
            'status_value' => 'approved', 'status' => 'Aprovada',
        ];

        return [
            'filters' => ['project' => $row['project']],
            'groups' => [[
                'associate_name' => $row['associate'], 'product_name' => $row['product'], 'unit' => 'kg',
                'deliveries_count' => 1, 'total_quantity' => 20, 'gross_value' => 60, 'admin_fee' => 3,
                'net_value' => 57, 'deliveries' => [$row],
            ]],
            'totals' => ['associates_count' => 1, 'products_count' => 1, 'deliveries_count' => 1,
                'total_quantity' => 20, 'total_gross' => 60, 'total_admin_fee' => 3, 'total_net' => 57],
        ];
    }
}
