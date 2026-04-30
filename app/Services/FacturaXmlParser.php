<?php

namespace App\Services;

class FacturaXmlParser
{
    private \SimpleXMLElement $xml;

    private array $ns = [
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
    ];

    public function parse(string $xmlContent): array
    {
        // SimpleXMLElement convert the raw XML text into an object
        // we can navigate like $xml->children()=>ID
        // Without this, the XML is just a plain string we can't query
        $this->xml = new \SimpleXMLElement($xmlContent);

        // We split the work into two methods:
        // =parseHeader() handles document-level data (dates, RUCs, totals)
        // -parseLines() handles each product line inside the invoice
        // keeping them separate makes it easier to debug and modify later
        return [
            'header' => $this->parseHeader(),
            'lines'  => $this->parseLines(),
        ];
    }

    private function parseHeader(): array
    {
        $x = $this->xml;
        
        // Read the full document ID (e.g. "F100-841975) and split it 
        // into series and numero so LolFar can use them separately
        $id     = (string) $x->children($this->ns['cbc'])->ID;
        $parts  = explode('-', $id, 2);
        $serie  = $parts[0] ?? '';
        $numero = $parts[1] ?? '';

        // InvoiceTypeCode: 01=Factura, 03=Boleta, 07=Nota Credito, 08=Nota Debito
        $typeCode = (string) $x->children($this->ns['cbc'])->InvoiceTypeCode;
        $tipoDoc  = match($typeCode) {
            '01'    => 'FA',
            '03'    => 'BO',
            '07'    => 'NC',
            '08'    => 'ND',
            default => $typeCode, 
        };

        //Issue date and time as separate fields in SUNAT XML
        $issueDate  = (string) $x->children($this->ns['cbc'])->IssueDate;
        $issueTime  = (string) $x->children($this->ns['cbc'])->IssueTime;
        $dueDate    = (string) $x->children($this->ns['cbc'])->DueDate;

        // Payment condition and due date — some invoices put PaymentDueDate inside
        // a PaymentTerms/Cuota* block instead of at the root cbc:DueDate level
        $formaPago  = '';
        foreach ($x->children($this->ns['cac'])->PaymentTerms as $pt) {
            $ptId = (string) $pt->children($this->ns['cbc'])->ID;
            if ($ptId === 'FormaPago') {
                $medio     = (string) $pt->children($this->ns['cbc'])->PaymentMeansID;
                $formaPago = strtolower($medio) === 'contado' ? 'CD' : 'CR';
            }
            if (empty($dueDate)) {
                $ptDue = (string) $pt->children($this->ns['cbc'])->PaymentDueDate;
                if (!empty($ptDue)) {
                    $dueDate = $ptDue;
                }
            }
        }

        // Supplier (emisor): RUC and company name
        $supplier   = $x->children($this->ns['cac'])->AccountingSupplierParty
                        ->children($this->ns['cac'])->Party;
        $rucEmisor  = (string) $supplier->children($this->ns['cac'])->PartyIdentification
                        ->children($this->ns['cbc'])->ID;
        $nomEmisor  = (string) $supplier->children($this->ns['cac'])->PartyName
                        ->children($this->ns['cbc'])->Name;
        
        // Document currency: PEN (soles) → SO, USD → US
        $currencyCode = (string) $x->children($this->ns['cbc'])->DocumentCurrencyCode;
        $moneda = match($currencyCode) {
            'PEN'   => 'SO',
            'USD'   => 'US',
            default => $currencyCode,
        };

        // Customer (receptor): RUC and company name
        $custParty  = $x->children($this->ns['cac'])->AccountingCustomerParty;
        $customer   = $custParty->children($this->ns['cac'])->Party;
        $rucRecep   = (string) $customer->children($this->ns['cac'])->PartyIdentification
                        ->children($this->ns['cbc'])->ID;
        $nomRecep   = (string) $customer->children($this->ns['cac'])->PartyLegalEntity
                        ->children($this->ns['cbc'])->RegistrationName;

        // Totals from LegalMonetaryTotal
        $monetary   = $x->children($this->ns['cac'])->LegalMonetaryTotal;
        $baseImpon  = (string) $monetary->children($this->ns['cbc'])->LineExtensionAmount;
        $totalPagar = (string) $monetary->children($this->ns['cbc'])->PayableAmount;

        //IGV is the TazTotal/TalAmount in the document
        $igvTotal = '0.00';
        foreach ($x->children($this->ns['cac'])->TaxTotal as $taxTotal) {
            $igvTotal = (string) $taxTotal->children($this->ns['cbc'])->TaxAmount;
            break;
        }

        return [
            'serie'         => $serie,
            'numero'        => $numero,
            'tipo_doc'      => $tipoDoc,
            'fecha'         => $issueDate,
            'hora'          => $issueTime,
            'fecha_vcto'    => $dueDate,
            'forma_pago'    => $formaPago,
            'ruc_emisor'    => $rucEmisor,
            'nom_emisor'    => trim($nomEmisor),
            'moneda'        => $moneda,
            'ruc_recep'     => $rucRecep,
            'nom_recep'     => trim($nomRecep),
            'base_impon'    => $baseImpon,
            'igv_total'     => $igvTotal,
            'total_pagar'   => $totalPagar,
        ];
    }

