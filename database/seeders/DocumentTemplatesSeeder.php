<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

class DocumentTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Recibo de Pagamento - Entrega',
                'type' => 'receipt',
                'description' => 'Recibo de pagamento para entregas de produtos realizadas por associados',
                'content' => $this->getPaymentReceiptTemplate(),
                'available_variables' => [
                    '{{cooperativa.nome}}',
                    '{{cooperativa.cnpj}}',
                    '{{cooperativa.endereco}}',
                    '{{associado.nome}}',
                    '{{associado.cpf}}',
                    '{{projeto.titulo}}',
                    '{{financeiro.valor}}',
                    '{{financeiro.valor_extenso}}',
                    '{{data.hoje}}',
                    '{{entrega.data}}',
                    '{{entrega.produto}}',
                    '{{entrega.quantidade}}',
                    '{{entrega.valor_bruto}}',
                    '{{entrega.taxa_admin}}',
                    '{{entrega.valor_liquido}}',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Declaração de Associado',
                'type' => 'declaration',
                'description' => 'Declaração confirmando que a pessoa é associada da cooperativa',
                'content' => $this->getAssociateDeclarationTemplate(),
                'available_variables' => [
                    '{{cooperativa.nome}}',
                    '{{cooperativa.cnpj}}',
                    '{{associado.nome}}',
                    '{{associado.cpf}}',
                    '{{associado.rg}}',
                    '{{associado.cidade}}',
                    '{{associado.estado}}',
                    '{{associado.matricula}}',
                    '{{data.hoje}}',
                    '{{data.hoje_extenso}}',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Carteirinha de Associado',
                'type' => 'other',
                'description' => 'Carteirinha de identificação de associado',
                'content' => $this->getMemberCardTemplate(),
                'available_variables' => [
                    '{{cooperativa.nome}}',
                    '{{associado.nome}}',
                    '{{associado.cpf}}',
                    '{{associado.matricula}}',
                    '{{associado.foto}}',
                    '{{qrcode}}',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Relatório de Entregas Mensal',
                'type' => 'report',
                'description' => 'Relatório detalhado das entregas realizadas por um associado no mês',
                'content' => $this->getDeliveryReportTemplate(),
                'available_variables' => [
                    '{{cooperativa.nome}}',
                    '{{cooperativa.cnpj}}',
                    '{{associado.nome}}',
                    '{{associado.cpf}}',
                    '{{associado.matricula}}',
                    '{{data.mes_atual}}',
                    '{{data.ano_atual}}',
                    '{{relatorio.total_entregas}}',
                    '{{relatorio.valor_total}}',
                    '{{relatorio.entregas_detalhadas}}',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Ata de Reunião',
                'type' => 'report',
                'description' => 'Modelo de ata para reuniões da cooperativa',
                'content' => $this->getMinutesTemplate(),
                'available_variables' => [
                    '{{cooperativa.nome}}',
                    '{{cooperativa.cnpj}}',
                    '{{reuniao.data}}',
                    '{{reuniao.hora}}',
                    '{{reuniao.local}}',
                    '{{reuniao.participantes}}',
                    '{{reuniao.pauta}}',
                    '{{reuniao.deliberacoes}}',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Contrato de Fornecimento',
                'type' => 'contract',
                'description' => 'Contrato de fornecimento de produtos entre cooperativa e cliente',
                'content' => $this->getSupplyContractTemplate(),
                'available_variables' => [
                    '{{cooperativa.nome}}',
                    '{{cooperativa.cnpj}}',
                    '{{cooperativa.endereco}}',
                    '{{projeto.titulo}}',
                    '{{projeto.cliente}}',
                    '{{projeto.data_inicio}}',
                    '{{projeto.data_fim}}',
                    '{{projeto.valor_total}}',
                    '{{projeto.numero_contrato}}',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $templateData) {
            DocumentTemplate::updateOrCreate(
                ['name' => $templateData['name']],
                $templateData
            );
        }

        $this->command->info('Document templates seeded successfully!');
    }

    private function getPaymentReceiptTemplate(): string
    {
        return <<<'HTML'
<div style="max-width: 800px; margin: 0 auto; padding: 40px; font-family: Arial, sans-serif;">
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="color: #2563eb; margin: 0;">{{cooperativa.nome}}</h1>
        <p style="margin: 5px 0;">CNPJ: {{cooperativa.cnpj}}</p>
        <p style="margin: 5px 0;">{{cooperativa.endereco}}</p>
        <h2 style="margin-top: 30px; color: #374151;">RECIBO DE PAGAMENTO</h2>
    </div>

    <div style="border: 2px solid #e5e7eb; padding: 30px; border-radius: 8px;">
        <p style="font-size: 14px; line-height: 1.8;">
            Recebi de <strong>{{cooperativa.nome}}</strong> a quantia de <strong>R$ {{financeiro.valor}}</strong> 
            (<strong>{{financeiro.valor_extenso}}</strong>), referente ao pagamento de entrega de produtos 
            do projeto <strong>{{projeto.titulo}}</strong>.
        </p>

        <div style="margin: 30px 0; padding: 20px; background: #f9fafb; border-radius: 6px;">
            <h3 style="margin-top: 0; color: #374151;">Detalhes da Entrega</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0;"><strong>Data da Entrega:</strong></td>
                    <td style="text-align: right;">{{entrega.data}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Produto:</strong></td>
                    <td style="text-align: right;">{{entrega.produto}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;"><strong>Quantidade:</strong></td>
                    <td style="text-align: right;">{{entrega.quantidade}}</td>
                </tr>
                <tr style="border-top: 1px solid #e5e7eb;">
                    <td style="padding: 8px 0;"><strong>Valor Bruto:</strong></td>
                    <td style="text-align: right;">R$ {{entrega.valor_bruto}}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0;">Taxa Administrativa:</td>
                    <td style="text-align: right; color: #ef4444;">- R$ {{entrega.taxa_admin}}</td>
                </tr>
                <tr style="border-top: 2px solid #374151;">
                    <td style="padding: 8px 0;"><strong style="font-size: 16px;">Valor Líquido:</strong></td>
                    <td style="text-align: right;"><strong style="font-size: 16px; color: #10b981;">R$ {{entrega.valor_liquido}}</strong></td>
                </tr>
            </table>
        </div>

        <div style="margin-top: 40px;">
            <p><strong>Associado:</strong> {{associado.nome}}</p>
            <p><strong>CPF:</strong> {{associado.cpf}}</p>
        </div>

        <div style="margin-top: 60px; text-align: right;">
            <p>{{cooperativa.endereco}}, {{data.hoje}}</p>
            <div style="margin-top: 80px; border-top: 1px solid #374151; padding-top: 10px; display: inline-block; min-width: 300px;">
                <p style="margin: 0; text-align: center;">{{associado.nome}}</p>
                <p style="margin: 0; text-align: center; font-size: 12px; color: #6b7280;">CPF: {{associado.cpf}}</p>
            </div>
        </div>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 12px; color: #6b7280;">
        <p>Documento autenticado digitalmente</p>
        <p>Código de verificação: [QR CODE]</p>
    </div>
</div>
HTML;
    }

    private function getAssociateDeclarationTemplate(): string
    {
        return <<<'HTML'
<div style="max-width: 800px; margin: 0 auto; padding: 40px; font-family: Arial, sans-serif;">
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="color: #2563eb; margin: 0;">{{cooperativa.nome}}</h1>
        <p style="margin: 5px 0;">CNPJ: {{cooperativa.cnpj}}</p>
        <h2 style="margin-top: 30px; color: #374151;">DECLARAÇÃO</h2>
    </div>

    <div style="line-height: 2; text-align: justify; padding: 30px;">
        <p style="text-indent: 50px;">
            Declaramos para os devidos fins que <strong>{{associado.nome}}</strong>, portador(a) do CPF 
            <strong>{{associado.cpf}}</strong> e RG <strong>{{associado.rg}}</strong>, residente em 
            <strong>{{associado.cidade}}/{{associado.estado}}</strong>, é associado(a) desta cooperativa, 
            com matrícula nº <strong>{{associado.matricula}}</strong>, encontrando-se em pleno gozo 
            de seus direitos e deveres estatutários.
        </p>

        <p style="text-indent: 50px;">
            Por ser verdade, firmamos a presente declaração.
        </p>

        <div style="margin-top: 80px; text-align: right;">
            <p>{{cooperativa.endereco}}, {{data.hoje_extenso}}</p>
            <div style="margin-top: 80px; border-top: 1px solid #374151; padding-top: 10px; display: inline-block; min-width: 350px;">
                <p style="margin: 0; text-align: center;">{{cooperativa.nome}}</p>
                <p style="margin: 0; text-align: center; font-size: 12px; color: #6b7280;">CNPJ: {{cooperativa.cnpj}}</p>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    private function getMemberCardTemplate(): string
    {
        return <<<'HTML'
<div style="width: 85.6mm; height: 53.98mm; border: 1px solid #000; border-radius: 8px; overflow: hidden; font-family: Arial, sans-serif; position: relative;">
    <!-- Front Side -->
    <div style="height: 100%; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 15px; position: relative;">
        <div style="font-size: 11px; font-weight: bold; margin-bottom: 5px;">{{cooperativa.nome}}</div>
        
        <div style="position: absolute; top: 50%; transform: translateY(-50%); left: 15px; right: 15px;">
            <div style="background: white; color: #1d4ed8; padding: 8px; border-radius: 6px; margin-bottom: 8px;">
                <div style="font-size: 14px; font-weight: bold; margin-bottom: 3px;">{{associado.nome}}</div>
                <div style="font-size: 10px;">CPF: {{associado.cpf}}</div>
                <div style="font-size: 10px;">Matrícula: {{associado.matricula}}</div>
            </div>
        </div>

        <div style="position: absolute; bottom: 10px; right: 10px; background: white; padding: 5px; border-radius: 4px;">
            {{qrcode}}
        </div>

        <div style="position: absolute; bottom: 10px; left: 15px; font-size: 8px; opacity: 0.8;">
            Associado(a) desde {{data.ano_atual}}
        </div>
    </div>
</div>
HTML;
    }

    private function getDeliveryReportTemplate(): string
    {
        return <<<'HTML'
<div style="max-width: 900px; margin: 0 auto; padding: 40px; font-family: Arial, sans-serif;">
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="color: #2563eb; margin: 0;">{{cooperativa.nome}}</h1>
        <p style="margin: 5px 0;">CNPJ: {{cooperativa.cnpj}}</p>
        <h2 style="margin-top: 30px; color: #374151;">RELATÓRIO DE ENTREGAS</h2>
        <p style="color: #6b7280;">{{data.mes_atual}} de {{data.ano_atual}}</p>
    </div>

    <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <p><strong>Associado:</strong> {{associado.nome}} (Matrícula: {{associado.matricula}})</p>
        <p><strong>CPF:</strong> {{associado.cpf}}</p>
    </div>

    <div style="margin-bottom: 30px;">
        <h3 style="color: #374151;">Resumo do Período</h3>
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <tr style="background: #f3f4f6;">
                <td style="padding: 12px; border: 1px solid #e5e7eb;">Total de Entregas</td>
                <td style="padding: 12px; border: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{relatorio.total_entregas}}</td>
            </tr>
            <tr>
                <td style="padding: 12px; border: 1px solid #e5e7eb;">Valor Total Líquido</td>
                <td style="padding: 12px; border: 1px solid #e5e7eb; text-align: right; font-weight: bold; color: #10b981;">R$ {{relatorio.valor_total}}</td>
            </tr>
        </table>
    </div>

    <div>
        <h3 style="color: #374151;">Entregas Detalhadas</h3>
        {{relatorio.entregas_detalhadas}}
    </div>

    <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #e5e7eb; text-align: right; font-size: 12px; color: #6b7280;">
        <p>Relatório gerado em {{data.hoje}}</p>
    </div>
</div>
HTML;
    }

    private function getMinutesTemplate(): string
    {
        return <<<'HTML'
<div style="max-width: 800px; margin: 0 auto; padding: 40px; font-family: Arial, sans-serif;">
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="color: #2563eb; margin: 0;">{{cooperativa.nome}}</h1>
        <p style="margin: 5px 0;">CNPJ: {{cooperativa.cnpj}}</p>
        <h2 style="margin-top: 30px; color: #374151;">ATA DE REUNIÃO</h2>
    </div>

    <div style="line-height: 1.8; text-align: justify;">
        <p><strong>Data:</strong> {{reuniao.data}} às {{reuniao.hora}}</p>
        <p><strong>Local:</strong> {{reuniao.local}}</p>

        <h3 style="color: #374151; margin-top: 30px;">Participantes</h3>
        <div style="padding: 15px; background: #f9fafb; border-left: 4px solid #2563eb; margin-bottom: 20px;">
            {{reuniao.participantes}}
        </div>

        <h3 style="color: #374151;">Pauta</h3>
        <div style="padding: 15px; background: #f9fafb; margin-bottom: 20px;">
            {{reuniao.pauta}}
        </div>

        <h3 style="color: #374151;">Deliberações</h3>
        <div style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 20px;">
            {{reuniao.deliberacoes}}
        </div>

        <p style="margin-top: 40px;">
            Nada mais havendo a tratar, foi encerrada a reunião, da qual se lavrou a presente ata, 
            que vai assinada por todos os presentes.
        </p>

        <div style="margin-top: 60px;">
            <div style="margin-bottom: 60px;">
                <div style="border-top: 1px solid #374151; padding-top: 5px; width: 300px;">
                    <p style="margin: 0; font-size: 12px; color: #6b7280;">Presidente</p>
                </div>
            </div>

            <div style="margin-bottom: 60px;">
                <div style="border-top: 1px solid #374151; padding-top: 5px; width: 300px;">
                    <p style="margin: 0; font-size: 12px; color: #6b7280;">Secretário</p>
                </div>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    private function getSupplyContractTemplate(): string
    {
        return <<<'HTML'
<div style="max-width: 800px; margin: 0 auto; padding: 40px; font-family: Arial, sans-serif;">
    <div style="text-align: center; margin-bottom: 40px;">
        <h2 style="color: #374151; margin: 0;">CONTRATO DE FORNECIMENTO</h2>
        <p style="margin: 10px 0; color: #6b7280;">Nº {{projeto.numero_contrato}}</p>
    </div>

    <div style="line-height: 1.8; text-align: justify;">
        <p style="font-weight: bold; margin-bottom: 20px;">CONTRATANTE:</p>
        <p style="margin-left: 20px; margin-bottom: 20px;">
            <strong>{{projeto.cliente}}</strong>
        </p>

        <p style="font-weight: bold; margin-bottom: 20px;">CONTRATADA:</p>
        <p style="margin-left: 20px; margin-bottom: 30px;">
            <strong>{{cooperativa.nome}}</strong>, pessoa jurídica de direito privado, inscrita no CNPJ sob 
            o nº <strong>{{cooperativa.cnpj}}</strong>, com sede em <strong>{{cooperativa.endereco}}</strong>.
        </p>

        <p style="font-weight: bold; margin-top: 30px;">CLÁUSULA PRIMEIRA - DO OBJETO</p>
        <p style="text-indent: 30px;">
            O presente contrato tem por objeto o fornecimento de produtos no âmbito do projeto 
            <strong>{{projeto.titulo}}</strong>, conforme especificações e quantidades detalhadas no anexo I deste instrumento.
        </p>

        <p style="font-weight: bold; margin-top: 20px;">CLÁUSULA SEGUNDA - DO PRAZO</p>
        <p style="text-indent: 30px;">
            O prazo de vigência deste contrato será de <strong>{{projeto.data_inicio}}</strong> a 
            <strong>{{projeto.data_fim}}</strong>, podendo ser prorrogado mediante acordo entre as partes.
        </p>

        <p style="font-weight: bold; margin-top: 20px;">CLÁUSULA TERCEIRA - DO VALOR</p>
        <p style="text-indent: 30px;">
            O valor total deste contrato é de <strong>R$ {{projeto.valor_total}}</strong>, a ser pago 
            conforme cronograma de entregas e condições estabelecidas no anexo II.
        </p>

        <p style="font-weight: bold; margin-top: 20px;">CLÁUSULA QUARTA - DAS OBRIGAÇÕES</p>
        <p style="text-indent: 30px;">
            A CONTRATADA se obriga a fornecer produtos de qualidade, em conformidade com as normas 
            sanitárias e de segurança alimentar vigentes, respeitando os prazos e quantidades acordadas.
        </p>

        <p style="text-indent: 30px;">
            A CONTRATANTE se obriga a receber os produtos nas datas acordadas e efetuar os pagamentos 
            conforme cronograma estabelecido.
        </p>

        <p style="font-weight: bold; margin-top: 20px;">CLÁUSULA QUINTA - DO FORO</p>
        <p style="text-indent: 30px;">
            As partes elegem o foro da comarca de <strong>{{cooperativa.cidade}}</strong> para dirimir 
            quaisquer questões oriundas deste contrato.
        </p>

        <p style="margin-top: 40px;">
            E, por estarem assim justos e contratados, firmam o presente instrumento em duas vias de 
            igual teor e forma, na presença das testemunhas abaixo.
        </p>

        <div style="margin-top: 80px;">
            <div style="margin-bottom: 80px;">
                <div style="border-top: 1px solid #374151; padding-top: 5px; width: 300px; margin: 0 auto;">
                    <p style="margin: 0; text-align: center; font-weight: bold;">{{cooperativa.nome}}</p>
                    <p style="margin: 0; text-align: center; font-size: 12px; color: #6b7280;">CONTRATADA</p>
                </div>
            </div>

            <div style="margin-bottom: 40px;">
                <div style="border-top: 1px solid #374151; padding-top: 5px; width: 300px; margin: 0 auto;">
                    <p style="margin: 0; text-align: center; font-weight: bold;">{{projeto.cliente}}</p>
                    <p style="margin: 0; text-align: center; font-size: 12px; color: #6b7280;">CONTRATANTE</p>
                </div>
            </div>
        </div>

        <p style="margin-top: 60px; font-weight: bold;">TESTEMUNHAS:</p>
        <div style="display: flex; justify-content: space-between; margin-top: 80px;">
            <div style="border-top: 1px solid #374151; padding-top: 5px; width: 45%;">
                <p style="margin: 0; font-size: 12px;">Nome:</p>
                <p style="margin: 0; font-size: 12px;">CPF:</p>
            </div>
            <div style="border-top: 1px solid #374151; padding-top: 5px; width: 45%;">
                <p style="margin: 0; font-size: 12px;">Nome:</p>
                <p style="margin: 0; font-size: 12px;">CPF:</p>
            </div>
        </div>
    </div>
</div>
HTML;
    }
}
