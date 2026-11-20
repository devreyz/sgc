@extends('pdf.partials.header')
@section('extra-styles')
    .service-order-copy { font-size: 9px; line-height: 1.3; }
    .service-order-copy-next { page-break-before: always; }
    .service-order-copy-label { text-align: right; font-weight: bold; margin-bottom: 6px; }
    .service-order-copy .info-box { padding: 7px 9px; margin-bottom: 5px; }
    .service-order-copy .info-box td { padding: 2px 4px; }
    .service-order-copy .section-title { margin: 7px 0 4px; padding-bottom: 2px; }
    .service-order-copy table.data-table { margin-bottom: 5px !important; }
    .service-order-copy table.data-table thead th { padding: 3px 5px !important; }
    .service-order-copy table.data-table tbody td { padding: 3px 5px !important; line-height: 1.25 !important; }
    .service-order-copy table.data-table tr, .service-order-signatures { page-break-inside: avoid; }
    .service-order-signatures { margin-top: 10px; }
@endsection
@section('content')
    {!! $body !!}
@endsection
