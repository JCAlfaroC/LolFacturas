<!DOCTYPE html>
<html lang="es">
<head>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LolFar - Importar Factura XML</title>
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center font-sans">

    <div class="bg-white rounded-xl shadow-md p-8 w-full max-w-md">
        <h1 class="text-xl font-semibold text-gray-800 mb-1">Importar Factura Electronica (XML SUNAT)</h1>
        <p class="text-xs text-gray-400 mb-6">Acepta archivos .xml individuales o un .zip con varios XML</p>

    @if ($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg px-4 py-3 mb-4 text-sm">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="/procesar" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <label for="xml_file" class="block text-sm font-medium text-gray-700">Selecciona los archivos:</label>
        <input type="file" id="xml_file" name="xml_file[]" accept=".xml,.zip" multiple
            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
            onchange="updateFileCount(this)">
        <p id="file-count" class="text-xs text-gray-400"></p>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition-colors">
            Generar TXT para LolFar
        </button>
    </form>
</div>

<script>
    function updateFileCount(input) {
        const files = Array.from(input.files);
        const label = document.getElementById('file-count');

        if (files.length === 0) {
            label.textContent = '';
            return;
        }

        const zips = files.filter(f => f.name.endsWith('.zip')).length;
        const xmls = files.filter(f => f.name.endsWith('.xml')).length;
        const parts = [];

        if (xmls > 0) parts.push(xmls + (xmls === 1 ? ' XML' : ' XMLs'));
        if (zips > 0) parts.push(zips + (zips === 1 ? ' ZIP' : ' ZIPs'));

        const suffix = (xmls + zips) > 1 || zips > 0 ? ' — se descargará un archivo .zip' : '';
        label.textContent = parts.join(' y ') + ' seleccionado' + (files.length > 1 ? 's' : '') + suffix;
    }
</script>

</body>
</html>
