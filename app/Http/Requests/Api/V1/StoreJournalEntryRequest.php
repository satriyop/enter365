<?php

namespace App\Http\Requests\Api\V1;

use App\Casts\AnalyticDistributionCast;
use App\Models\Accounting\AnalyticAccount;
use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'journal_id' => ['required', 'integer', 'exists:journals,id'],
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:100'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.partner_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'lines.*.analytic_distribution' => ['nullable', 'array'],
            'lines.*.analytic_distribution.*' => ['numeric', 'min:0', 'max:100'],
            'lines.*.tax_tag_ids' => ['nullable', 'array'],
            'lines.*.tax_tag_ids.*' => ['integer', 'min:1', 'exists:tax_tags,id'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.debit' => ['required_without:lines.*.credit', 'integer', 'min:0'],
            'lines.*.credit' => ['required_without:lines.*.debit', 'integer', 'min:0'],
            'auto_post' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'journal_id.required' => 'Jurnal wajib dipilih.',
            'journal_id.exists' => 'Jurnal tidak ditemukan.',
            'entry_date.required' => 'Tanggal jurnal wajib diisi.',
            'description.required' => 'Deskripsi jurnal wajib diisi.',
            'lines.required' => 'Baris jurnal wajib diisi.',
            'lines.min' => 'Jurnal harus memiliki minimal 2 baris.',
            'lines.*.account_id.required' => 'Akun wajib diisi untuk setiap baris.',
            'lines.*.account_id.exists' => 'Akun tidak ditemukan.',
            'lines.*.partner_id.exists' => 'Partner/kontak tidak ditemukan.',
            'lines.*.analytic_distribution.array' => 'Distribusi analitik tidak valid.',
            'lines.*.tax_tag_ids.*.exists' => 'Tag pajak tidak ditemukan.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lines = $this->input('lines', []);
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($lines as $line) {
                $totalDebit += $line['debit'] ?? 0;
                $totalCredit += $line['credit'] ?? 0;
            }

            if ($totalDebit !== $totalCredit) {
                $validator->errors()->add('lines', 'Total debit harus sama dengan total kredit. Debit: '.$totalDebit.', Kredit: '.$totalCredit);
            }

            $analyticIds = [];
            foreach ($lines as $line) {
                if (! is_array($line) || ! is_array($line['analytic_distribution'] ?? null)) {
                    continue;
                }
                foreach (array_keys($line['analytic_distribution']) as $id) {
                    if (is_numeric($id)) {
                        $analyticIds[] = (int) $id;
                    }
                }
            }

            $existingIds = $analyticIds === []
                ? []
                : AnalyticAccount::query()
                    ->whereIn('id', array_values(array_unique($analyticIds)))
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();
            $existingSet = array_flip($existingIds);

            foreach ($lines as $index => $line) {
                if (! is_array($line) || ! is_array($line['analytic_distribution'] ?? null)) {
                    continue;
                }

                foreach (array_keys($line['analytic_distribution']) as $id) {
                    if (! is_numeric($id) || ! isset($existingSet[(int) $id])) {
                        $validator->errors()->add(
                            "lines.{$index}.analytic_distribution",
                            'Akun analitik tidak ditemukan.'
                        );
                    }
                }
            }
        });
    }

    /**
     * Keep analytic account-id keys. Laravel rebuilds validated nested arrays
     * with numeric keys as lists ({"10":60,"20":40} -> [60,40]).
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if ($key !== null || ! is_array($validated) || ! is_array($validated['lines'] ?? null)) {
            return $validated;
        }

        $rawLines = $this->input('lines', []);

        foreach ($validated['lines'] as $index => $line) {
            if (! is_array($line) || ! array_key_exists('analytic_distribution', $line)) {
                continue;
            }

            $raw = is_array($rawLines) && is_array($rawLines[$index] ?? null)
                ? ($rawLines[$index]['analytic_distribution'] ?? null)
                : $line['analytic_distribution'];

            $validated['lines'][$index]['analytic_distribution'] = AnalyticDistributionCast::asObject($raw);
        }

        return $validated;
    }
}
