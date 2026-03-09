<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PdvSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'pdv_customer_id' => $this->pdv_customer_id,
            'customer_name' => $this->customer_name,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'discount_percent' => $this->discount_percent,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'amount_paid' => $this->amount_paid,
            'change_amount' => $this->change_amount,
            'status' => $this->status,
            'is_fiado' => $this->is_fiado,
            'fiado_due_date' => $this->fiado_due_date,
            'interest_rate' => $this->interest_rate,
            'notes' => $this->notes,
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at,
            'display_name' => $this->display_name,
            'fiado_remaining' => $this->fiado_remaining,
            'items' => PdvSaleItemResource::collection($this->whenLoaded('items')),
            'payments' => PdvPaymentResource::collection($this->whenLoaded('payments')),
            'fiado_payments' => PdvFiadoPaymentResource::collection($this->whenLoaded('fiadoPayments')),
            'customer' => new PdvCustomerBriefResource($this->whenLoaded('customer')),
            'creator' => new UserBriefResource($this->whenLoaded('creator')),
        ];
    }
}
