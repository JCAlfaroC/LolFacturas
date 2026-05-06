<?php

namespace App\Services;
use Illuminate\Support\Facades\DB;

class TxtGenerator
{
    /**
     * Generate LolFar TXT content from raw parsed invoice data.
     * Columns that require LolFar internal codes (not present in the XML)
     * are output as '' or '0' so the file can be imported and completed
     * manually inside LolFar afterwards.
     */
    public function generate(array $data): string
    {
        $header = $data['header'];
        $lines  = $this->mergeLines($data['lines']);

        $diasPago = 0;
        if (!empty($header['fecha']) && !empty($header['fecha_vcto'])) {
            $issue    = new \DateTime($header['fecha']);
            $due      = new \DateTime($header['fecha_vcto']);
            $diasPago = (int) $issue->diff($due)->days;
        }

        $importedAt = date('d/m/Y H:i:s');
        $fechaDoc   = date('d/m/Y', strtotime($header['fecha'])) . ' 00:00:00';
        $fechaVcto  = !empty($header['fecha_vcto'])
            ? date('d/m/Y', strtotime($header['fecha_vcto'])) . ' 00:00:00'
            : '';

        $orderNum = $this->readCounter();

        $codigoProveedor = $this->resolveProveedorCode($header['nom_emisor']);
        $rows = [];
        foreach ($lines as $line) {
            $cols = [
                /* 01 */ (string) $orderNum,                                   // sequential order ID (persisted counter)
                /* 02 */ $this->resolveProductCode($line['codigo']),        // cod_producto      (LolFar internal code resolved from GTIN)
                /* 02 */ // $line['codigo'],  // PRODUCTOS INFORMACION COMPLETAR                                       // cod_producto      (seller's item code from XML)
                /* 03 */ '0',
                /* 04 */ '0',
                /* 05 */ (string) $line['bonif_qty'],                          // bonif quantity merged from bonif line
                /* 06 */ $this->fmt($line['cantidad']),                        // InvoicedQuantity
                /* 07 */ '0',
                /* 08 */ $this->fmt((float)$line['precio_con_igv'] / 1.18),    // PRECIO SIN IGV   // reference price sin IGV
                /* 09 */ $this->fmt($line['precio_con_igv']),                  // PRECIO  CON IGV  // reference price con IGV (AlternativeConditionPrice)
                /* 10 */ $this->fmt($line['precio_unit']),                     // PRECIO CON IGV   // invoiced unit price sin IGV (Price/PriceAmount)
                /* 11 */ '0',
                /* 12 */ '0',
                /* 13 */ '0',
                /* 14 */ '0',
                /* 15 */ '',
                /* 16 */ $this->fmt($line['val_venta']),                        // LineExtensionAmount (subtotal sin IGV)
                /* 17 */ $this->fmt((float)$line['val_venta'] - ((float)$line['precio_con_igv'] / 1.18) * (float)$line['cantidad']),
                /* 18 */ (string) $orderNum,                                   // same sequential ID as col01
                /* 19 */ 'A2',                                                   // cod_zona          (LolFar internal)
                /* 20 */ 'CC',                                                   // codigo de tipo de movimiento Cargo Por Compra CC,                                                    // cod_tipo          (LolFar internal)
                /* 21 */ $header['numero'],
                /* 22 */ '0',
                /* 23 */ $fechaDoc,
                /* 24 */ $importedAt,
                /* 25 */ $header['forma_pago'],
                /* 26 */ $this->fmt($header['total_pagar']),
                /* 27 */ '0',
                /* 28 */ '0',
                /* 29 */ $this->fmt($header['igv_total']),
                /* 30 */ '0',
                /* 31 */ '',
                /* 32 */ '',
                /* 33 */ $codigoProveedor,                                                   // cod_proveedor     (LolFar internal — e.g. 'MM')
                /* 34 */ '0',
                /* 35 */ '0',
                /* 36 */ $header['moneda'],                                    // currency: SO=soles, US=dollars (DocumentCurrencyCode)
                /* 37 */ '',
                /* 38 */ '43027',             // asignado por el sistema al movimiento de compra  // cod_interno       (LolFar internal)
                /* 39 */ $fechaVcto,
                /* 40 */ $header['serie'],
                /* 41 */ '',
                /* 42 */ (string) $diasPago,
                /* 43 */ $header['tipo_doc'],
                /* 44 */ '',
                /* 45 */ 'N',  
                /* 46 */ '0',
                /* 47 */ '',
                /* 48 */ '',
                /* 49 */ '',
                /* 50 */ '',
                /* 51 */ '0',
                /* 52 */ 'N', 
                /* 53 */ '0',
            ];

            $rows[] = implode("\t", $cols);
        }

        $this->saveCounter($orderNum + 1);

        return implode("\r\n", $rows) . "\r\n";
    }

    /**
     * Absorb bonif lines (PriceTypeCode 02) into the previous regular line
     * as bonif_qty, then drop them so they don't produce a separate TXT row.
     */
    private function mergeLines(array $lines): array
    {
        $result  = [];
        $lastIdx = -1;

        foreach ($lines as $line) {
            if ($line['es_bonif']) {
                if ($lastIdx >= 0) {
                    $result[$lastIdx]['bonif_qty'] = (int) $line['cantidad'];
                }
                continue;
            }

            $result[]  = array_merge($line, ['bonif_qty' => 0]);
            $lastIdx   = count($result) - 1;
        }

        return $result;
    }

    private function readCounter(): int
    {
        $path = storage_path('app/order_counter.txt');
        return file_exists($path) ? (int) file_get_contents($path) : 91283;
    }

    private function saveCounter(int $value): void
    {
        file_put_contents(storage_path('app/order_counter.txt'), $value);
    }

    private function resolveProductCode(string $gtin): string
    {
        return DB::table('productos')
            ->where('gtin', trim($gtin))
            ->value('codigo_producto') ?? '';
    }

    private function resolveProveedorCode(string $nombre): string
    {
        return DB::table('proveedores')
            ->whereRaw('LOWER(nombre) = ?', [strtolower(trim($nombre))])
            ->value('codigo_proveedor') ?? '';
    }

    private function fmt(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
