<?php

namespace App\Enums;

enum DeliveryConferenceGroupingMode: string
{
    case CUSTOMER = 'customer';
    case ORGANIZATION_DETAILED = 'organization_detailed';
    case ORGANIZATION_CONSOLIDATED = 'organization_consolidated';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Por produto',
            self::ORGANIZATION_DETAILED => 'Detalhado por cliente',
            self::ORGANIZATION_CONSOLIDATED => 'Consolidado por produto',
        };
    }
}
