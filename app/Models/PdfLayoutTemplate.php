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
        'estimated_height_mm',
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
        'both' => 'Cabeçalho + Rodapé',
        'cover' => 'Capa',
        'back_cover' => 'Contracapa',
    ];

    /**
     * Returns all pre-built seed templates (10 headers, 10 footers, 5 covers, 5 back-covers).
     * All templates use {{cor.primaria}}, {{cor.destaque}} and {{cooperativa.logo_img}}
     * so they adapt automatically to the selected color theme.
     */
    public static function getSeedTemplates(): array
    {
        return [
            // ═══════════════ 10 CABEÇALHOS ═══════════════

            // H1 – Clássico com logo (DEFAULT)
            [
                'name' => 'Cabeçalho Clássico',
                'layout_type' => 'header',
                'is_default' => true,
                'estimated_height_mm' => 28,
                'content' => '<table style="width:100%;border-collapse:collapse;border-bottom:2px solid {{cor.primaria}};padding-bottom:8px;margin-bottom:10px;font-family:Arial,sans-serif;"><tr><td style="vertical-align:middle;width:55%;"><table style="border-collapse:collapse;"><tr><td style="vertical-align:middle;padding-right:8px;">{{cooperativa.logo_img}}</td><td style="vertical-align:middle;"><div style="font-size:14px;font-weight:bold;color:{{cor.primaria}};">{{cooperativa.nome}}</div><div style="font-size:8px;color:#6b7280;">CNPJ: {{cooperativa.cnpj}}</div></td></tr></table></td><td style="text-align:right;vertical-align:middle;font-size:8px;color:#4b5563;">{{cooperativa.cidade}}/{{cooperativa.estado}}<br>Tel: {{cooperativa.telefone}}</td></tr></table>',
            ],

            // H2 – Barra superior colorida
            [
                'name' => 'Cabeçalho Barra Superior',
                'layout_type' => 'header',
                'is_default' => false,
                'estimated_height_mm' => 22,
                'content' => '<div style="background:{{cor.primaria}};padding:8px 12px;margin-bottom:10px;font-family:Arial,sans-serif;"><table style="width:100%;border-collapse:collapse;"><tr><td style="vertical-align:middle;"><table style="border-collapse:collapse;"><tr><td style="vertical-align:middle;padding-right:8px;">{{cooperativa.logo_img}}</td><td style="vertical-align:middle;"><div style="font-size:13px;font-weight:bold;color:#fff;">{{cooperativa.nome}}</div><div style="font-size:7.5px;color:rgba(255,255,255,0.75);">CNPJ: {{cooperativa.cnpj}}</div></td></tr></table></td><td style="text-align:right;vertical-align:middle;"><div style="font-size:9px;font-weight:600;color:#fff;">{{documento.titulo}}</div><div style="font-size:7.5px;color:rgba(255,255,255,0.75);">{{data.hoje}}</div></td></tr></table></div>',
            ],

            // H3 – Dupla borda (primária em cima, destaque embaixo)
            [
                'name' => 'Cabeçalho Dupla Borda',
                'layout_type' => 'header',
                'is_default' => false,
                'estimated_height_mm' => 20,
                'content' => '<div style="font-family:Arial,sans-serif;margin-bottom:10px;border-top:3px solid {{cor.primaria}};border-bottom:1px solid {{cor.destaque}};padding:5px 0;"><table style="width:100%;border-collapse:collapse;"><tr><td style="vertical-align:middle;"><table style="border-collapse:collapse;"><tr><td style="vertical-align:middle;padding-right:8px;">{{cooperativa.logo_img}}</td><td style="vertical-align:middle;"><span style="font-size:13px;font-weight:bold;color:{{cor.primaria}};">{{cooperativa.nome}}</span><br><span style="font-size:7.5px;color:#6b7280;">CNPJ: {{cooperativa.cnpj}}</span></td></tr></table></td><td style="text-align:right;vertical-align:middle;font-size:8px;color:#4b5563;">{{documento.titulo}}<br>{{data.hoje}}</td></tr></table></div>',
            ],

            // H4 – Corporativo completo com fundo suave
            [
                'name' => 'Cabeçalho Corporativo Completo',
                'layout_type' => 'header',
                'is_default' => false,
                'estimated_height_mm' => 28,
                'content' => '<div style="border:1px solid #d1d5db;border-radius:4px;padding:8px 12px;margin-bottom:10px;font-family:Arial,sans-serif;background:#f9fafb;"><table style="width:100%;border-collapse:collapse;"><tr><td style="vertical-align:middle;"><table style="border-collapse:collapse;"><tr><td style="vertical-align:middle;padding-right:8px;">{{cooperativa.logo_img}}</td><td style="vertical-align:middle;"><div style="font-size:14px;font-weight:bold;color:{{cor.primaria}};">{{cooperativa.nome}}</div></td></tr></table></td><td style="text-align:right;vertical-align:middle;font-size:8px;color:#374151;">CNPJ: {{cooperativa.cnpj}}<br>{{cooperativa.endereco}}<br>{{cooperativa.cidade}}/{{cooperativa.estado}} | {{cooperativa.telefone}}</td></tr></table><div style="border-top:2px solid {{cor.primaria}};margin-top:6px;"></div></div>',
            ],

            // H5 – Formal centralizado
            [
                'name' => 'Cabeçalho Formal Centralizado',
                'layout_type' => 'header',
                'is_default' => false,
                'estimated_height_mm' => 24,
                'content' => '<div style="font-family:Arial,sans-serif;text-align:center;margin-bottom:12px;"><div style="margin-bottom:4px;">{{cooperativa.logo_img}}</div><p style="font-size:15px;font-weight:bold;color:{{cor.primaria}};margin:0 0 3px;text-transform:uppercase;letter-spacing:0.5px;">{{cooperativa.nome}}</p><p style="font-size:8px;color:#6b7280;margin:0 0 5px;">CNPJ: {{cooperativa.cnpj}} | {{cooperativa.cidade}}/{{cooperativa.estado}}</p><div style="border-top:3px double {{cor.primaria}};border-bottom:1px solid {{cor.destaque}};height:3px;"></div></div>',
            ],

            // H6 – Barra lateral colorida
            [
                'name' => 'Cabeçalho Barra Lateral',
                'layout_type' => 'header',
                'is_default' => false,
                'estimated_height_mm' => 20,
                'content' => '<div style="font-family:Arial,sans-serif;margin-bottom:10px;border-left:5px solid {{cor.primaria}};padding-left:10px;"><table style="width:100%;border-collapse:collapse;"><tr><td style="vertical-align:middle;"><p style="font-size:13px;font-weight:bold;color:{{cor.primaria}};margin:0;">{{cooperativa.nome}}</p><p style="font-size:8px;color:#6b7280;margin:2px 0 0;">CNPJ: {{cooperativa.cnpj}} | {{cooperativa.telefone}}</p></td><td style="text-align:right;vertical-align:middle;"><p style="font-size:9px;color:#374151;margin:0;">{{documento.titulo}}</p><p style="font-size:7px;color:#9ca3af;margin:2px 0 0;">{{data.hoje}}</p></td></tr></table><div style="border-bottom:1px solid {{cor.destaque}};margin-top:6px;"></div></div>',
            ],

            // H7 – Dupla camada (barra escura + info abaixo)
            [
                'name' => 'Cabeçalho Dupla Camada',
                'layout_type' => 'header',
                'is_default' => false,
                'estimated_height_mm' => 26,
                'content' => '<div style="font-family:Arial,sans-serif;margin-bottom:10px;"><div style="background:{{cor.primaria}};padding:5px 10px;"><table style="width:100%;border-collapse:collapse;"><tr><td style="font-size:13px;font-weight:bold;color:#fff;">{{cooperativa.nome}}</td><td style="text-align:right;font-size:8px;color:rgba(255,255,255,0.8);">CNPJ: {{cooperativa.cnpj}}</td></tr></table></div><div style="border-bottom:2px solid {{cor.destaque}};padding:3px 10px;background:#f8f9fa;"><span style="font-size:7.5px;color:#6b7280;">{{cooperativa.endereco}} — {{cooperativa.cidade}}/{{cooperativa.estado}} | Tel: {{cooperativa.telefone}}</span></div></div>',
            ],

            // H8 – Escuro (dark)
            [
                'name' => 'Cabeçalho Escuro',
                'layout_type' => 'header',
                'is_default' => false,
                'estimated_height_mm' => 22,
                'content' => '<div style="background:#111827;padding:8px 12px;margin-bottom:10px;font-family:Arial,sans-serif;border-bottom:2px solid {{cor.primaria}};"><table style="width:100%;border-collapse:collapse;"><tr><td style="vertical-align:middle;"><p style="font-size:13px;font-weight:700;color:#fff;margin:0;">{{cooperativa.nome}}</p><p style="font-size:7.5px;color:#9ca3af;margin:2px 0 0;">CNPJ: {{cooperativa.cnpj}}</p></td><td style="text-align:right;vertical-align:middle;"><p style="font-size:9px;color:#e5e7eb;margin:0;">{{documento.titulo}}</p><p style="font-size:7px;color:#6b7280;margin:2px 0 0;">{{data.hoje}}</p></td></tr></table></div>',
            ],

            // H9 – Compacto linha única
            [
                'name' => 'Cabeçalho Compacto',
                'layout_type' => 'header',
                'is_default' => false,
                'estimated_height_mm' => 14,
                'content' => '<div style="font-family:Arial,sans-serif;margin-bottom:10px;border-bottom:1px solid {{cor.primaria}};padding-bottom:4px;"><table style="width:100%;border-collapse:collapse;"><tr><td style="font-size:10px;font-weight:bold;color:{{cor.primaria}};">{{cooperativa.nome}}</td><td style="text-align:center;font-size:8px;color:#6b7280;">CNPJ: {{cooperativa.cnpj}}</td><td style="text-align:right;font-size:8px;color:#374151;">{{documento.titulo}} — {{data.hoje}}</td></tr></table></div>',
            ],

            // H10 – Tabular bicolor com logo
            [
                'name' => 'Cabeçalho Tabular Bicolor',
                'layout_type' => 'header',
                'is_default' => false,
                'estimated_height_mm' => 22,
                'content' => '<div style="font-family:Arial,sans-serif;margin-bottom:10px;border-bottom:2px solid {{cor.destaque}};"><table style="width:100%;border-collapse:collapse;"><tr style="background:{{cor.primaria}};"><td style="padding:6px 8px;width:60%;"><table style="border-collapse:collapse;"><tr><td style="vertical-align:middle;padding-right:8px;">{{cooperativa.logo_img}}</td><td style="vertical-align:middle;"><p style="font-size:13px;font-weight:bold;color:#fff;margin:0;">{{cooperativa.nome}}</p><p style="font-size:7.5px;color:rgba(255,255,255,0.75);margin:1px 0 0;">{{cooperativa.cidade}}/{{cooperativa.estado}}</p></td></tr></table></td><td style="padding:6px 8px;text-align:right;"><p style="font-size:9px;font-weight:600;color:#fff;margin:0;">{{documento.titulo}}</p><p style="font-size:7.5px;color:rgba(255,255,255,0.75);margin:1px 0 0;">CNPJ: {{cooperativa.cnpj}} | {{data.hoje}}</p></td></tr></table></div>',
            ],

            // ═══════════════ 10 RODAPÉS ═══════════════

            // F1 – Clássico (DEFAULT)
            [
                'name' => 'Rodapé Clássico',
                'layout_type' => 'footer',
                'is_default' => true,
                'estimated_height_mm' => 16,
                'content' => '<div style="border-top:2px solid {{cor.primaria}};padding-top:5px;margin-top:10px;font-family:Arial,sans-serif;"><table style="width:100%;border-collapse:collapse;"><tr><td style="font-size:7.5px;color:#6b7280;">{{cooperativa.nome}} | CNPJ: {{cooperativa.cnpj}}</td><td style="text-align:right;font-size:7.5px;color:#9ca3af;">Página {{pagina.atual}} de {{pagina.total}}</td></tr></table></div>',
            ],

            // F2 – Barra colorida primária
            [
                'name' => 'Rodapé Barra Colorida',
                'layout_type' => 'footer',
                'is_default' => false,
                'estimated_height_mm' => 16,
                'content' => '<div style="background:{{cor.primaria}};padding:5px 10px;margin-top:10px;font-family:Arial,sans-serif;"><table style="width:100%;border-collapse:collapse;"><tr><td style="font-size:7.5px;color:rgba(255,255,255,0.85);">{{cooperativa.nome}} | {{cooperativa.cidade}}/{{cooperativa.estado}}</td><td style="text-align:center;font-size:7px;color:rgba(255,255,255,0.7);">{{data.hoje}}</td><td style="text-align:right;font-size:8px;color:#fff;font-weight:bold;">Pág. {{pagina.atual}}/{{pagina.total}}</td></tr></table></div>',
            ],

            // F3 – Minimalista
            [
                'name' => 'Rodapé Minimalista',
                'layout_type' => 'footer',
                'is_default' => false,
                'estimated_height_mm' => 12,
                'content' => '<div style="border-top:1px solid {{cor.destaque}};padding-top:4px;margin-top:8px;text-align:center;font-family:Arial,sans-serif;"><span style="font-size:7px;color:#9ca3af;">{{cooperativa.nome}} — {{data.hoje}} — Página {{pagina.atual}} de {{pagina.total}}</span></div>',
            ],

            // F4 – Corporativo completo
            [
                'name' => 'Rodapé Corporativo Completo',
                'layout_type' => 'footer',
                'is_default' => false,
                'estimated_height_mm' => 20,
                'content' => '<div style="border-top:2px solid {{cor.primaria}};padding-top:5px;margin-top:10px;font-family:Arial,sans-serif;"><table style="width:100%;border-collapse:collapse;"><tr><td style="font-size:7px;color:#374151;"><strong style="color:{{cor.primaria}};">{{cooperativa.nome}}</strong> | CNPJ: {{cooperativa.cnpj}}<br>{{cooperativa.endereco}}, {{cooperativa.cidade}}/{{cooperativa.estado}} | Tel: {{cooperativa.telefone}}</td><td style="text-align:right;font-size:7.5px;color:#6b7280;vertical-align:top;">Gerado: {{data.hoje}}<br>Pág. {{pagina.atual}}/{{pagina.total}}</td></tr></table></div>',
            ],

            // F5 – Data destacada
            [
                'name' => 'Rodapé Data Destacada',
                'layout_type' => 'footer',
                'is_default' => false,
                'estimated_height_mm' => 16,
                'content' => '<div style="border-top:1px solid #d1d5db;margin-top:8px;padding-top:4px;font-family:Arial,sans-serif;"><table style="width:100%;border-collapse:collapse;"><tr><td style="font-size:7.5px;color:#6b7280;">{{cooperativa.nome}} — CNPJ: {{cooperativa.cnpj}}</td><td style="text-align:center;font-size:7px;color:#6b7280;">{{data.hoje_extenso}}</td><td style="text-align:right;font-size:8px;font-weight:bold;color:{{cor.primaria}};">{{pagina.atual}} / {{pagina.total}}</td></tr></table></div>',
            ],

            // F6 – Legal com aviso
            [
                'name' => 'Rodapé Legal com Aviso',
                'layout_type' => 'footer',
                'is_default' => false,
                'estimated_height_mm' => 28,
                'content' => '<div style="border-top:2px double {{cor.primaria}};padding-top:4px;margin-top:10px;font-family:Arial,sans-serif;"><p style="font-size:6.5px;color:#6b7280;text-align:justify;margin:0 0 3px;">Documento de uso exclusivo da {{cooperativa.nome}}. Reprodução não autorizada é proibida.</p><table style="width:100%;border-collapse:collapse;"><tr><td style="font-size:7px;color:#374151;">{{cooperativa.nome}} | CNPJ: {{cooperativa.cnpj}}</td><td style="text-align:right;font-size:7px;color:#6b7280;">Pág. {{pagina.atual}} de {{pagina.total}}</td></tr></table></div>',
            ],

            // F7 – Simples (paginação central)
            [
                'name' => 'Rodapé Simples (Paginação)',
                'layout_type' => 'footer',
                'is_default' => false,
                'estimated_height_mm' => 12,
                'content' => '<div style="text-align:center;border-top:1px dashed {{cor.destaque}};padding-top:4px;margin-top:8px;font-family:Arial,sans-serif;"><span style="font-size:8px;color:#6b7280;">— {{pagina.atual}} —</span></div>',
            ],

            // F8 – Três colunas informativas
            [
                'name' => 'Rodapé Três Colunas',
                'layout_type' => 'footer',
                'is_default' => false,
                'estimated_height_mm' => 18,
                'content' => '<div style="margin-top:8px;font-family:Arial,sans-serif;"><div style="background:#f1f5f9;border-top:2px solid {{cor.primaria}};padding:4px 8px;"><table style="width:100%;border-collapse:collapse;"><tr><td style="font-size:7.5px;color:{{cor.primaria}};font-weight:bold;">{{cooperativa.nome}}</td><td style="text-align:center;font-size:7px;color:#6b7280;">Tel: {{cooperativa.telefone}}</td><td style="text-align:right;font-size:7.5px;color:#374151;">Pág <strong>{{pagina.atual}}</strong> / {{pagina.total}}</td></tr></table></div></div>',
            ],

            // F9 – Barra de destaque
            [
                'name' => 'Rodapé Barra de Destaque',
                'layout_type' => 'footer',
                'is_default' => false,
                'estimated_height_mm' => 16,
                'content' => '<div style="background:{{cor.destaque}};padding:5px 10px;margin-top:10px;font-family:Arial,sans-serif;"><table style="width:100%;border-collapse:collapse;"><tr><td style="font-size:7.5px;color:#fff;">{{cooperativa.nome}}</td><td style="text-align:center;font-size:7px;color:rgba(255,255,255,0.8);">{{data.hoje}}</td><td style="text-align:right;font-size:7.5px;color:#fff;font-weight:bold;">{{pagina.atual}}/{{pagina.total}}</td></tr></table></div>',
            ],

            // F10 – Elegante
            [
                'name' => 'Rodapé Elegante',
                'layout_type' => 'footer',
                'is_default' => false,
                'estimated_height_mm' => 14,
                'content' => '<div style="border-top:1px solid #374151;padding-top:5px;margin-top:10px;font-family:Arial,sans-serif;"><table style="width:100%;border-collapse:collapse;"><tr><td style="font-size:7px;color:#4b5563;font-style:italic;">{{cooperativa.nome}} | CNPJ {{cooperativa.cnpj}}</td><td style="text-align:center;font-size:6.5px;color:#9ca3af;">{{data.hoje}}</td><td style="text-align:right;font-size:7.5px;color:{{cor.primaria}};font-weight:bold;">{{pagina.atual}} / {{pagina.total}}</td></tr></table></div>',
            ],
            [
                'name' => 'Capa Formal',
                'layout_type' => 'cover',
                'is_default' => true,
                'estimated_height_mm' => 0,
                'content' => '<table style="width:100%;height:297mm;border-collapse:collapse;font-family:Arial,sans-serif;"><tr><td style="text-align:center;vertical-align:middle;padding:20mm;"><div style="margin-bottom:8mm;">{{cooperativa.logo_img}}</div><div style="border-top:3px solid {{cor.primaria}};border-bottom:3px solid {{cor.primaria}};padding:8mm 0;margin-bottom:12mm;"><p style="font-size:18px;font-weight:bold;color:{{cor.primaria}};margin:0 0 4px;text-transform:uppercase;letter-spacing:1px;">{{cooperativa.nome}}</p><p style="font-size:9px;color:#6b7280;margin:0;">CNPJ: {{cooperativa.cnpj}} | {{cooperativa.cidade}}/{{cooperativa.estado}}</p></div><p style="font-size:28px;font-weight:bold;color:{{cor.primaria}};text-transform:uppercase;margin:0 0 10mm;">{{documento.titulo}}</p><p style="font-size:10px;color:#374151;margin:0 0 4px;">{{cooperativa.cidade}}/{{cooperativa.estado}}</p><p style="font-size:9px;color:#6b7280;margin:0;">{{data.hoje_extenso}}</p></td></tr></table>',
            ],

            // C2 – Barra superior colorida + título abaixo
            [
                'name' => 'Capa Barra Superior',
                'layout_type' => 'cover',
                'is_default' => false,
                'estimated_height_mm' => 0,
                'content' => '<table style="width:100%;height:297mm;border-collapse:collapse;font-family:Arial,sans-serif;"><tr style="height:110mm;"><td style="background:{{cor.primaria}};text-align:center;vertical-align:middle;padding:10mm;"><div style="margin-bottom:5mm;">{{cooperativa.logo_img}}</div><p style="font-size:20px;font-weight:bold;color:#fff;margin:0 0 3px;text-transform:uppercase;">{{cooperativa.nome}}</p><p style="font-size:9px;color:rgba(255,255,255,0.75);margin:0;">CNPJ: {{cooperativa.cnpj}} | {{cooperativa.cidade}}/{{cooperativa.estado}}</p></td></tr><tr><td style="text-align:left;vertical-align:middle;padding:15mm;"><div style="border-left:5px solid {{cor.primaria}};padding-left:10mm;"><p style="font-size:28px;font-weight:bold;color:{{cor.primaria}};margin:0 0 6mm;">{{documento.titulo}}</p><p style="font-size:10px;color:#6b7280;margin:0;">{{data.hoje_extenso}}</p></div></td></tr><tr style="height:18mm;"><td style="border-top:1px solid #e5e7eb;padding:5mm 15mm;vertical-align:bottom;"><p style="font-size:8px;color:#9ca3af;text-align:center;margin:0;">Tel: {{cooperativa.telefone}} | {{cooperativa.endereco}}</p></td></tr></table>',
            ],

            // C3 – Cooperativista com destaque e barra
            [
                'name' => 'Capa Cooperativista',
                'layout_type' => 'cover',
                'is_default' => false,
                'estimated_height_mm' => 0,
                'content' => '<table style="width:100%;height:297mm;border-collapse:collapse;font-family:Arial,sans-serif;"><tr><td style="text-align:center;vertical-align:middle;padding:20mm;"><div style="margin-bottom:6mm;">{{cooperativa.logo_img}}</div><p style="font-size:10px;color:{{cor.destaque}};text-transform:uppercase;letter-spacing:2px;margin:0 0 3mm;">COOPERATIVA</p><p style="font-size:20px;font-weight:bold;color:{{cor.primaria}};margin:0 0 6mm;text-transform:uppercase;">{{cooperativa.nome}}</p><div style="background:{{cor.primaria}};height:3px;width:80px;margin:0 auto 8mm;"></div><p style="font-size:28px;font-weight:bold;color:{{cor.primaria}};text-transform:uppercase;margin:0 0 8mm;">{{documento.titulo}}</p><div style="border:2px solid {{cor.primaria}};padding:3mm 8mm;display:inline-block;"><p style="font-size:10px;color:{{cor.primaria}};font-weight:bold;margin:0;">{{data.hoje_extenso}}</p></div></td></tr></table>',
            ],

            // C4 – Minimalista (fontes leves e muito espaço)
            [
                'name' => 'Capa Minimalista',
                'layout_type' => 'cover',
                'is_default' => false,
                'estimated_height_mm' => 0,
                'content' => '<table style="width:100%;height:297mm;border-collapse:collapse;font-family:Arial,sans-serif;"><tr><td style="text-align:center;vertical-align:middle;padding:20mm;"><div style="border-bottom:1px solid #d1d5db;padding-bottom:8mm;margin-bottom:12mm;"><p style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:3px;margin:0 0 4px;">{{cooperativa.nome}}</p><p style="font-size:8px;color:#d1d5db;margin:0;">CNPJ: {{cooperativa.cnpj}}</p></div><p style="font-size:30px;font-weight:300;color:{{cor.primaria}};margin:0 0 8mm;">{{documento.titulo}}</p><p style="font-size:9px;color:#6b7280;margin:0 0 4px;">{{data.hoje_extenso}}</p><p style="font-size:8px;color:#9ca3af;margin:0;">{{cooperativa.cidade}}/{{cooperativa.estado}}</p></td></tr></table>',
            ],

            // C5 – Relatório oficial em caixa
            [
                'name' => 'Capa Relatório Oficial',
                'layout_type' => 'cover',
                'is_default' => false,
                'estimated_height_mm' => 0,
                'content' => '<table style="width:100%;height:297mm;border-collapse:collapse;font-family:Arial,sans-serif;"><tr><td style="vertical-align:middle;padding:15mm;"><table style="width:100%;border-collapse:collapse;margin-bottom:10mm;"><tr><td style="vertical-align:middle;"><table style="border-collapse:collapse;"><tr><td style="vertical-align:middle;padding-right:8px;">{{cooperativa.logo_img}}</td><td style="vertical-align:middle;"><p style="font-size:13px;font-weight:bold;color:{{cor.primaria}};text-transform:uppercase;margin:0;">{{cooperativa.nome}}</p><p style="font-size:8px;color:#9ca3af;margin:3px 0 0;">CNPJ: {{cooperativa.cnpj}} | Tel: {{cooperativa.telefone}}</p></td></tr></table></td><td style="text-align:right;vertical-align:middle;"><p style="font-size:8px;color:#9ca3af;margin:0;">{{cooperativa.cidade}}/{{cooperativa.estado}}</p><p style="font-size:8px;color:#9ca3af;margin:0;">{{data.hoje}}</p></td></tr></table><div style="border:2px solid {{cor.primaria}};padding:10mm;text-align:center;"><p style="font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:2px;margin:0 0 6mm;">RELATÓRIO OFICIAL</p><p style="font-size:24px;font-weight:bold;color:{{cor.primaria}};text-transform:uppercase;margin:0 0 6mm;">{{documento.titulo}}</p><p style="font-size:10px;color:#374151;margin:0;">{{data.hoje_extenso}}</p></div><p style="font-size:8px;color:#9ca3af;text-align:center;margin-top:8mm;">{{cooperativa.endereco}}</p></td></tr></table>',
            ],

            // ═══════════════ 5 CONTRACAPAS ═══════════════

            // B1 – Simples com contato (DEFAULT)
            [
                'name' => 'Contracapa Simples',
                'layout_type' => 'back_cover',
                'is_default' => true,
                'estimated_height_mm' => 0,
                'content' => '<table style="width:100%;height:297mm;border-collapse:collapse;font-family:Arial,sans-serif;background:#f9fafb;"><tr><td style="text-align:center;vertical-align:middle;padding:20mm;"><div style="border-top:3px solid {{cor.primaria}};border-bottom:3px solid {{cor.primaria}};padding:10mm 0;max-width:140mm;margin:0 auto;"><div style="margin-bottom:5mm;">{{cooperativa.logo_img}}</div><p style="font-size:18px;font-weight:bold;color:{{cor.primaria}};text-transform:uppercase;margin:0 0 5mm;">{{cooperativa.nome}}</p><p style="font-size:9px;color:#6b7280;margin:3px 0;">CNPJ: {{cooperativa.cnpj}}</p><p style="font-size:9px;color:#6b7280;margin:3px 0;">{{cooperativa.endereco}}</p><p style="font-size:9px;color:#6b7280;margin:3px 0;">{{cooperativa.cidade}}/{{cooperativa.estado}}</p><p style="font-size:9px;color:#6b7280;margin:3px 0;">Tel: {{cooperativa.telefone}}</p></div><p style="font-size:8px;color:#9ca3af;margin-top:8mm;">Documento gerado em {{data.hoje_extenso}}</p></td></tr></table>',
            ],

            // B2 – Corporativa escura
            [
                'name' => 'Contracapa Corporativa Escura',
                'layout_type' => 'back_cover',
                'is_default' => false,
                'estimated_height_mm' => 0,
                'content' => '<table style="width:100%;height:297mm;border-collapse:collapse;font-family:Arial,sans-serif;background:#111827;"><tr><td style="text-align:center;vertical-align:middle;padding:20mm;"><p style="font-size:20px;font-weight:bold;color:#fff;text-transform:uppercase;margin:0 0 4px;letter-spacing:1px;">{{cooperativa.nome}}</p><p style="font-size:8px;color:#6b7280;margin:0 0 5mm;">CNPJ: {{cooperativa.cnpj}}</p><div style="background:{{cor.primaria}};height:2px;width:80px;margin:0 auto 8mm;"></div><p style="font-size:9px;color:#9ca3af;margin:4px 0;">{{cooperativa.endereco}}</p><p style="font-size:9px;color:#9ca3af;margin:4px 0;">{{cooperativa.cidade}}/{{cooperativa.estado}}</p><p style="font-size:9px;color:#9ca3af;margin:4px 0;">Tel: {{cooperativa.telefone}}</p><p style="font-size:7.5px;color:#374151;margin-top:10mm;">Emitido em {{data.hoje_extenso}}</p></td></tr></table>',
            ],

            // B3 – Legal com aviso de responsabilidade
            [
                'name' => 'Contracapa Legal',
                'layout_type' => 'back_cover',
                'is_default' => false,
                'estimated_height_mm' => 0,
                'content' => '<table style="width:100%;height:297mm;border-collapse:collapse;font-family:Arial,sans-serif;"><tr><td style="vertical-align:middle;padding:15mm;"><div style="border:1px solid #e5e7eb;border-radius:6px;padding:10mm;background:#f9fafb;"><p style="font-size:11px;font-weight:bold;color:{{cor.primaria}};text-transform:uppercase;border-bottom:1px solid #e5e7eb;padding-bottom:5mm;margin:0 0 6mm;">Informações e Responsabilidade Legal</p><p style="font-size:8px;color:#374151;text-align:justify;line-height:1.6;margin:0 0 5mm;">Este documento foi gerado pelo sistema de gestão da <strong>{{cooperativa.nome}}</strong>, CNPJ {{cooperativa.cnpj}}, e possui validade apenas quando acompanhado da respectiva assinatura dos responsáveis.</p><p style="font-size:8px;color:#374151;text-align:justify;line-height:1.6;margin:0 0 6mm;">Quaisquer dúvidas devem ser direcionadas à {{cooperativa.nome}} pelos canais oficiais de comunicação.</p><div style="border-top:1px solid #e5e7eb;padding-top:6mm;"><p style="font-size:8.5px;font-weight:bold;color:{{cor.primaria}};margin:0 0 2px;">{{cooperativa.nome}}</p><p style="font-size:7.5px;color:#6b7280;margin:2px 0;">CNPJ: {{cooperativa.cnpj}}</p><p style="font-size:7.5px;color:#6b7280;margin:2px 0;">{{cooperativa.endereco}}, {{cooperativa.cidade}}/{{cooperativa.estado}}</p><p style="font-size:7.5px;color:#6b7280;margin:2px 0;">Tel: {{cooperativa.telefone}}</p></div></div><p style="font-size:7px;color:#9ca3af;text-align:center;margin-top:6mm;">Gerado em {{data.hoje_extenso}}</p></td></tr></table>',
            ],

            // B4 – Minimalista (info na base)
            [
                'name' => 'Contracapa Minimalista',
                'layout_type' => 'back_cover',
                'is_default' => false,
                'estimated_height_mm' => 0,
                'content' => '<table style="width:100%;height:297mm;border-collapse:collapse;font-family:Arial,sans-serif;"><tr><td style="text-align:center;vertical-align:bottom;padding:0 20mm 15mm;"><div style="border-top:2px solid {{cor.primaria}};padding-top:8mm;"><p style="font-size:13px;font-weight:bold;color:#374151;text-transform:uppercase;letter-spacing:1px;margin:0 0 4px;">{{cooperativa.nome}}</p><p style="font-size:8px;color:#9ca3af;margin:3px 0;">CNPJ: {{cooperativa.cnpj}}</p><p style="font-size:8px;color:#9ca3af;margin:3px 0;">{{cooperativa.cidade}}/{{cooperativa.estado}} | Tel: {{cooperativa.telefone}}</p><p style="font-size:7px;color:#d1d5db;margin-top:5mm;">{{data.hoje_extenso}}</p></div></td></tr></table>',
            ],

            // B5 – Fale Conosco (fundo primário com card de contato)
            [
                'name' => 'Contracapa Canais de Comunicação',
                'layout_type' => 'back_cover',
                'is_default' => false,
                'estimated_height_mm' => 0,
                'content' => '<table style="width:100%;height:297mm;border-collapse:collapse;font-family:Arial,sans-serif;"><tr><td style="text-align:center;vertical-align:middle;padding:20mm;background:{{cor.primaria}};"><div style="margin-bottom:6mm;">{{cooperativa.logo_img}}</div><p style="font-size:14px;font-weight:bold;color:#fff;text-transform:uppercase;margin:0 0 3px;">{{cooperativa.nome}}</p><p style="font-size:8px;color:rgba(255,255,255,0.75);margin:0 0 8mm;">CNPJ: {{cooperativa.cnpj}}</p><div style="background:#fff;border-radius:8px;padding:8mm 10mm;max-width:140mm;margin:0 auto;text-align:left;"><p style="font-size:10px;font-weight:bold;color:{{cor.primaria}};text-transform:uppercase;letter-spacing:1px;margin:0 0 5mm;text-align:center;">Fale Conosco</p><table style="width:100%;border-collapse:collapse;"><tr><td style="padding:3px 0;font-size:8px;color:#374151;border-bottom:1px solid #f3f4f6;">Endereço: {{cooperativa.endereco}}</td></tr><tr><td style="padding:3px 0;font-size:8px;color:#374151;border-bottom:1px solid #f3f4f6;">Tel: {{cooperativa.telefone}} | E-mail: {{cooperativa.email}}</td></tr><tr><td style="padding:3px 0;font-size:8px;color:#374151;">{{cooperativa.cidade}}/{{cooperativa.estado}}</td></tr></table></div><p style="font-size:7.5px;color:rgba(255,255,255,0.6);margin-top:8mm;">Emitido em {{data.hoje_extenso}}</p></td></tr></table>',
            ],
        ];
    }

    /**
     * Variables available in layout templates.
     */
    public static function getAvailableVariables(): array
    {
        return [
            // Cooperativa (dados reais do Tenant)
            '{{cooperativa.nome}}' => 'Nome da Cooperativa',
            '{{cooperativa.cnpj}}' => 'CNPJ da Cooperativa',
            '{{cooperativa.endereco}}' => 'Endereço (logradouro + número)',
            '{{cooperativa.cidade}}' => 'Cidade',
            '{{cooperativa.estado}}' => 'Estado (UF)',
            '{{cooperativa.telefone}}' => 'Telefone',
            '{{cooperativa.email}}' => 'E-mail',
            '{{cooperativa.site}}' => 'Site',
            '{{cooperativa.ie}}' => 'Inscrição Estadual',
            '{{cooperativa.logo_img}}' => 'Logo da Cooperativa (imagem HTML)',
            // Datas
            '{{data.hoje}}' => 'Data de Hoje (dd/mm/aaaa)',
            '{{data.hoje_extenso}}' => 'Data por Extenso',
            '{{data.mes_atual}}' => 'Mês Atual',
            '{{data.ano_atual}}' => 'Ano Atual',
            '{{data.hora_atual}}' => 'Hora Atual',
            // Documento e paginação
            '{{documento.titulo}}' => 'Título do Documento',
            '{{pagina.atual}}' => 'Página Atual',
            '{{pagina.total}}' => 'Total de Páginas',
            // Tema de cores (resolvido automaticamente pelo tema selecionado)
            '{{cor.primaria}}' => 'Cor Primária do Tema',
            '{{cor.destaque}}' => 'Cor de Destaque do Tema',
            // Associado (contextual — disponível em documentos de associado)
            '{{associado.nome}}' => 'Nome do Associado',
            '{{associado.cpf}}' => 'CPF do Associado',
            '{{associado.rg}}' => 'RG do Associado',
            '{{associado.endereco}}' => 'Endereço do Associado',
            '{{associado.cidade}}' => 'Cidade do Associado',
            '{{associado.estado}}' => 'Estado do Associado',
            '{{associado.telefone}}' => 'Telefone do Associado',
            '{{associado.email}}' => 'E-mail do Associado',
            '{{associado.propriedade}}' => 'Nome da Propriedade',
            '{{associado.dap_caf}}' => 'Nº DAP/CAF',
            '{{associado.matricula}}' => 'Nº Matrícula',
            // Financeiro (contextual — disponível em documentos financeiros)
            '{{financeiro.valor}}' => 'Valor em R$ (contextual)',
            '{{financeiro.valor_extenso}}' => 'Valor por Extenso (contextual)',
            '{{financeiro.saldo}}' => 'Saldo da Conta (contextual)',
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

    public function scopeCovers($query)
    {
        return $query->where('layout_type', 'cover');
    }

    public function scopeBackCovers($query)
    {
        return $query->where('layout_type', 'back_cover');
    }
}
