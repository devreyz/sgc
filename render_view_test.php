<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tenant = (object) ['name' => 'Teste Coop', 'cnpj' => null, 'city' => 'Cidade', 'state' => 'ST', 'logo' => null];
$associate = (object) ['user' => (object) ['name' => 'Fulano'], 'cpf_cnpj' => '000.000.000-00', 'registration_number' => '123'];
$project = null;
$summary = ['gross_value' => 1000.0, 'admin_fee' => 50.0, 'net_value' => 950.0, 'total_quantity' => 10];
$productsSummary = [ ['product_name' => 'Tomate', 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 100.0, 'gross' => 1000.0, 'admin_fee' => 50.0, 'net' => 950.0] ];
$isSecondCopy = false;

try {
    echo view('pdf.project-associate-receipt', compact('tenant','associate','project','summary','productsSummary','isSecondCopy'))->render();
    echo "\n--- RENDERED OK ---\n";
} catch (Throwable $e) {
    echo "ERROR: " . get_class($e) . " - " . $e->getMessage() . PHP_EOL;
}
