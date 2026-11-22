@extends('pdf.partials.header')
@section('extra-styles')
    @include('pdf.partials.service-document-style')
@endsection
@section('content')
    {!! $body !!}
@endsection
