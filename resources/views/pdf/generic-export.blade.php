@extends('pdf.partials.header')

@section('content')
@php
    $columnCount = max(1, count($columns ?? []));
@endphp

<div class="info-box">
    <table>
        <tr>
            <td class="label">Registros exportados</td>
            <td class="value">{{ count($data ?? []) }}</td>
            <td class="label">Gerado em</td>
            <td class="value">{{ $generatedAt ?? now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
</div>

<style>
    table.generic-export-table thead { display: table-header-group; }
    table.generic-export-table tfoot { display: table-row-group; }
    table.generic-export-table tr { page-break-inside: avoid; break-inside: avoid; }
</style>

<table class="data-table generic-export-table">
    <thead>
        <tr>
            @foreach($columns ?? [] as $label)
                <th>{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($data ?? [] as $row)
            <tr>
                @foreach(array_keys($columns ?? []) as $field)
                    <td>{{ $row[$field] ?? '-' }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ $columnCount }}" class="text-center">Nenhum registro encontrado.</td>
            </tr>
        @endforelse
    </tbody>
</table>
@endsection
