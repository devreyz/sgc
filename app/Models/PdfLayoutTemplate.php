<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdfLayoutTemplate extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'layout_type',
        'content',
        'is_default',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    const LAYOUT_TYPES = [
        'header' => 'Cabeçalho',
        'footer' => 'Rodapé',
        'both'   => 'Cabeçalho + Rodapé',
    ];

    /**
     * Variables available in layout templates.
     */
    public static function getAvailableVariables(): array
    {
        return [
            '{{cooperativa.nome}}'      => 'Nome da Cooperativa',
            '{{cooperativa.cnpj}}'      => 'CNPJ da Cooperativa',
            '{{cooperativa.endereco}}'  => 'Endereço',
            '{{cooperativa.cidade}}'    => 'Cidade',
            '{{cooperativa.estado}}'    => 'Estado',
            '{{cooperativa.telefone}}'  => 'Telefone',
            '{{data.hoje}}'             => 'Data de Hoje',
            '{{data.hoje_extenso}}'     => 'Data por Extenso',
            '{{data.mes_atual}}'        => 'Mês Atual',
            '{{data.ano_atual}}'        => 'Ano Atual',
            '{{documento.titulo}}'      => 'Título do Documento',
            '{{pagina.atual}}'          => 'Página Atual',
            '{{pagina.total}}'          => 'Total de Páginas',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHeaders($query)
    {
        return $query->whereIn('layout_type', ['header', 'both']);
    }

    public function scopeFooters($query)
    {
        return $query->whereIn('layout_type', ['footer', 'both']);
    }
}
