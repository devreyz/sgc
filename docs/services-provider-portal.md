# Portal do prestador

O portal resolve o prestador somente pelo usuário autenticado e pelo tenant da rota. Nunca cria prestador automaticamente e nunca aceita `provider_id` informado pelo navegador.

O prestador vê apenas suas ordens, inicia o serviço, salva rascunhos, envia para conferência, anexa evidências privadas e consulta obrigações e pagamentos próprios. A criação de OS aparece somente quando a versão publicada autoriza.

Evidências são armazenadas no disco privado, têm MIME e tamanho validados e recebem hash SHA-256. Downloads passam por autorização e escopo do tenant.

Na área financeira, “a receber” significa valor já reconhecido pela organização em obrigação payable; “pago” vem das alocações confirmadas; solicitações de repasse reservam somente saldos disponíveis e são idempotentes.
