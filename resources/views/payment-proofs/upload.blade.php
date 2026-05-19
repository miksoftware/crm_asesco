<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Subir Soporte de Pago - ASESCO BPO</title>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #f97316, #ea580c, #db2777, #ec4899); }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <!-- Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full gradient-bg mb-4 shadow-lg">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Adjuntar Soporte de Pago</h1>
            <p class="text-gray-600 mt-2 text-sm">ASESCO BPO - Asesorías Especializadas y Cobranzas</p>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
            @if($proof->client_name)
            <div class="mb-5 p-3 bg-orange-50 rounded-lg border border-orange-100">
                <p class="text-sm text-gray-700">Hola <span class="font-semibold">{{ $proof->client_name }}</span>, por favor sube tu soporte de pago.</p>
            </div>
            @else
            <div class="mb-5 p-3 bg-orange-50 rounded-lg border border-orange-100">
                <p class="text-sm text-gray-700">Por favor sube tu soporte de pago.</p>
            </div>
            @endif

            @if($errors->any())
                <div class="mb-4 p-3 bg-red-50 rounded-lg border border-red-200">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form action="{{ url('/pago/' . $proof->token) }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                @csrf

                <!-- File Upload Area -->
                <div class="mb-4">
                    <label for="file" class="block text-sm font-medium text-gray-700 mb-2">
                        Selecciona tu comprobante <span class="text-red-500">*</span>
                    </label>
                    
                    <label for="file" 
                           class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-orange-400 hover:bg-orange-50 transition-colors"
                           id="dropArea">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6 text-center px-4" id="placeholder">
                            <svg class="w-12 h-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="mb-1 text-sm text-gray-600">
                                <span class="font-semibold text-orange-600">Toca para seleccionar</span>
                            </p>
                            <p class="text-xs text-gray-500">JPG, PNG o PDF (máx. 10MB)</p>
                        </div>
                        <div class="hidden flex-col items-center justify-center pt-5 pb-6 px-4 text-center" id="preview">
                            <div id="imagePreview" class="hidden mb-2">
                                <img src="" alt="Vista previa" class="max-h-32 rounded-lg shadow">
                            </div>
                            <div id="fileIcon" class="hidden mb-2">
                                <svg class="w-12 h-12 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-900" id="fileName"></p>
                            <p class="text-xs text-gray-500 mt-1" id="fileSize"></p>
                            <button type="button" onclick="resetFile()" class="mt-2 text-xs text-red-600 hover:text-red-700 underline">Cambiar archivo</button>
                        </div>
                        <input type="file" 
                               id="file" 
                               name="file" 
                               accept="image/jpeg,image/png,image/jpg,application/pdf"
                               required
                               class="hidden">
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" 
                        id="submitBtn"
                        class="w-full py-3 px-4 gradient-bg text-white font-semibold rounded-xl hover:opacity-90 transition-opacity shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span id="submitText">Enviar soporte</span>
                </button>
            </form>

            <!-- Info -->
            <div class="mt-5 pt-5 border-t border-gray-100">
                <div class="flex items-start gap-2 text-xs text-gray-500">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Tu archivo será enviado de forma segura a tu asesor. Este enlace expira el {{ $proof->expires_at->format('d/m/Y H:i') }}.</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-xs text-gray-400 mt-6">© {{ date('Y') }} ASESCO BPO</p>
    </div>

    <script>
        const fileInput = document.getElementById('file');
        const placeholder = document.getElementById('placeholder');
        const preview = document.getElementById('preview');
        const imagePreview = document.getElementById('imagePreview');
        const fileIcon = document.getElementById('fileIcon');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const form = document.getElementById('uploadForm');
        const submitBtn = document.getElementById('submitBtn');
        const submitText = document.getElementById('submitText');

        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            placeholder.classList.add('hidden');
            preview.classList.remove('hidden');
            preview.classList.add('flex');

            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);

            if (file.type.startsWith('image/')) {
                imagePreview.classList.remove('hidden');
                fileIcon.classList.add('hidden');
                imagePreview.querySelector('img').src = URL.createObjectURL(file);
            } else {
                imagePreview.classList.add('hidden');
                fileIcon.classList.remove('hidden');
            }
        });

        function resetFile() {
            fileInput.value = '';
            placeholder.classList.remove('hidden');
            preview.classList.add('hidden');
            preview.classList.remove('flex');
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        form.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitText.textContent = 'Enviando...';
        });
    </script>
</body>
</html>
