<?php

namespace App\Services\Services;

use App\Models\ServiceOrder;

class ServiceHumanStatusResolver
{
    public function resolve(ServiceOrder $order): string
    {
        $status = $order->operational_status;
        if ($status === 'in_progress') {
            return 'Em execução';
        }
        if ($status === 'submitted') {
            return 'Aguardando conferência';
        }
        if ($status === 'rejected') {
            return 'Correção solicitada';
        }
        if ($status === 'cancelled') {
            return $order->execution?->validated_at ? 'Anulada' : 'Cancelada';
        }
        if ($status !== 'validated') {
            return $status === 'scheduled' ? 'Agendado' : 'Rascunho';
        }
        return match ($this->financialState($order)) {
            'incoming' => 'Falta receber do cliente',
            'outgoing' => 'Falta pagar o prestador',
            'both' => 'A receber e a pagar',
            'settled' => 'Quitado',
            default => 'Concluído',
        };
    }

    public function financialState(ServiceOrder $order): ?string
    {
        if ($order->operational_status !== 'validated') {
            return null;
        }
        $obligations = ($order->execution?->obligations ?? collect())->where('status', '!=', 'cancelled');
        if ($obligations->isEmpty()) {
            return 'complete';
        }
        $pending = $obligations->where('status', '!=', 'paid');
        $receivable = $pending->contains('direction', 'receivable');
        $payable = $pending->contains('direction', 'payable');

        return match (true) {
            $receivable && $payable => 'both',
            $receivable => 'incoming',
            $payable => 'outgoing',
            default => 'settled',
        };
    }
}
