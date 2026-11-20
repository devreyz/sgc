<div class="svc-review-table-wrap">
    <table class="svc-review-table" aria-label="Leituras do medidor">
        <tbody>
            <tr><th scope="row">{{ $reading['start_label'] }}</th><td>{{ $quantity($reading['start'], $reading['unit']) }}</td></tr>
            @foreach($reading['pauses'] as $pause)
                <tr><th scope="row">Pausa {{ $loop->iteration }}</th>
                    <td>{{ $quantity($pause['stop'], $reading['unit']) }} até {{ $quantity($pause['resume'], $reading['unit']) }} · desconto de {{ $quantity($pause['resume'] - $pause['stop'], $reading['unit']) }}</td>
                </tr>
            @endforeach
            <tr><th scope="row">{{ $reading['end_label'] }}</th><td>{{ $quantity($reading['end'], $reading['unit']) }}</td></tr>
        </tbody>
    </table>
</div>
