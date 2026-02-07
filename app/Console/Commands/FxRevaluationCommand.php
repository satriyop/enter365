<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Accounting\FxRevaluationServiceInterface;
use Illuminate\Console\Command;

class FxRevaluationCommand extends Command
{
    protected $signature = 'accounting:fx-revaluation
        {date : Balance sheet date (YYYY-MM-DD)}
        {--rates=* : Closing rates as CURRENCY:RATE pairs (e.g. USD:15800)}
        {--reverse : Also create reversal JE for next day}
        {--dry-run : Preview without persisting}';

    protected $description = 'Revalue open foreign-currency AR/AP at closing rate (PSAK 10 unrealized FX)';

    public function handle(FxRevaluationServiceInterface $service): int
    {
        $date = $this->argument('date');
        $closingRates = $this->parseRates();

        if (empty($closingRates)) {
            $this->error('Tidak ada kurs yang diberikan. Gunakan --rates=USD:15800 --rates=EUR:17200');

            return self::FAILURE;
        }

        $this->info("Revaluasi selisih kurs per {$date}");
        $this->table(['Mata Uang', 'Kurs Penutup'], collect($closingRates)->map(
            fn (float $rate, string $currency) => [$currency, number_format($rate, 4)]
        )->values()->toArray());

        // Preview
        $preview = $service->preview($date, $closingRates);

        if (empty($preview['items'])) {
            $this->info('Tidak ada transaksi valuta asing yang perlu direvaluasi.');

            return self::SUCCESS;
        }

        /** @var array<int, array{type: string, reference: string, currency: string, outstanding: int, booked_rate: float, closing_rate: float, booked_base: int, revalued_base: int, unrealized_fx: int}> $items */
        $items = $preview['items'];

        $this->table(
            ['Tipe', 'Referensi', 'Mata Uang', 'Outstanding', 'Kurs Awal', 'Kurs Penutup', 'Selisih IDR'],
            array_map(fn (array $item) => [
                $item['type'] === 'invoice' ? 'Piutang' : 'Utang',
                $item['reference'],
                $item['currency'],
                number_format($item['outstanding']),
                number_format($item['booked_rate'], 4),
                number_format($item['closing_rate'], 4),
                number_format($item['unrealized_fx']),
            ], $items)
        );

        $this->info('Total keuntungan belum terealisasi: '.number_format($preview['total_gain']).' IDR');
        $this->info('Total kerugian belum terealisasi: '.number_format($preview['total_loss']).' IDR');

        if ($this->option('dry-run')) {
            $this->warn('Mode dry-run: tidak ada jurnal yang dibuat.');

            return self::SUCCESS;
        }

        // Execute
        $entries = $service->revalue($date, $closingRates, (bool) $this->option('reverse'));

        foreach ($entries as $entry) {
            $this->info("Jurnal dibuat: {$entry->entry_number} — {$entry->description}");
        }

        return self::SUCCESS;
    }

    /**
     * Parse --rates=CURRENCY:RATE options into an array.
     *
     * @return array<string, float>
     */
    private function parseRates(): array
    {
        $rates = [];
        foreach ($this->option('rates') as $rateStr) {
            if (str_contains($rateStr, ':')) {
                [$currency, $rate] = explode(':', $rateStr, 2);
                $rates[strtoupper(trim($currency))] = (float) trim($rate);
            }
        }

        return $rates;
    }
}
