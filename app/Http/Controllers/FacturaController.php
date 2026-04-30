<?php

namespace App\Http\Controllers;

use App\Services\FacturaXmlParser;
use App\Services\TxtGenerator;
use Illuminate\Http\Request;

class FacturaController extends Controller
{
    public function index()
    {
        return view('facturas.upload');
    }

    public function procesar(Request $request)
    {
        $request->validate([
            'xml_file'   => 'required',
            'xml_file.*' => 'file|mimes:xml,zip|max:51200',
        ]);

        $files = $request->file('xml_file');
        if (!is_array($files)) {
            $files = [$files];
        }

        $parser    = new FacturaXmlParser();
        $generator = new TxtGenerator();
        $results   = [];

        foreach ($files as $file) {
            if (strtolower($file->getClientOriginalExtension()) === 'zip') {
                $zip = new \ZipArchive();
                if ($zip->open($file->getRealPath()) === true) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $name = $zip->getNameIndex($i);
                        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xml') {
                            continue;
                        }
                        $results[] = $this->buildResult($parser, $generator, $zip->getFromIndex($i));
                    }
                    $zip->close();
                }
            } else {
                $results[] = $this->buildResult($parser, $generator, file_get_contents($file->getRealPath()));
            }
        }

        if (count($results) === 1) {
            return response($results[0]['content'], 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="' . $results[0]['filename'] . '"');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'facturas_') . '.zip';
        $zip     = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        foreach ($results as $result) {
            $zip->addFromString($result['filename'], $result['content']);
        }
        $zip->close();

        return response()->download($zipPath, 'facturas_export.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    private function buildResult(FacturaXmlParser $parser, TxtGenerator $generator, string $xmlContent): array
    {
        $data = $parser->parse($xmlContent);
        $h    = $data['header'];
        return [
            'filename' => $h['tipo_doc'] . '_' . $h['serie'] . '-' . $h['numero'] . '.txt',
            'content'  => $generator->generate($data),
        ];
    }
}
