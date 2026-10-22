@extends('pdf.partials.header')
@php($suppress_internal_footer = true)
@php($hide_header_email = true)
@section('content')
@include('pdf.partials.billing-dossier-report-body')
@endsection
