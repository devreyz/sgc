<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Symfony\Component\Process\Process;

class GenerateWebPushKeys extends Command
{
    protected $signature = 'webpush:vapid {--configured-openssl : Execucao interna com OpenSSL configurado}';
    protected $description = 'Gera um par de chaves VAPID para notificacoes Web Push';

    public function handle(): int
    {
        if (PHP_OS_FAMILY === 'Windows' && ! getenv('OPENSSL_CONF') && ! $this->option('configured-openssl')) {
            $opensslConfig = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';
            if (is_file($opensslConfig)) {
                $process = new Process(
                    [PHP_BINARY, base_path('artisan'), 'webpush:vapid', '--configured-openssl'],
                    base_path(),
                    ['OPENSSL_CONF' => $opensslConfig],
                );
                $process->setTimeout(30);
                $process->run(fn (string $type, string $output) => $this->output->write($output));

                return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
            }
        }

        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable $exception) {
            $this->error('Nao foi possivel gerar a chave P-256. Verifique a extensao OpenSSL e a variavel OPENSSL_CONF.');

            return self::FAILURE;
        }
        $this->line('WEBPUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEBPUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->warn('Guarde a chave privada somente no ambiente do servidor.');

        return self::SUCCESS;
    }
}
