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
        $obligations = ($order->execution?->obligations ?? collect())->where('status', '!=', 'cancelled');
        if ($obligations->isEmpty()) {
            return 'Concluído';
        }
        $pending = $obligations->where('status', '!=', 'paid');
        $receivable = $pending->contains('direction', 'receivable');
        $payable = $pending->contains('direction', 'payable');

        return match (true) {
            $receivable && $payable => 'A receber e a pagar',
            $receivable => 'Falta receber do cliente',
            $payable => 'Falta pagar o prestador',
            default => 'Quitado',
        };
    }
}
