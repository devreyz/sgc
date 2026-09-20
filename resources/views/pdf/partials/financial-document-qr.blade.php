@php
    $verificationIdentity = isset($receipt) && $receipt instanceof \Illuminate\Database\Eloquent\Model
        ? app(\App\Services\FinancialDocumentIdentityService::class)->ensure($receipt, auth()->user())
        : null;
    $verificationQr = $verificationIdentity
        ? app(\App\Services\FinancialDocumentIdentityService::class)->qrDataUri($verificationIdentity, 150)
        : null;
@endphp

@if($verificationIdentity && $verificationQr)
    <table style="width:100%;margin-top:8px;border:1px solid #d1d5db;border-collapse:collapse;background:#f9fafb">
        <tr>
            <td style="width:72px;padding:5px;vertical-align:middle">
                <img src="{{ $verificationQr }}" alt="QR Code de validação" style="display:block;width:62px;height:62px">
            </td>
            <td style="padding:6px;vertical-align:middle;font-family:'DejaVu Sans',Arial,sans-serif;color:#374151">
                <strong style="display:block;font-size:8.5pt;color:#166534">Validar comprovante</strong>
                <span style="display:block;margin-top:2px;font-size:7pt;line-height:1.35">Escaneie para consultar a autenticidade e a situação atual deste documento no SGC.</span>
                <span style="display:block;margin-top:3px;font-size:7pt;font-weight:bold;letter-spacing:.04em">{{ $verificationIdentity->reference_code }}</span>
            </td>
        </tr>
    </table>
@endif
