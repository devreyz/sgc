<?php

namespace App\Enums;

enum FiscalDocumentType: string
{
    case NFE = 'nfe';
    case NFSE = 'nfse';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NFE => 'NF-e', self::NFSE => 'NFS-e', self::OTHER => 'Outro documento fiscal',
        };
    }
}
