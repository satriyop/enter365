<?php

declare(strict_types=1);

namespace App\Services\Accounting\BankImport;

use App\Services\Accounting\BankImport\Contracts\BankStatementParserInterface;
use App\Services\Accounting\BankImport\Parsers\BcaBankStatementParser;
use App\Services\Accounting\BankImport\Parsers\BniBankStatementParser;
use App\Services\Accounting\BankImport\Parsers\GenericBankStatementParser;
use App\Services\Accounting\BankImport\Parsers\MandiriBankStatementParser;
use Illuminate\Http\UploadedFile;

class BankStatementFormatDetector
{
    /**
     * @var array<BankStatementParserInterface>
     */
    private array $parsers;

    public function __construct()
    {
        // Register parsers in priority order (specific banks first, generic last)
        $this->parsers = [
            new BcaBankStatementParser,
            new MandiriBankStatementParser,
            new BniBankStatementParser,
            new GenericBankStatementParser,
        ];
    }

    /**
     * Detect format and return appropriate parser.
     *
     * @return array{parser: BankStatementParserInterface, bank: string, date_format: string, delimiter: string}
     *
     * @throws \RuntimeException if file cannot be read or parsed
     */
    public function detect(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new \RuntimeException('Could not open file for reading');
        }

        try {
            // Read first 2KB to detect delimiter
            $sample = fread($handle, 2048);
            if ($sample === false) {
                throw new \RuntimeException('Could not read file');
            }

            rewind($handle);

            // Detect delimiter
            $delimiter = $this->detectDelimiter($sample);

            // Read headers
            $headers = fgetcsv($handle, 0, $delimiter);
            if ($headers === false || $headers[0] === null) {
                throw new \RuntimeException('Could not read CSV headers');
            }

            // Read first data row
            $firstRow = fgetcsv($handle, 0, $delimiter);
            if ($firstRow === false || $firstRow[0] === null) {
                throw new \RuntimeException('No data rows found in CSV');
            }

            // Try each parser
            foreach ($this->parsers as $parser) {
                if ($parser->canParse($headers, $firstRow)) {
                    return [
                        'parser' => $parser,
                        'bank' => $parser->getBankIdentifier(),
                        'date_format' => $parser->getDateFormat(),
                        'delimiter' => $delimiter,
                    ];
                }
            }

            throw new \RuntimeException('Could not detect bank statement format');
        } finally {
            fclose($handle);
        }
    }

    /**
     * Detect CSV delimiter from sample text.
     */
    private function detectDelimiter(string $sample): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $counts = [];

        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = substr_count($sample, $delimiter);
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }
}
