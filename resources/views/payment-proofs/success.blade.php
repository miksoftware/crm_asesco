<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soporte Enviado - ASESCO BPO</title>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-8 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 mb-4">
                <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>

            @if($alreadyUploaded)
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Ya enviaste tu soporte</h1>
                <p class="text-gray-600 text-sm mb-6">
                    Este soporte ya fue enviado el {{ $proof->uploaded_at->format('d/m/Y H:i') }}.
                </p>
            @else
                <h1 class="text-2xl font-bold text-gray-900 mb-2">¡Soporte enviado!</h1>
                <p class="text-gray-600 text-sm mb-6">
                    Tu comprobante de pago ha sido recibido correctamente. Tu asesor lo revisará pronto.
                </p>
            @endif

            <div class="bg-gray-50 rounded-lg p-4 text-left text-sm text-gray-700 space-y-2 mb-6">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="text-gray-500">Archivo:</span>
                    <span class="font-medium truncate">{{ $proof->file_name }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-gray-500">Enviado:</span>
                    <span class="font-medium">{{ $proof->uploaded_at->format('d/m/Y H:i') }}</span>
                </div>
            </div>

            <p class="text-xs text-gray-400">
                Ya puedes cerrar esta página
            </p>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">© {{ date('Y') }} ASESCO BPO</p>
    </div>
</body>
</html>
