@php
    $receiptConsent = app(\App\Services\ReceiptConsentRenderer::class)->render(
        kind: $consentKind,
        tenant: $tenant,
        project: $project ?? null,
        receipt: $receipt ?? null,
        financial: $consentFinancial ?? [],
        associate: $associate ?? null,
        customer: $customer ?? null,
        organization: $organization ?? null,
    );
@endphp

@if((string) $receiptConsent !== '')
<style>
    .receipt-consent { margin-top: 20px; color: #333; font-size: 10.5px; line-height: 1.55; page-break-inside: avoid; }
    .receipt-consent p { margin: 0 0 12px; text-align: left; }
    .receipt-consent table { width: 100%; margin-top: 26px; border-collapse: collapse; page-break-inside: avoid; }
    .receipt-consent td { width: 50%; padding: 0 12px 0 0; vertical-align: top; }
    .receipt-consent td + td { padding-right: 0; padding-left: 12px; }
    .receipt-signature { width: 100%; text-align: left; }
    .receipt-signature .sig-line { margin-top: 36px; padding-top: 5px; border-top: 1px solid #333; font-size: 11px; font-weight: 700; }
    .receipt-signature .sig-role { margin-top: 2px; color: #555; font-size: 9px; }
    .receipt-signature .sig-doc { margin-top: 1px; color: #666; font-size: 8.5px; }
</style>
{!! $receiptConsent !!}
@endif
