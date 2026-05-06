<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ImportCsvSeeder extends Seeder
{
    public function run(): void
    {
        $this->importProveedores();
        $this->importProductos();
    }

    private function importProveedores(): void
    {
        $file = fopen(database_path('proveedores.csv'), 'r');
        fgetcsv($file); // skip header

        $batch = [];
        while (($row = fgetcsv($file)) !== false) {
            $batch[] = [
                'codigo_proveedor' => trim($row[0]),
                'nombre'           => trim($row[1]),
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }
        fclose($file);

        DB::table('proveedores')->insert($batch);
        $this->command->info('Proveedores imported: ' . count($batch));
    }

    private function importProductos(): void
    {
        $file = fopen(database_path('productos.csv'), 'r');
        fgetcsv($file); // skip header

        $batch = [];
        $count = 0;

        while (($row = fgetcsv($file)) !== false) {
            $batch[] = [
                'GTIN'            => trim($row[0]),
                'codigo_producto' => trim($row[1]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ];

            if (count($batch) === 500) {
                DB::table('productos')->insert($batch);
                $count += 500;
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('productos')->insert($batch);
            $count += count($batch);
        }

        fclose($file);
        $this->command->info('Productos imported: ' . $count);
    }
}
