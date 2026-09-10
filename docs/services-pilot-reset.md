# Reset seguro do piloto de serviços

O comando `php artisan services:pilot-reset --dry-run` mostra contagens e escopo sem alterar dados. `--force` executa somente após a conferência. Em produção também é exigido o token configurado em `SERVICES_PILOT_RESET_TOKEN`.

O reset remove fatos do piloto do módulo de serviços e referências compartilhadas cuja origem seja comprovadamente um tipo legado de serviço. Não remove tenants, usuários, associados, prestadores nem contas bancárias. Movimentos de caixa são removidos pelo modelo para que os observadores revertam saldos.

Ordens da nova fundação (`service_version_id` preenchido) não pertencem ao reset legado. O processo roda em transação e interrompe ao encontrar pré-condição insegura.
