<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/**
 * Kopitiam 57 cafe menu (Harga Cafe). Guest pays after 5% service + 10% PBJT.
 *
 * @phpstan-type MenuRow array{sku: string, name: string, category: string, price: int, cost: int, qty: int, track: bool, taxable: bool, barcode: string}
 */
final class KopitiamCafeMenu
{
    /**
     * @return list<MenuRow>
     */
    public static function items(): array
    {
        $barcode = 899057000101;
        $rows = [];

        foreach (self::definitions() as $row) {
            $price = $row['price'];
            $rows[] = [
                'sku' => $row['sku'],
                'name' => $row['name'],
                'category' => $row['category'],
                'price' => $price,
                'cost' => $row['cost'] ?? (int) round($price * 0.32),
                'qty' => $row['qty'] ?? 0,
                'track' => $row['track'] ?? false,
                'taxable' => false,
                'barcode' => $row['barcode'] ?? (string) $barcode++,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{sku: string, name: string, category: string, price: int, cost?: int, qty?: int, track?: bool, barcode?: string}>
     */
    private static function definitions(): array
    {
        return [
            // Dimsum
            ['sku' => 'KT57-HAKAU', 'name' => 'Hakau', 'category' => 'POS-DIM', 'price' => 22_000],
            ['sku' => 'KT57-SIOMAY', 'name' => 'Siomay', 'category' => 'POS-DIM', 'price' => 20_000, 'barcode' => '899057000013'],
            ['sku' => 'KT57-CEKER', 'name' => 'Ceker Ayam', 'category' => 'POS-DIM', 'price' => 22_000],
            ['sku' => 'KT57-LUMPIA-ST', 'name' => 'Lumpia Kulit Tahu Steam', 'category' => 'POS-DIM', 'price' => 25_000],
            ['sku' => 'KT57-LUMPIA-GR', 'name' => 'Lumpia Kulit Tahu Goreng', 'category' => 'POS-DIM', 'price' => 20_000],
            ['sku' => 'KT57-BAKPAO', 'name' => 'Bakpao Telur Asin', 'category' => 'POS-DIM', 'price' => 25_000],
            ['sku' => 'KT57-BOLAUD', 'name' => 'Bola Udang', 'category' => 'POS-DIM', 'price' => 22_000],
            ['sku' => 'KT57-WONTON-GR', 'name' => 'Wonton Goreng', 'category' => 'POS-DIM', 'price' => 22_000],
            ['sku' => 'KT57-MANTAU', 'name' => 'Mantau Goreng', 'category' => 'POS-DIM', 'price' => 25_000],
            ['sku' => 'KT57-DUMP-CO', 'name' => 'Dumpling Chili Oil', 'category' => 'POS-DIM', 'price' => 25_000],
            ['sku' => 'KT57-PRAWN-CK', 'name' => 'Prawn Cakwe', 'category' => 'POS-DIM', 'price' => 25_000],

            // Appetizer
            ['sku' => 'KT57-PISANG', 'name' => 'Pisang Goreng Madu', 'category' => 'POS-APP', 'price' => 25_000],
            ['sku' => 'KT57-SINGKONG', 'name' => 'Singkong Goreng', 'category' => 'POS-APP', 'price' => 25_000],
            ['sku' => 'KT57-TAHU-KP', 'name' => 'Tahu Kipas', 'category' => 'POS-APP', 'price' => 25_000],
            ['sku' => 'KT57-TAHU-LG', 'name' => 'Tahu Lada Garam', 'category' => 'POS-APP', 'price' => 20_000],
            ['sku' => 'KT57-CANAI', 'name' => 'Canai Kari', 'category' => 'POS-APP', 'price' => 35_000],

            // Toast
            ['sku' => 'KT57-KAYA-TST', 'name' => 'Kaya Butter Toast', 'category' => 'POS-TOS', 'price' => 25_000],
            ['sku' => 'KT57-FRENCH', 'name' => 'French Toast', 'category' => 'POS-TOS', 'price' => 40_000],
            ['sku' => 'KT57-BREADED', 'name' => 'Breaded Toast', 'category' => 'POS-TOS', 'price' => 40_000],

            // Bubur & Sup
            ['sku' => 'KT57-BUBUR-AY', 'name' => 'Bubur Ayam Kwangtung', 'category' => 'POS-BUB', 'price' => 40_000],
            ['sku' => 'KT57-BUBUR-SF', 'name' => 'Bubur Seafood Kwangtung', 'category' => 'POS-BUB', 'price' => 40_000],
            ['sku' => 'KT57-SUP-WT', 'name' => 'Sup Wonton', 'category' => 'POS-BUB', 'price' => 35_000],

            // Nasi
            ['sku' => 'KT57-NG-YZ', 'name' => 'Nasi Goreng Yangzhou', 'category' => 'POS-NAS', 'price' => 50_000],
            ['sku' => 'KT57-NG-KCB', 'name' => 'Nasi Goreng Kecombrang', 'category' => 'POS-NAS', 'price' => 50_000],
            ['sku' => 'KT57-NG-ROA', 'name' => 'Nasi Goreng Sambal Roa', 'category' => 'POS-NAS', 'price' => 50_000],
            ['sku' => 'KT57-RICE-CHF', 'name' => 'Rice Chiffon Egg Chicken Charsiu', 'category' => 'POS-NAS', 'price' => 55_000],
            ['sku' => 'KT57-RICE-KUN', 'name' => 'Rice Chicken Kungpao', 'category' => 'POS-NAS', 'price' => 55_000],
            ['sku' => 'KT57-NLEM-AY', 'name' => 'Nasi Lemak Ayam Kandar', 'category' => 'POS-NAS', 'price' => 45_000],
            ['sku' => 'KT57-NLEM-BF', 'name' => 'Nasi Lemak Beef Charsiu', 'category' => 'POS-NAS', 'price' => 60_000],
            ['sku' => 'KT57-RICE-SE', 'name' => 'Rice Chicken Salted Egg', 'category' => 'POS-NAS', 'price' => 45_000],
            ['sku' => 'KT57-BEEF-GB', 'name' => 'Beef Garlic Butter Rice', 'category' => 'POS-NAS', 'price' => 60_000],
            ['sku' => 'KT57-HAINAN', 'name' => 'Nasi Ayam Hainan', 'category' => 'POS-NAS', 'price' => 45_000, 'barcode' => '899057000010'],

            // Mie
            ['sku' => 'KT57-MALA-XG', 'name' => 'Ma La Xiang Guo', 'category' => 'POS-MIE', 'price' => 55_000],
            ['sku' => 'KT57-MALA-TG', 'name' => 'Ma La Tang', 'category' => 'POS-MIE', 'price' => 55_000],
            ['sku' => 'KT57-MIE-KARI', 'name' => 'Mie Kari', 'category' => 'POS-MIE', 'price' => 40_000],
            ['sku' => 'KT57-MIE-SAPI', 'name' => 'Mie Goreng Sapi', 'category' => 'POS-MIE', 'price' => 45_000],
            ['sku' => 'KT57-MIE-SF', 'name' => 'Mie Siram Seafood', 'category' => 'POS-MIE', 'price' => 45_000],
            ['sku' => 'KT57-KWET-GR', 'name' => 'Kwetiau Goreng Sapi', 'category' => 'POS-MIE', 'price' => 50_000],
            ['sku' => 'KT57-KWET-SF', 'name' => 'Kwetiau Siram Seafood', 'category' => 'POS-MIE', 'price' => 45_000],

            // Tofu
            ['sku' => 'KT57-TOFU-SH', 'name' => 'Tofu Siram Shimeji', 'category' => 'POS-TOF', 'price' => 40_000],
            ['sku' => 'KT57-TOFU-BF', 'name' => 'Tofu Siram Beef', 'category' => 'POS-TOF', 'price' => 45_000],
            ['sku' => 'KT57-TOFU-AU', 'name' => 'Tofu Siram Ayam Udang', 'category' => 'POS-TOF', 'price' => 40_000],
            ['sku' => 'KT57-TOFU-BY', 'name' => 'Tofu Siram Bayam', 'category' => 'POS-TOF', 'price' => 40_000],

            // Kopi
            ['sku' => 'KT57-KOPI-O', 'name' => 'Kopi O', 'category' => 'POS-KOP', 'price' => 15_000, 'barcode' => '899057000001'],
            ['sku' => 'KT57-KOPI-PENG', 'name' => 'Kopi Peng', 'category' => 'POS-KOP', 'price' => 20_000],
            ['sku' => 'KT57-KOPI-OBT', 'name' => 'Kopi O Butter', 'category' => 'POS-KOP', 'price' => 20_000],
            ['sku' => 'KT57-KOPI-OCN', 'name' => 'Kopi O Cinnamon', 'category' => 'POS-KOP', 'price' => 15_000],
            ['sku' => 'KT57-KOPI-TB', 'name' => 'Kopi Tubruk', 'category' => 'POS-KOP', 'price' => 15_000],
            ['sku' => 'KT57-KOPI-TBS', 'name' => 'Kopi Tubruk Susu', 'category' => 'POS-KOP', 'price' => 18_000],
            ['sku' => 'KT57-KOPI-TRK', 'name' => 'Kopi Tarik', 'category' => 'POS-KOP', 'price' => 25_000, 'barcode' => '899057000002'],
            ['sku' => 'KT57-KOPI-TRK-L', 'name' => 'Kopi Tarik Large', 'category' => 'POS-KOP', 'price' => 28_000],
            ['sku' => 'KT57-KOPI-MLK', 'name' => 'Kopi Tarik Malaka', 'category' => 'POS-KOP', 'price' => 25_000],
            ['sku' => 'KT57-KOPI-SGR', 'name' => 'Kopi Sanger', 'category' => 'POS-KOP', 'price' => 15_000],
            ['sku' => 'KT57-KOPI-CDL', 'name' => 'Kopi Cendol Malaka', 'category' => 'POS-KOP', 'price' => 28_000],
            ['sku' => 'KT57-KOPI-MKA', 'name' => 'Kopi Moka Cincau', 'category' => 'POS-KOP', 'price' => 28_000],
            ['sku' => 'KT57-KOPI-W57', 'name' => 'Kopi Susu Warga 57', 'category' => 'POS-KOP', 'price' => 25_000],
            ['sku' => 'KT57-KOPI-TWR', 'name' => 'Kopi Tower Warga Maju', 'category' => 'POS-KOP', 'price' => 80_000],
            ['sku' => 'KT57-KOPI-YY', 'name' => 'Kopi Yin Yang', 'category' => 'POS-KOP', 'price' => 25_000],
            ['sku' => 'KT57-KOPI-ORG', 'name' => 'Kopi Orange', 'category' => 'POS-KOP', 'price' => 28_000],
            ['sku' => 'KT57-VN-DRIP', 'name' => 'Vietnam Drip', 'category' => 'POS-KOP', 'price' => 20_000],

            // Teh
            ['sku' => 'KT57-TEH-O', 'name' => 'Teh O', 'category' => 'POS-TEH', 'price' => 10_000],
            ['sku' => 'KT57-TEH-PCI', 'name' => 'Teh Poci', 'category' => 'POS-TEH', 'price' => 15_000],
            ['sku' => 'KT57-WEDANG', 'name' => 'Wedang Uwuh', 'category' => 'POS-TEH', 'price' => 18_000],
            ['sku' => 'KT57-OOLONG', 'name' => 'Oolong Tea', 'category' => 'POS-TEH', 'price' => 20_000],
            ['sku' => 'KT57-JASMINE', 'name' => 'Jasmine Tea', 'category' => 'POS-TEH', 'price' => 20_000],
            ['sku' => 'KT57-EARL', 'name' => 'Earlgrey Tea', 'category' => 'POS-TEH', 'price' => 20_000],
            ['sku' => 'KT57-THAI-TRK', 'name' => 'Thai Tea Tarik', 'category' => 'POS-TEH', 'price' => 25_000],
            ['sku' => 'KT57-THAI-TRK-L', 'name' => 'Thai Tea Tarik Large', 'category' => 'POS-TEH', 'price' => 28_000],
            ['sku' => 'KT57-GT-TRK', 'name' => 'Green Tea Tarik', 'category' => 'POS-TEH', 'price' => 25_000],
            ['sku' => 'KT57-GT-TRK-L', 'name' => 'Green Tea Tarik Large', 'category' => 'POS-TEH', 'price' => 28_000],
            ['sku' => 'KT57-LEMON-T', 'name' => 'Lemon Tea', 'category' => 'POS-TEH', 'price' => 20_000],
            ['sku' => 'KT57-LYCHEE-T', 'name' => 'Lychee Tea', 'category' => 'POS-TEH', 'price' => 25_000],
            ['sku' => 'KT57-TEH-TRK', 'name' => 'Teh Tarik', 'category' => 'POS-TEH', 'price' => 25_000, 'barcode' => '899057000003'],
            ['sku' => 'KT57-TEH-TRK-L', 'name' => 'Teh Tarik Large', 'category' => 'POS-TEH', 'price' => 28_000],
            ['sku' => 'KT57-TEH-CNC', 'name' => 'Teh Tarik Cincau', 'category' => 'POS-TEH', 'price' => 28_000],
            ['sku' => 'KT57-TEH-MLK', 'name' => 'Teh Tarik Malaka', 'category' => 'POS-TEH', 'price' => 25_000],
            ['sku' => 'KT57-JAS-CHS', 'name' => 'Jasmine Cheese Tea', 'category' => 'POS-TEH', 'price' => 25_000],
            ['sku' => 'KT57-OOL-CHS', 'name' => 'Oolong Cheese Tea', 'category' => 'POS-TEH', 'price' => 25_000],
            ['sku' => 'KT57-EARL-CHS', 'name' => 'Earlgrey Cheese Tea', 'category' => 'POS-TEH', 'price' => 25_000],
            ['sku' => 'KT57-TEH-TWR', 'name' => 'Teh Tower Warga 57', 'category' => 'POS-TEH', 'price' => 60_000],
            ['sku' => 'KT57-LOHAN', 'name' => 'Lo Han Guo', 'category' => 'POS-TEH', 'price' => 20_000],

            // Milk based
            ['sku' => 'KT57-COK-57', 'name' => 'Coklat 57', 'category' => 'POS-MLK', 'price' => 20_000],
            ['sku' => 'KT57-STMJ', 'name' => 'STMJ 57', 'category' => 'POS-MLK', 'price' => 25_000],
            ['sku' => 'KT57-MILO-MY', 'name' => 'Milo Malay', 'category' => 'POS-MLK', 'price' => 25_000],
            ['sku' => 'KT57-COK-PK', 'name' => 'Coklat Pisang Keju 57', 'category' => 'POS-MLK', 'price' => 30_000],
            ['sku' => 'KT57-COK-TRM', 'name' => 'Coklat Tiramisu', 'category' => 'POS-MLK', 'price' => 30_000],
            ['sku' => 'KT57-BUKAJO', 'name' => 'Bukajo 57', 'category' => 'POS-MLK', 'price' => 28_000],
            ['sku' => 'KT57-CENDOL-S', 'name' => 'Cendol Susu Aren', 'category' => 'POS-MLK', 'price' => 25_000],
            ['sku' => 'KT57-CKN-CRM', 'name' => 'Cookies & Cream', 'category' => 'POS-MLK', 'price' => 25_000],
            ['sku' => 'KT57-UBE', 'name' => 'Ube Frappe', 'category' => 'POS-MLK', 'price' => 25_000],
            ['sku' => 'KT57-MATCHA', 'name' => 'Matcha Frappe', 'category' => 'POS-MLK', 'price' => 25_000],

            // Jus
            ['sku' => 'KT57-JERUK', 'name' => 'Jeruk', 'category' => 'POS-JUS', 'price' => 18_000],
            ['sku' => 'KT57-GDETOX', 'name' => 'Green Detox', 'category' => 'POS-JUS', 'price' => 28_000],
            ['sku' => 'KT57-WM-ORG', 'name' => 'Watermelon Orange', 'category' => 'POS-JUS', 'price' => 28_000],
            ['sku' => 'KT57-JERUK-KL', 'name' => 'Jeruk Kelapa', 'category' => 'POS-JUS', 'price' => 25_000],
            ['sku' => 'KT57-HONEY-LM', 'name' => 'Honey Lemon Selasih', 'category' => 'POS-JUS', 'price' => 20_000],
            ['sku' => 'KT57-IMMUNE', 'name' => 'Immune Booster', 'category' => 'POS-JUS', 'price' => 28_000],

            // Smoothies
            ['sku' => 'KT57-SM-MNG', 'name' => 'Mangga Smoothie', 'category' => 'POS-SMH', 'price' => 25_000],
            ['sku' => 'KT57-SM-STRW', 'name' => 'Strawberry Smoothie', 'category' => 'POS-SMH', 'price' => 25_000],
            ['sku' => 'KT57-SM-MBRY', 'name' => 'Mango Berry Smoothie', 'category' => 'POS-SMH', 'price' => 28_000],
            ['sku' => 'KT57-SM-DRGN', 'name' => 'Dragon Sirsak Smoothie', 'category' => 'POS-SMH', 'price' => 28_000],
            ['sku' => 'KT57-SM-MIX', 'name' => 'Mixberry Smoothie', 'category' => 'POS-SMH', 'price' => 25_000],
            ['sku' => 'KT57-SM-JCK', 'name' => 'Jackfruit Coco Brulee Smoothie', 'category' => 'POS-SMH', 'price' => 28_000],

            // Float
            ['sku' => 'KT57-FL-COK', 'name' => 'Coklat Float', 'category' => 'POS-FLT', 'price' => 20_000],
            ['sku' => 'KT57-FL-STR', 'name' => 'Strawberry Float', 'category' => 'POS-FLT', 'price' => 20_000],
            ['sku' => 'KT57-FL-MNG', 'name' => 'Mangga Float', 'category' => 'POS-FLT', 'price' => 20_000],
            ['sku' => 'KT57-FL-THAI', 'name' => 'Thai Tea Float', 'category' => 'POS-FLT', 'price' => 20_000],

            // Extra
            ['sku' => 'KT57-AIR', 'name' => 'Air Mineral', 'category' => 'POS-XTR', 'price' => 8_000],
            ['sku' => 'KT57-BADAK', 'name' => 'Cap Badak Siantar', 'category' => 'POS-XTR', 'price' => 20_000],
            ['sku' => 'KT57-TELUR-HM', 'name' => 'Telur 1/2 Matang', 'category' => 'POS-XTR', 'price' => 10_000],
            ['sku' => 'KT57-GULA', 'name' => 'Gula Cair', 'category' => 'POS-XTR', 'price' => 5_000],
            ['sku' => 'KT57-SKM', 'name' => 'SKM', 'category' => 'POS-XTR', 'price' => 5_000],
            ['sku' => 'KT57-CINCAU', 'name' => 'Cincau', 'category' => 'POS-XTR', 'price' => 5_000],
            ['sku' => 'KT57-MILK', 'name' => 'Fresh Milk', 'category' => 'POS-XTR', 'price' => 10_000],
            ['sku' => 'KT57-CENDOL', 'name' => 'Cendol', 'category' => 'POS-XTR', 'price' => 5_000],

            // Pastry (PDF board — cafe prices)
            ['sku' => 'KT57-SB-GARLIC', 'name' => 'Salt Bread Garlic Cheese', 'category' => 'POS-ROT', 'price' => 28_000, 'cost' => 11_000, 'qty' => 20, 'track' => true, 'barcode' => '899057000016'],
            ['sku' => 'KT57-SB-DBL', 'name' => 'Salt Bread Double Cheese', 'category' => 'POS-ROT', 'price' => 23_000, 'cost' => 9_000, 'qty' => 20, 'track' => true, 'barcode' => '899057000017'],
            ['sku' => 'KT57-SB-ORI', 'name' => 'Salt Bread Original', 'category' => 'POS-ROT', 'price' => 18_000, 'cost' => 7_000, 'qty' => 20, 'track' => true, 'barcode' => '899057000018'],
            ['sku' => 'KT57-SMEER', 'name' => 'Roti Smeer Meses', 'category' => 'POS-ROT', 'price' => 22_000, 'cost' => 9_000, 'qty' => 20, 'track' => true, 'barcode' => '899057000019'],
            ['sku' => 'KT57-CROISS-BT', 'name' => 'Butter Croissant', 'category' => 'POS-ROT', 'price' => 23_000, 'cost' => 9_000, 'qty' => 20, 'track' => true, 'barcode' => '899057000020'],
            ['sku' => 'KT57-SB-BEEF', 'name' => 'Salt Bread Smoked Beef & Cheese', 'category' => 'POS-ROT', 'price' => 40_000, 'cost' => 16_000, 'qty' => 20, 'track' => true, 'barcode' => '899057000021'],
            ['sku' => 'KT57-CROISS-CH', 'name' => 'Double Choco Croissant', 'category' => 'POS-ROT', 'price' => 45_000, 'cost' => 18_000, 'qty' => 20, 'track' => true, 'barcode' => '899057000022'],
        ];
    }
}
