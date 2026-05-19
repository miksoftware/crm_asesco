<div class="p-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Soportes de Pago</h1>
            <p class="text-gray-600 mt-1">Comprobantes de pago enviados por los clientes</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <div class="relative flex items-center">
                    <div class="absolute left-3 flex items-center justify-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text" 
                           wire:model.live.debounce.300ms="search"
                           placeholder="Buscar por teléfono o nombre..."
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
            </div>

            <div class="w-full sm:w-44">
                <select wire:model.live="statusFilter"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="">Todos los estados</option>
                    <option value="pending">Pendiente</option>
                    <option value="uploaded">Subido</option>
                    <option value="downloaded">Descargado</option>
                    <option value="expired">Expirado</option>
                </select>
            </div>

            <div class="w-full sm:w-44">
                <input type="date" 
                       wire:model.live="dateFilter"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>

            <div class="w-full sm:w-20">
                <select wire:model.live="perPage"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Cliente</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Teléfono</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Canal</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Agente</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Estado</th>
                        <th wire:click="sortBy('created_at')" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                            <div class="flex items-center gap-1">
                                Enviado
                                @if($sortField === 'created_at')
                                    <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                    </svg>
                                @endif
                            </div>
                        </th>
                        <th wire:click="sortBy('uploaded_at')" class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                            <div class="flex items-center gap-1">
                                Subido
                                @if($sortField === 'uploaded_at')
                                    <svg class="w-4 h-4 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                    </svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Descargado</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($this->proofs as $proof)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="font-medium text-gray-900">{{ $proof->client_name ?: ($proof->contact->display_name ?? 'Sin nombre') }}</span>
                        </td>
                        <td class="px-6 py-4 font-mono text-sm text-gray-700">{{ $proof->phone_number }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $proof->channel->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700">{{ $proof->user->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($proof->status === 'downloaded') bg-green-100 text-green-800
                                @elseif($proof->status === 'uploaded') bg-blue-100 text-blue-800
                                @elseif($proof->status === 'pending') bg-yellow-100 text-yellow-800
                                @else bg-gray-100 text-gray-700
                                @endif">
                                {{ $proof->status_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $proof->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ $proof->uploaded_at ? $proof->uploaded_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            @if($proof->downloaded_at)
                                <div>{{ $proof->downloaded_at->format('d/m/Y H:i') }}</div>
                                <div class="text-xs text-gray-500">por {{ $proof->downloader->name ?? 'N/A' }}</div>
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if(in_array($proof->status, ['uploaded', 'downloaded']) && $canDownload)
                                <button wire:click="openDownloadModal({{ $proof->id }})"
                                        class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                        title="Descargar">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </button>
                                @endif

                                @if(auth()->user()->hasRole('admin') || $proof->user_id === auth()->id())
                                <button wire:click="deleteProof({{ $proof->id }})"
                                        wire:confirm="¿Eliminar este soporte de pago?"
                                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Eliminar">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay soportes de pago</h3>
                            <p class="mt-1 text-sm text-gray-500">Los soportes de pago enviados por los clientes aparecerán aquí.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->proofs->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $this->proofs->links() }}
        </div>
        @endif
    </div>

    {{-- Modal de Descarga --}}
    @if($showDownloadModal)
    @teleport('body')
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-trap.noscroll="true">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" wire:click="closeDownloadModal"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="p-6">
                <div class="flex items-start gap-4 mb-5">
                    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Descargar soporte</h3>
                        <p class="text-sm text-gray-500 mt-1">Asigna un nombre al archivo. Cuando descargues, el navegador te preguntará dónde guardarlo.</p>
                    </div>
                </div>

                <form wire:submit.prevent="confirmDownload">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombre del archivo *</label>
                        <div class="flex items-center">
                            <input type="text"
                                   wire:model="downloadFileName"
                                   placeholder="Ej: 1000259687"
                                   autofocus
                                   class="flex-1 px-4 py-2.5 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <span class="px-3 py-2.5 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg text-sm text-gray-700 font-mono">
                                .{{ $downloadExtension }}
                            </span>
                        </div>
                        @error('downloadFileName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-2 text-xs text-gray-500">
                            <svg class="inline w-3.5 h-3.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Tip: organiza tus descargas creando carpetas por fecha (ej: <span class="font-mono">Soportes/{{ now()->format('Y-m-d') }}/</span>)
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-6">
                        <button type="button"
                                wire:click="closeDownloadModal"
                                class="px-4 py-2 text-gray-700 hover:text-gray-900 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Descargar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endteleport
    @endif

    {{-- Script de descarga con "Guardar como" --}}
    <script>
        (function () {
            // Evitar registrar el listener varias veces
            if (window.__paymentProofDownloadListener) return;
            window.__paymentProofDownloadListener = true;

            window.addEventListener('trigger-save-as', async (event) => {
                const { url, fileName, mimeType } = event.detail || {};
                if (!url || !fileName) return;

                try {
                    // Descargar el archivo como blob
                    const response = await fetch(url, { credentials: 'same-origin' });
                    if (!response.ok) throw new Error('Error al descargar el archivo');
                    const blob = await response.blob();

                    // Si el navegador soporta File System Access API (Chrome/Edge), usar "Guardar como"
                    if ('showSaveFilePicker' in window) {
                        try {
                            const ext = fileName.split('.').pop();
                            const handle = await window.showSaveFilePicker({
                                suggestedName: fileName,
                                types: [{
                                    description: 'Archivo',
                                    accept: { [mimeType || 'application/octet-stream']: ['.' + ext] }
                                }]
                            });
                            const writable = await handle.createWritable();
                            await writable.write(blob);
                            await writable.close();

                            window.dispatchEvent(new CustomEvent('toast-payment-proof', {
                                detail: { type: 'success', message: 'Archivo guardado correctamente' }
                            }));
                            return;
                        } catch (err) {
                            // Si el usuario cancela el diálogo, no hacer nada
                            if (err.name === 'AbortError') return;
                            console.warn('File System Access API falló, usando descarga normal:', err);
                        }
                    }

                    // Fallback: descarga normal con nombre
                    const link = document.createElement('a');
                    const blobUrl = URL.createObjectURL(blob);
                    link.href = blobUrl;
                    link.download = fileName;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
                } catch (error) {
                    console.error('Error al descargar:', error);
                    window.dispatchEvent(new CustomEvent('toast-payment-proof', {
                        detail: { type: 'error', message: 'Error al descargar el archivo' }
                    }));
                }
            });

            // Conectar con el sistema de toast existente (SweetAlert2)
            window.addEventListener('toast-payment-proof', (event) => {
                const { type, message } = event.detail;
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: type,
                        title: message,
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                }
            });
        })();
    </script>
</div>
