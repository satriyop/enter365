<?php

namespace Database\Seeders\Demo;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Shared secret for every seeded demo login (Kopitiam + trading/NEX/Vahana).
 * Not a per-user secret — rotate before handing a real till to a human.
 */
final class DemoPassword
{
    public const VALUE = 'WRGKvh-WU#pL5ii#mufZXxpW';

    /**
     * @var list<string>
     */
    public const EMAILS = [
        'admin@example.com',
        'admin@demo.com',
        'sales@demo.com',
        'purchasing@demo.com',
        'produksi@demo.com',
        'finance@demo.com',
        'gudang@demo.com',
        'siti@kopitiam57.test',
        'rina@kopitiam57.test',
        'dewi@kopitiam57.test',
    ];

    public static function hash(): string
    {
        return Hash::make(self::VALUE);
    }

    public static function rotate(): int
    {
        $updated = 0;

        foreach (self::EMAILS as $email) {
            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                continue;
            }

            $user->password = self::hash();
            $user->save();
            $updated++;
        }

        return $updated;
    }
}
