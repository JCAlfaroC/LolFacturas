<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProveedoresSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('proveedores.csv');
        $handle = fopen($path, 'r');

        fgetcsv($handle); // skip header

        DB::table('proveedores')->truncate();

        $chunk = [];
        while (($row = fgetcsv($handle)) !== false) {
            $codigo = trim($row[0] ?? '');
            $nombre = trim($row[1] ?? '');

            if ($codigo === '' && $nombre === '') continue;

            $chunk[] = [
                'codigo_proveedor' => $codigo,
                'nombre'           => $nombre,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];

            if (count($chunk) === 500) {
                DB::table('proveedores')->insert($chunk);
                $chunk = [];
            }
        }

        if ($chunk) {
            DB::table('proveedores')->insert($chunk);
        }

        fclose($handle);
    }
}
