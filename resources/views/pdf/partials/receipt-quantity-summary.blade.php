{{-- Cell padding is intentional: DomPDF does not reliably pad display:table wrappers. --}}
<table class="receipt-quantity-summary" role="presentation" style="width:100%; border-collapse:separate; border-spacing:0; margin:10px 0 12px; border:1px solid #cfd3d6; border-left:3px solid #64786f; background:#f7f9f8; page-break-inside:avoid;">
    <tr>
        <td style="width:65%; padding:10px 12px; vertical-align:middle;">
            <div style="font-size:9px; font-weight:bold; color:#374151; line-height:1.5;">Quantidade total entregue no período</div>
            <div style="margin-top:3px; font-size:8px; color:#687078; line-height:1.5;">Totais separados por unidade de medida</div>
        </td>
        <td style="width:35%; padding:10px 12px; vertical-align:middle; text-align:right; font-size:12px; font-weight:bold; color:#244c35; line-height:1.65;">
            @include('pdf.partials.receipt-quantity-total')
        </td>
    </tr>
</table>
