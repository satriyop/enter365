<?php
use App\Models\Accounting\Account;
use App\Http\Controllers\Api\V1\AccountController;
use Illuminate\Http\Request;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(Request::capture());

$acc = Account::first();
if (!$acc) {
    echo "No account found\n";
    exit;
}

$controller = app(AccountController::class);
$request = new Request();
$data = $controller->ledger($acc, $request)->getData();

echo json_encode($data, JSON_PRETTY_PRINT) . "\n";

