<?php

use App\Models\Accounting\CheckSetting;
use App\Models\Accounting\Journal;
use App\Models\Accounting\PaymentMethod;
use App\Models\Accounting\PaymentProvider;
use App\Models\Accounting\PaymentTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

dataset('payment masters', [
    ['payment-terms', PaymentTerm::class],
    ['payment-methods', PaymentMethod::class],
    ['payment-providers', PaymentProvider::class],
    ['checks', CheckSetting::class],
]);

it('creates retrieves edits archives and filters configuration', function (string $path, string $model): void {
    authenticatedAdmin();
    $attributes = $model::factory()->make()->getAttributes();
    if ($model === PaymentTerm::class) {
        $attributes['lines'] = json_decode($attributes['lines'], true);
    }
    $id = $this->postJson('/api/v1/'.$path, $attributes)->assertCreated()->json('data.id');
    $this->getJson("/api/v1/$path/$id")->assertOk()->assertJsonPath('data.code', $attributes['code']);
    $this->patchJson("/api/v1/$path/$id", ['name' => 'Updated', 'is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.name', 'Updated');
    $this->getJson("/api/v1/$path?is_active=1")->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/$path?search=Updated&per_page=0")->assertOk()->assertJsonCount(1, 'data');
    $this->postJson('/api/v1/'.$path, $attributes)->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->getJson("/api/v1/$path/999999")->assertNotFound();
})->with('payment masters');

it('enforces authentication and permissions on every master', function (string $path, string $model): void {
    $record = $model::factory()->create();
    $this->getJson('/api/v1/'.$path)->assertUnauthorized();
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/'.$path)->assertForbidden();
    $this->getJson("/api/v1/$path/$record->id")->assertForbidden();
    $this->postJson('/api/v1/'.$path, [])->assertForbidden();
    $this->patchJson("/api/v1/$path/$record->id", ['is_active' => false])->assertForbidden();
})->with('payment masters');

it('preserves installment totals including rounding and month boundaries', function (): void {
    authenticatedAdmin();
    $term = PaymentTerm::factory()->create(['lines' => [
        ['type' => 'percent', 'value' => 30, 'days' => 0, 'due_type' => 'days_after'],
        ['type' => 'fixed', 'value' => 10, 'days' => 2, 'due_type' => 'end_of_month'],
        ['type' => 'balance', 'value' => 0, 'days' => 0, 'due_type' => 'end_of_next_month'],
    ]]);
    $this->postJson("/api/v1/payment-terms/$term->id/preview", ['amount' => 10001, 'date' => '2028-01-31'])
        ->assertOk()->assertExactJson(['data' => [
            ['due_date' => '2028-01-31', 'amount' => 3000],
            ['due_date' => '2028-02-02', 'amount' => 1000],
            ['due_date' => '2028-02-29', 'amount' => 6001],
        ]]);
    $this->postJson("/api/v1/payment-terms/$term->id/preview", ['amount' => 10, 'date' => '2028-01-31'])
        ->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->postJson("/api/v1/payment-terms/$term->id/preview", ['amount' => 0, 'date' => '2028-02-30'])
        ->assertUnprocessable()->assertJsonValidationErrors(['amount', 'date']);
    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/payment-terms/$term->id/preview", ['amount' => 100, 'date' => '2028-01-31'])->assertForbidden();
});

it('rejects invalid installment schedules without changing saved lines', function (array $lines): void {
    authenticatedAdmin();
    $term = PaymentTerm::factory()->create();
    $original = $term->lines;
    $this->patchJson("/api/v1/payment-terms/$term->id", ['lines' => $lines])->assertUnprocessable();
    expect($term->refresh()->lines)->toEqual($original);
})->with([
    'empty' => [[]],
    'missing balance' => [[['type' => 'percent', 'value' => 30, 'days' => 0, 'due_type' => 'days_after']]],
    'balance not last' => [[['type' => 'balance', 'value' => 0, 'days' => 0, 'due_type' => 'days_after'], ['type' => 'fixed', 'value' => 10, 'days' => 30, 'due_type' => 'days_after']]],
    'over 100 percent' => [[['type' => 'percent', 'value' => 101, 'days' => 0, 'due_type' => 'days_after'], ['type' => 'balance', 'value' => 0, 'days' => 30, 'due_type' => 'days_after']]],
    'unknown type' => [[['type' => 'other', 'value' => 0, 'days' => 0, 'due_type' => 'days_after']]],
    'negative days' => [[['type' => 'balance', 'value' => 0, 'days' => -1, 'due_type' => 'days_after']]],
    'extra line fields' => [[['type' => 'balance', 'value' => 0, 'days' => 0, 'due_type' => 'days_after', 'unsafe' => true]]],
]);

it('persists provider method associations and rejects outbound or unknown methods', function (): void {
    authenticatedAdmin();
    $method = PaymentMethod::factory()->create();
    $provider = PaymentProvider::factory()->create();
    $this->patchJson("/api/v1/payment-providers/$provider->id", ['payment_method_ids' => [$method->id], 'state' => 'test'])
        ->assertOk()->assertJsonPath('data.payment_method_ids', [$method->id]);
    $this->getJson('/api/v1/payment-providers')->assertOk()->assertJsonPath('data.0.payment_method_ids', [$method->id]);
    $this->patchJson("/api/v1/payment-methods/$method->id", ['direction' => 'outbound'])->assertUnprocessable()->assertJsonValidationErrors('direction');
    $outbound = PaymentMethod::factory()->create(['direction' => 'outbound']);
    foreach ([$outbound->id, 999999] as $id) {
        $this->patchJson("/api/v1/payment-providers/$provider->id", ['payment_method_ids' => [$id]])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method_ids.0');
    }
    expect($provider->paymentMethods()->pluck('payment_methods.id')->all())->toBe([$method->id]);
    $this->patchJson("/api/v1/payment-providers/$provider->id", ['payment_method_ids' => []])->assertOk()->assertJsonPath('data.payment_method_ids', []);
});

it('validates bank journal configuration and check numbering', function (): void {
    authenticatedAdmin();
    $check = CheckSetting::factory()->create();
    $sales = Journal::factory()->create(['type' => 'sales']);
    $this->patchJson("/api/v1/checks/$check->id", ['journal_id' => $sales->id, 'next_number' => 0, 'layout' => 'invalid'])
        ->assertUnprocessable()->assertJsonValidationErrors(['journal_id', 'next_number', 'layout']);
    $this->postJson('/api/v1/checks', ['code' => 'DUP', 'name' => 'Duplicate', 'journal_id' => $check->journal_id, 'next_number' => 1, 'layout' => 'top'])
        ->assertUnprocessable()->assertJsonValidationErrors('journal_id');
    $method = PaymentMethod::factory()->create();
    $this->patchJson("/api/v1/payment-methods/$method->id", ['journal_id' => $sales->id, 'direction' => 'bad', 'payment_type' => 'bad'])
        ->assertUnprocessable()->assertJsonValidationErrors(['journal_id', 'direction', 'payment_type']);
});

it('seeds useful defaults idempotently without overwriting edits', function (): void {
    $this->seed(Database\Seeders\PaymentConfigurationSeeder::class);
    PaymentTerm::query()->where('code', 'NET-30')->update(['name' => 'Custom']);
    $this->seed(Database\Seeders\PaymentConfigurationSeeder::class);
    expect(PaymentTerm::query()->count())->toBe(4)
        ->and(PaymentMethod::query()->count())->toBe(6)
        ->and(PaymentTerm::query()->where('code', 'NET-30')->value('name'))->toBe('Custom');
});

it('allows journal viewers to read but not edit configuration', function (string $path, string $model): void {
    $user = User::factory()->create();
    $role = \App\Models\Core\Role::query()->create(['name' => 'payment-viewer', 'display_name' => 'Payment viewer']);
    $permission = \App\Models\Core\Permission::query()->firstOrCreate(['name' => 'journals.view'], ['display_name' => 'View journals', 'group' => 'journals']);
    $role->permissions()->attach($permission);
    $user->roles()->attach($role);
    Sanctum::actingAs($user);
    $record = $model::factory()->create();
    $this->getJson('/api/v1/'.$path)->assertOk();
    $this->getJson("/api/v1/$path/$record->id")->assertOk();
    $this->patchJson("/api/v1/$path/$record->id", ['name' => 'Blocked'])->assertForbidden();
    $this->postJson('/api/v1/'.$path, [])->assertForbidden();
})->with('payment masters');