    private function parseLines(): array
    {
        $lines = [];

        // Loop through every <cac:InvoiceLine> in the document
        // Each one is a product row in the invoice 
        foreach ($this->xml->children($this->ns['cac'])->InvoiceLine as $line){

            // Line number (1, 2, 3...)
            $nro        = (string) $line->children($this->ns['cbc'])->ID;

            // Quantity and unit of measure (e.g. 3 BX)
            $cantidad   = (string) $line->children($this->ns['cbc'])->InvoicedQuantity;
            $unidad     = (string) $line->children($this->ns['cbc'])->InvoicedQuantity
                            ->attributes()['unitCode'];

            // Total value for this line (quantity * unit price after discount)
            $valVenta   = (string) $line->children($this->ns['cbc'])->LineExtensionAmount;

            // Product code and description from the supplier's catalogue
            $item       = $line->children($this->ns['cac'])->Item;
            $descripcion = (string) $item->children($this->ns['cbc'])->Description;
            $codigo     = (string) $item->children($this->ns['cac'])->SellersItemIdentification
                            ->children($this->ns['cbc'])->ID;

            // Unit proce WITHOUT IGV
            $precioUnit = (string) $line->children($this->ns['cac'])->Price
                            ->children($this->ns['cbc'])->PriceAmount;
            
            //  Unit price WITH IGV and whether this line is a bonus (bonification)
            // PriveTypeCode 01 = normal sale, 02 = free/bonus item
            $precioConIgv    = '0.00';
            $esBonif         = false;
            foreach ($line->children($this->ns['cac'])->PricingReference
                        ->children($this->ns['cac'])->AlternativeConditionPrice as $acp) {
                $precioConIgv   = (string) $acp->children($this->ns['cbc'])->PriceAmount;
                $esBonif        = ((string) $acp->children($this->ns['cbc'])->PriceTypeCode === '02');
                break;
            }

            $igvLinea = '0.00';
            foreach ($line->children($this->ns['cac'])->TaxTotal as $taxTotal) {
                $igvLinea   = (string) $taxTotal->children($this->ns['cbc'])->TaxAmount;
                break;
            }

            $lines[] = [
                'nro'               => $nro,
                'codigo'            => trim($codigo), 
                'descripcion'       => trim($descripcion),
                'cantidad'          => $cantidad,
                'unidad'            => $unidad,
                'precio_unit'        => $precioUnit,
                'precio_con_igv'    => $precioConIgv,
                'val_venta'         => $valVenta,
                'igv_linea'         => $igvLinea, 
                'es_bonif'          => $esBonif,
            ];
        }

        return $lines;
    }
}