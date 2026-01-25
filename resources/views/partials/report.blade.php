<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="date">Gerado em: {{ $generatedAt }}</div>
    </div>

    <div class="content">
        @if(count($data) > 0)
            <table>
                <thead>
                    <tr>
                        @foreach($columns as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $row)
                        <tr>
                            @foreach(array_keys($columns) as $field)
                                <td>
                                    @php
                                        $value = $row[$field] ?? '-';
                                        if ($value instanceof \Carbon\Carbon) {
                                            $value = $value->format('d/m/Y');
                                        } elseif (is_bool($value)) {
                                            $value = $value ? 'Sim' : 'Não';
                                        } elseif (is_array($value)) {
                                            $value = implode(', ', $value);
                                        } elseif ($value === null) {
                                            $value = '-';
                                        }
                                    @endphp
                                    {{ $value }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="summary">
                <strong>Total de registros:</strong> {{ count($data) }}
            </div>
        @else
            <div class="no-data">
                Nenhum registro encontrado.
            </div>
        @endif
    </div>

    <div class="footer">
        Sistema de Gestão Cooperativa - SGC | {{ $title }} | Página 1
    </div>
</body>
</html>