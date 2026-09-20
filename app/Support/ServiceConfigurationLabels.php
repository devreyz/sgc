<?php

namespace App\Support;

final class ServiceConfigurationLabels
{
    public static function fieldTypes(): array
    {
        return [
            'text' => 'Texto curto',
            'textarea' => 'Texto longo / observação',
            'integer' => 'Número inteiro',
            'decimal' => 'Número decimal',
            'money' => 'Valor em dinheiro',
            'quantity' => 'Quantidade com unidade',
            'date' => 'Data',
            'datetime' => 'Data e hora',
            'boolean' => 'Sim ou não',
            'select' => 'Lista de opções',
            'member' => 'Membro',
            'associate' => 'Associado',
            'provider' => 'Prestador de serviço',
            'asset' => 'Equipamento ou recurso',
            'location' => 'Local',
            'image' => 'Foto',
            'file' => 'Arquivo ou comprovante',
            'signature' => 'Assinatura',
            'meter' => 'Leitura de medidor',
        ];
    }

    public static function phases(): array
    {
        return [
            'order' => 'Ao criar a ordem',
            'start' => 'Ao iniciar o serviço',
            'execution' => 'Durante a execução',
            'finish' => 'Ao concluir o serviço',
            'review' => 'Na conferência da gestão',
        ];
    }

    public static function pricingMethods(bool $provider = false): array
    {
        return [
            'fixed' => 'Valor fixo por execução',
            'quantity_x_rate' => 'Quantidade calculada × tarifa por unidade',
            'percent_of_base' => $provider ? 'Percentual do valor cobrado' : 'Percentual de um valor base',
        ];
    }

    public static function quantityModes(): array
    {
        return [
            'primary' => 'Usar a quantidade operacional principal',
            'fixed_one' => 'Uma unidade por execução',
            'field' => 'Usar um campo numérico',
            'meter_difference' => 'Diferença entre duas leituras',
        ];
    }

    public static function fieldType(?string $value): string
    {
        return self::fieldTypes()[$value] ?? ($value ?: 'Não informado');
    }

    public static function phase(?string $value): string
    {
        return self::phases()[$value] ?? ($value ?: 'Não informada');
    }
}
