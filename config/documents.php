<?php

return [
    /*
    | Reemissão preguiçosa: nenhum lote é processado. A verificação acontece
    | somente quando um PDF arquivado é aberto ou solicitado pelo usuário.
    | Essa atualização é apenas visual e nunca troca a identidade ou o QR.
    */
    'automatic_refresh_on_view' => (bool) env('DOCUMENT_AUTO_REFRESH_ON_VIEW', true),

    // Use 1, 3 ou outro intervalo adequado à política documental da organização.
    'refresh_after_months' => (int) env('DOCUMENT_REFRESH_AFTER_MONTHS', 3),

    // Altere quando o layout/gerador exigir recriar o PDF mantendo o mesmo QR.
    'renderer_version' => env('DOCUMENT_RENDER_VERSION', env('APP_VERSION', '2026.09.19')),
];
