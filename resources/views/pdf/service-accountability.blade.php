@extends('pdf.partials.header')
@section('extra-styles')
    .service-summary { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 8px; }
    .service-summary td { padding: 5px 8px; border: 1px solid #cfd3d6; background: #f3f6f4; vertical-align: top; }
    .service-summary strong { display: block; margin-top: 2px; }
    .accountability .data-table thead { display: table-header-group; }
    .accountability .data-table tr { page-break-inside: avoid; }
@endsection
@section('content')
    @php
        $sections = $visible_sections ?? ['summary', 'orders', 'monthly', 'payments', 'expenses'];
        $columns = $visible_columns ?? ['beneficiary', 'provider', 'quantity', 'balances'];
        $period = \Illuminate\Support\Carbon::parse($summary['from'])->format('d/m/Y').' a '.\Illuminate\Support\Carbon::parse($summary['to'])->format('d/m/Y');
    @endphp
    <div class="section-title">Período {{ $period }}</div>
    {!! \App\Services\Services\ServiceDocumentService::extraContent($system_pdf_template ?? null, 'intro') !!}
    @include('services._accountability', ['pdfMode' => true])
    {!! \App\Services\Services\ServiceDocumentService::extraContent($system_pdf_template ?? null, 'closing') !!}
@endsection
