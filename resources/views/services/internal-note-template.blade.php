<div style="border-left:4px solid #176348;background:#edf5f0;padding:10px 14px;margin:0 0 12px">
    <div style="font-size:9pt;color:#37584a">CONTROLE INTERNO · SEM VALOR FISCAL</div>
    <h1 style="margin:4px 0 0;font-size:18pt">Nota interna de serviço</h1>
    <div style="margin-top:4px">Ordem @{{order_number}} · @{{service_name}}</div>
</div>
<table style="width:100%;border-collapse:collapse;margin-bottom:12px;table-layout:fixed">
    <tbody>
        <tr><td><strong>Beneficiário</strong><br>@{{beneficiary_name}}</td><td><strong>Prestador</strong><br>@{{provider_name}}</td></tr>
        <tr><td><strong>Agendado em</strong><br>@{{scheduled_at}}</td><td><strong>Validado em</strong><br>@{{validated_at}}</td></tr>
        <tr><td><strong>Local</strong><br>@{{location}}</td><td><strong>Quantidade executada</strong><br>@{{quantity}} @{{unit}}</td></tr>
    </tbody>
</table>
<h2 style="font-size:12pt">Valores a receber</h2>
@{{composicao_receber}}
<p style="text-align:right"><strong>Total a receber: @{{total_receber}}</strong></p>
<h2 style="font-size:12pt">Remuneração do prestador</h2>
@{{composicao_pagar}}
<p style="text-align:right"><strong>Total a pagar: @{{total_pagar}}</strong></p>
<h2 style="font-size:12pt">Dados da execução</h2>
@{{campos_execucao}}
<p style="white-space:pre-line"><strong>Observações</strong><br>@{{notes}}</p>
