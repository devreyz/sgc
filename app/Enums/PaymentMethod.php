<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case DINHEIRO = 'dinheiro';
    case PIX = 'pix';
    case TRANSFERENCIA = 'transferencia';
    case BOLETO = 'boleto';
    case CARTAO = 'cartao';
    case CHEQUE = 'cheque';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::DINHEIRO => 'Dinheiro',
            self::PIX => 'PIX',
            self::TRANSFERENCIA => 'Transferência',
            self::BOLETO => 'Boleto',
            self::CARTAO => 'Cartão',
            self::CHEQUE => 'Cheque',
            self::OUTRO => 'Outro',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DINHEIRO => 'heroicon-o-banknotes',
            self::PIX => 'heroicon-o-qr-code',
            self::TRANSFERENCIA => 'heroicon-o-arrow-path',
            self::BOLETO => 'heroicon-o-document-text',
            self::CARTAO => 'heroicon-o-credit-card',
            self::CHEQUE => 'heroicon-o-document',
            self::OUTRO => 'heroicon-o-currency-dollar',
        };
    }
}
