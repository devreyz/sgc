<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PdvCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cpf_cnpj' => $this->cpf_cnpj,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'credit_limit' => $this->credit_limit,
            'credit_balance' => $this->credit_balance,
            'status' => $this->status,
            'fiado_balance' => $this->when(
                $this->relationLoaded('sales'),
                fn () => $this->fiado_balance
            ),
            'sales' => PdvSaleResource::collection($this->whenLoaded('sales')),
        ];
    }
}
