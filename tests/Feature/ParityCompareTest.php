<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

function parityRunner(): string
{
    return base_path('scripts/parity/compare.mjs');
}

function runParity(array $args, array $env = []): Illuminate\Contracts\Process\ProcessResult
{
    return Process::timeout(30)
        ->path(base_path())
        ->env($env)
        ->run(['node', parityRunner(), ...$args]);
}

it('dry-run validates the catalog and prints the ordered plan', function () {
    $result = runParity(['--dry-run']);

    expect($result->successful())->toBeTrue()
        ->and($result->output())->toContain('Odoo ↔ enter365 parity compare')
        ->and($result->output())->toContain('(read-only)')
        ->and($result->output())->toContain('OK. Catalog is valid. No browsers launched.')
        ->and($result->errorOutput())->toBeEmpty();

    $seedIds = [
        'post-login',
        'chart-of-accounts',
        'customer-invoices',
        'quotation-detail',
        'purchase-rfq',
        'vendor-bills',
        'stock-report',
        'accounting-reports',
        'contacts',
        'products',
        'delivery-orders',
        'payments',
    ];

    foreach ($seedIds as $id) {
        expect($result->output())->toContain($id);
    }

    expect($result->output())->toContain('/odoo')
        ->and($result->output())->toContain('/accounting/accounts')
        ->and($result->output())->toContain('/invoices')
        ->and($result->output())->toContain('/quotations/{id}')
        ->and($result->output())->toContain('/purchasing/purchase-orders')
        ->and($result->output())->toContain('/bills')
        ->and($result->output())->toContain('/reports/stock-summary')
        ->and($result->output())->toContain('/reports');
});

it('limits the dry-run plan to the first N pairs', function () {
    $result = runParity(['--dry-run', '--limit', '2']);

    expect($result->successful())->toBeTrue()
        ->and($result->output())->toContain('post-login')
        ->and($result->output())->toContain('chart-of-accounts')
        ->and($result->output())->toContain('limit 2')
        ->and($result->output())->not->toContain('customer-invoices');
});

it('fails a live run when credentials are missing without echoing secrets', function () {
    $result = runParity(['--limit', '1'], [
        'ODOO_EMAIL' => '',
        'ODOO_PASSWORD' => '',
        'ENTER365_EMAIL' => '',
        'ENTER365_PASSWORD' => '',
        'ODOO_BASE_URL' => 'https://enterkode.odoo.com',
        'ENTER365_BASE_URL' => 'https://enter365.pamungkas.org',
    ]);

    expect($result->successful())->toBeFalse()
        ->and($result->errorOutput())->toContain('ODOO_EMAIL')
        ->and($result->errorOutput())->toContain('ODOO_PASSWORD')
        ->and($result->errorOutput())->toContain('ENTER365_EMAIL')
        ->and($result->errorOutput())->toContain('ENTER365_PASSWORD')
        ->and($result->errorOutput())->toContain('never commit secrets')
        ->and($result->errorOutput())->not->toContain('password=')
        ->and($result->output().$result->errorOutput())->not->toMatch('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/');
});

it('blocks Odoo write JSON-RPC and allows reads and login', function () {
    $script = <<<'JS'
import { isOdooWriteRequest } from './scripts/parity/lib/capture.mjs';

const write = isOdooWriteRequest({
    method: 'POST',
    url: 'https://enterkode.odoo.com/web/dataset/call_kw/sale.order/write',
    postData: JSON.stringify({ params: { method: 'write' } }),
});
const save = isOdooWriteRequest({
    method: 'POST',
    url: 'https://enterkode.odoo.com/web/dataset/call_kw/account.move/web_save',
    postData: JSON.stringify({ params: { method: 'web_save' } }),
});
const read = isOdooWriteRequest({
    method: 'POST',
    url: 'https://enterkode.odoo.com/web/dataset/call_kw/sale.order/web_search_read',
    postData: JSON.stringify({ params: { method: 'web_search_read' } }),
});
const login = isOdooWriteRequest({
    method: 'POST',
    url: 'https://enterkode.odoo.com/web/login',
    postData: 'login=demo@example.com&password=super-secret-value',
});
const get = isOdooWriteRequest({
    method: 'GET',
    url: 'https://enterkode.odoo.com/odoo',
});

if (write && save && !read && !login && !get) {
    console.log('ok');
    process.exit(0);
}

console.error(JSON.stringify({ write, save, read, login, get }));
process.exit(1);
JS;

    $result = Process::timeout(15)
        ->path(base_path())
        ->run(['node', '--input-type=module', '-e', $script]);

    expect($result->successful())->toBeTrue()
        ->and($result->output())->toContain('ok')
        ->and($result->errorOutput())->not->toContain('super-secret-value');
});

it('rejects unknown compare dimensions in the catalog parser', function () {
    $script = <<<'JS'
import { parseCatalogYaml, validateCatalog } from './scripts/parity/compare.mjs';

const catalog = parseCatalogYaml(`
pairs:
  - id: post-login
    journey: home
    title: Home
    odoo.path: /odoo
    enter365.path: /
    compare:
      - chrome
      - not-a-dimension
`);
const errors = validateCatalog(catalog);
if (errors.some((e) => e.includes('not-a-dimension'))) {
    console.log('ok');
    process.exit(0);
}
console.error(JSON.stringify(errors));
process.exit(1);
JS;

    $result = Process::timeout(15)
        ->path(base_path())
        ->run(['node', '--input-type=module', '-e', $script]);

    expect($result->successful())->toBeTrue()
        ->and($result->output())->toContain('ok');
});
