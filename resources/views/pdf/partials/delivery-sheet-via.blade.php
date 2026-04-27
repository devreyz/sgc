{{--
  Partial: pdf/partials/delivery-sheet-via.blade.php
  Variáveis esperadas do contexto pai:
    $colA, $colB, $maxRows : produtos divididos em duas colunas
    $isPortrait             : bool
  Variáveis herdadas do escopo da view pai (via Blade include):
    $orgName, $orgCnpj, $displayName, $clientCity
    $hasLogo, $logoSrc

  NOTA DomPDF: margin em tabelas é ignorado — usar <div style="height:Xpx"> como espaçador
--}}
@php
    $padH = $isPortrait ? '0' : '5mm';
@endphp
<table style="width:100%;border-collapse:collapse;"><tbody><tr>
<td style="padding:0 {{ $padH }};vertical-align:top;">

{{-- ── Cabeçalho ── --}}
<table style="width:100%;border-collapse:collapse;border-bottom:1.5px solid #1a1a1a;padding-bottom:5px;">
<tbody><tr>
    @if($hasLogo)
    <td style="width:{{ $isPortrait ? '20mm' : '14mm' }};vertical-align:middle;">
        <img src="{{ $logoSrc }}" alt=""
             style="max-width:{{ $isPortrait ? '17mm' : '12mm' }};max-height:{{ $isPortrait ? '17mm' : '12mm' }};display:block;">
    </td>
    @endif
    <td style="vertical-align:middle;padding-left:5px;">
        <div class="hdr-title">Ficha de Entrega - {{ $displayName }}</div>
        <div class="hdr-org">{{ $orgName }}</div>
        @if($orgCnpj)<div class="hdr-cnpj">CNPJ: {{ $orgCnpj }}</div>@endif
    </td>
    <td style="text-align:right;white-space:nowrap;padding-left:6px;vertical-align:middle;">
        <span class="hdr-date-lbl">Data:</span>
        <span class="hdr-date-blank">&nbsp;</span>
    </td>
</tr></tbody></table>

{{-- espaçador pós-cabeçalho --}}
<div style="height:7px;font-size:1px;">&nbsp;</div>

{{-- ── Info: cliente e produtor ── --}}
<table class="info">
   
    @if($clientCity)
    <tr>
        <td class="lbl">Cidade</td>
        <td class="val">{{ $clientCity }}</td>
    </tr>
    @endif
    <tr>
        <td class="lbl">Produtor</td>
        <td class="val blank">&nbsp;</td>
    </tr>
</table>

{{-- espaçador pós-info --}}
<div style="height:8px;font-size:1px;">&nbsp;</div>

{{-- ── Tabela de produtos (2 colunas) ── --}}
<table style="width:100%;border-collapse:collapse;"><tbody><tr>

{{-- Coluna A --}}
<td style="vertical-align:top;width:50%;padding-right:4px;">
<table class="pt">
<thead><tr>
    <th>#</th>
    <th>Produto</th>
    <th class="c" style="width:{{ $isPortrait ? '52px' : '44px' }}">Quantidade</th>
</tr></thead>
<tbody>
@foreach(range(0, $maxRows - 1) as $i)
@php $p = $colA[$i] ?? null; @endphp
<tr>
    <td style="width:14px;color:#888;font-size:{{ $isPortrait ? '7.5px' : '7px' }}">{{ $p ? ($i + 1) : '' }}</td>
    <td>{{ $p ? $p['name'] : '' }}</td>
    <td class="td-qty">&nbsp;</td>
</tr>
@endforeach
</tbody>
</table>
</td>

{{-- Coluna B --}}
<td style="vertical-align:top;width:50%;padding-left:4px;">
<table class="pt">
<thead><tr>
    <th>#</th>
    <th>Produto</th>
    <th class="c" style="width:{{ $isPortrait ? '52px' : '44px' }}">Quantidade</th>
</tr></thead>
<tbody>
@foreach(range(0, $maxRows - 1) as $i)
@php $p = $colB[$i] ?? null; @endphp
<tr>
    <td style="width:14px;color:#888;font-size:{{ $isPortrait ? '7.5px' : '7px' }}">{{ $p ? ($i + count($colA) + 1) : '' }}</td>
    <td>{{ $p ? $p['name'] : '' }}</td>
    <td class="td-qty">&nbsp;</td>
</tr>
@endforeach
</tbody>
</table>
</td>

</tr></tbody></table>

{{-- espaçador pós-produtos --}}
<div style="height:10px;font-size:1px;">&nbsp;</div>
<!-- 
{{-- ── Total Líquido ── --}}
<table style="width:100%;border-collapse:collapse;">
    <tr>
        <td class="tot-lbl">Total Líquido (R$):</td>
        <td class="tot-val2">&nbsp;</td>
    </tr>
</table> -->

</td></tr></tbody></table>
