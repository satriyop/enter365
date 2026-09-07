<?php

declare(strict_types=1);

namespace App\Imports\Accounting;

use App\Models\Accounting\Account;
use App\Services\Accounting\AccountService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AccountImport implements ToCollection, WithHeadingRow
{
    use Importable;

    /** @var array<string, true> */
    private array $seenCodes = [];

    /** @var array<string, Account> */
    private array $createdByCode = [];

    /** @var array<int, Account> */
    private array $createdAccounts = [];

    /** @var array<int, array{row: int, messages: array<int, string>}> */
    private array $errors = [];

    public function __construct(
        private AccountService $accountService
    ) {}

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // header row + 0-index
            $data = $this->normalizeRow($row->toArray());

            if ($this->isEmptyRow($data)) {
                continue;
            }

            $messages = $this->validateRow($data);

            // Reserve code for in-file uniqueness even when the row later fails.
            if (is_string($data['code']) && $data['code'] !== '') {
                $this->seenCodes[$data['code']] = true;
            }

            if ($messages !== []) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'messages' => $messages,
                ];

                continue;
            }

            $payload = $this->buildPayload($data);

            try {
                $account = $this->accountService->create($payload);
                $this->createdAccounts[] = $account;
                $this->createdByCode[$account->code] = $account;
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'messages' => [$e->getMessage()],
                ];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $get = function (array $keys) use ($row): mixed {
            foreach ($keys as $key) {
                if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                    return is_string($row[$key]) ? trim($row[$key]) : $row[$key];
                }
            }

            return null;
        };

        return [
            'code' => $get(['code']),
            'name' => $get(['name']),
            'type' => $get(['type']),
            'subtype' => $get(['subtype']),
            'parent' => $get(['parent', 'parent_id', 'parent_code']),
            'active' => $get(['active', 'is_active']),
            'allow_reconciliation' => $get(['allow_reconciliation', 'reconcile', 'reconciliation']),
            'currency' => $get(['currency']),
            'description' => $get(['description']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmptyRow(array $data): bool
    {
        return ($data['code'] === null || $data['code'] === '')
            && ($data['name'] === null || $data['name'] === '');
    }

    /**
     * Validate a row using StoreAccountRequest-equivalent rules.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function validateRow(array $data): array
    {
        $messages = [];

        $currency = $data['currency'];
        if ($currency === '') {
            $currency = null;
        }

        $validator = Validator::make(
            [
                'code' => $data['code'],
                'name' => $data['name'],
                'type' => $data['type'],
                'subtype' => $data['subtype'],
                'description' => $data['description'],
                'currency' => $currency,
            ],
            [
                'code' => ['required', 'string', 'max:20'],
                'name' => ['required', 'string', 'max:255'],
                'type' => ['required', 'string', Rule::in(Account::getTypes())],
                'subtype' => ['nullable', 'string', 'max:50'],
                'description' => ['nullable', 'string'],
                'currency' => [
                    'nullable',
                    'string',
                    'size:3',
                    Rule::in(config('accounting.multi_currency.supported_currencies', [])),
                ],
            ],
            [
                'code.required' => 'Kode akun wajib diisi.',
                'name.required' => 'Nama akun wajib diisi.',
                'type.required' => 'Tipe akun wajib diisi.',
                'type.in' => 'Tipe akun tidak valid.',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $messages[] = $message;
            }
        }

        $code = is_string($data['code']) ? $data['code'] : (string) $data['code'];

        if ($code !== '' && $code !== '0') {
            if (isset($this->seenCodes[$code]) || Account::where('code', $code)->exists()) {
                $messages[] = 'Kode akun sudah digunakan.';
            }
        }

        if ($data['active'] !== null && $this->parseBoolean($data['active']) === null) {
            $messages[] = 'Nilai active tidak valid (gunakan true/false atau 1/0).';
        }

        if ($data['allow_reconciliation'] !== null && $this->parseBoolean($data['allow_reconciliation']) === null) {
            $messages[] = 'Nilai allow_reconciliation tidak valid (gunakan true/false atau 1/0).';
        }

        // Parent resolution + type compatibility (same rule as StoreAccountRequest)
        if ($data['parent'] !== null && $data['parent'] !== '') {
            $parent = $this->resolveParent($data['parent']);
            if (! $parent) {
                $messages[] = "Akun induk '{$data['parent']}' tidak ditemukan.";
            } elseif ($data['type'] && $parent->type !== $data['type']) {
                $messages[] = 'Tipe akun induk harus sama dengan tipe akun yang dibuat.';
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildPayload(array $data): array
    {
        $parentId = null;
        if ($data['parent'] !== null && $data['parent'] !== '') {
            $parent = $this->resolveParent($data['parent']);
            $parentId = $parent?->id;
        }

        $currency = $data['currency'];
        if ($currency === '') {
            $currency = null;
        }

        $payload = [
            'code' => (string) $data['code'],
            'name' => (string) $data['name'],
            'type' => (string) $data['type'],
            'subtype' => $data['subtype'],
            'description' => $data['description'],
            'parent_id' => $parentId,
            'is_active' => $data['active'] !== null
                ? (bool) $this->parseBoolean($data['active'])
                : true,
            'allow_reconciliation' => $data['allow_reconciliation'] !== null
                ? (bool) $this->parseBoolean($data['allow_reconciliation'])
                : false,
            'currency' => $currency !== null ? strtoupper((string) $currency) : null,
        ];

        return $payload;
    }

    private function resolveParent(mixed $parent): ?Account
    {
        $value = is_string($parent) ? trim($parent) : $parent;

        if ($value === null || $value === '') {
            return null;
        }

        $asString = (string) $value;

        if (isset($this->createdByCode[$asString])) {
            return $this->createdByCode[$asString];
        }

        if (is_numeric($asString) && ! str_contains($asString, '.')) {
            $byId = Account::find((int) $asString);
            if ($byId) {
                return $byId;
            }
        }

        return Account::where('code', $asString)->first();
    }

    private function parseBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            if ((int) $value === 1) {
                return true;
            }
            if ((int) $value === 0) {
                return false;
            }

            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'aktif' => true,
            '0', 'false', 'no', 'n', 'nonaktif' => false,
            default => null,
        };
    }

    /**
     * @return array{
     *   created_count: int,
     *   error_count: int,
     *   errors: array<int, array{row: int, messages: array<int, string>}>,
     *   accounts: array<int, Account>
     * }
     */
    public function getResults(): array
    {
        return [
            'created_count' => count($this->createdAccounts),
            'error_count' => count($this->errors),
            'errors' => $this->errors,
            'accounts' => $this->createdAccounts,
        ];
    }
}
