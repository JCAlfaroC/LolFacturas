<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductosSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('productos.csv');
        $handle = fopen($path, 'r');

        fgetcsv($handle); // skip header

        DB::table('productos')->truncate();

        $chunk = [];
        while (($row = fgetcsv($handle)) !== false) {
            $gtin   = trim($row[0] ?? '');
            $codigo = trim($row[1] ?? '');

            if ($gtin === '' && $codigo === '') continue;

            $chunk[] = [
                'gtin'                  => $gtin,
                'codigo_proveedor_item' => null,
                'codigo_producto'       => $codigo,
                'created_at'            => now(),
                'updated_at'            => now(),
            ];

            if (count($chunk) === 500) {
                DB::table('productos')->insert($chunk);
                $chunk = [];
            }
        }

        if ($chunk) {
            DB::table('productos')->insert($chunk);
        }

        fclose($handle);
    }
}
