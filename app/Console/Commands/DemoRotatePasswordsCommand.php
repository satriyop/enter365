<?php

namespace App\Console\Commands;

use Database\Seeders\Demo\DemoPassword;
use Illuminate\Console\Command;

class DemoRotatePasswordsCommand extends Command
{
    protected $signature = 'demo:rotate-passwords';

    protected $description = 'Set every seeded demo user to the shared strong password (no catalog changes)';

    public function handle(): int
    {
        $updated = DemoPassword::rotate();
        $this->info("Rotated {$updated} demo user password(s) to the shared DemoPassword.");

        return self::SUCCESS;
    }
}
