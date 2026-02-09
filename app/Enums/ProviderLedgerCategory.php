<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProviderLedgerCategory: string implements HasLabel
{
    case SERVICO_PRESTADO = 'servico_prestado';
    case PAGAMENTO_RECEBIDO = 'pagamento_recebido';
    case AJUSTE = 'ajuste';
    case OUTRO = 'outro';

    public function getLabel(): string
    {
        return match ($this) {
            self::SERVICO_PRESTADO => 'Serviço Prestado',
            self::PAGAMENTO_RECEBIDO => 'Pagamento Recebido',
            self::AJUSTE => 'Ajuste',
            self::OUTRO => 'Outro',
        };
    }
}
