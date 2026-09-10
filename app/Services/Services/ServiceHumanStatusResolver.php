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
            return 'Cancelado';
        }
        if ($status !== 'validated') {
            return $status === 'scheduled' ? 'Agendado' : 'Rascunho';
        }
        $obligations = $order->execution?->obligations ?? collect();
        if ($obligations->isEmpty()) {
            return 'Concluído';
        }
        if ($obligations->every(fn ($o) => $o->status === 'paid')) {
            return 'Quitado';
        }
        if ($obligations->contains(fn ($o) => $o->status === 'partially_paid')) {
            return 'Parcialmente pago';
        }

        return 'Aguardando pagamento';
    }
}
