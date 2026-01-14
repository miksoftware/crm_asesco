<div class="p-4 border-b border-gray-200">
    <h4 class="text-sm font-semibold text-gray-700 mb-3">Acciones Rápidas</h4>
    
    <!-- Quick Action Buttons -->
    <div class="grid grid-cols-2 gap-2">
        <!-- Register Promise Button -->
        <button wire:click="openPromiseModal"
                class="flex flex-col items-center justify-center p-3 bg-yellow-50 hover:bg-yellow-100 rounded-lg border border-yellow-200 transition-colors group">
            <svg class="w-5 h-5 text-yellow-600 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            <span class="text-xs font-medium text-yellow-700 text-center">Registrar promesa</span>
        </button>

        <!-- Mark as Paid Button -->
        <button wire:click="markAsPaid"
                wire:loading.attr="disabled"
                wire:target="markAsPaid"
                @if(!$canManageLabels) disabled @endif
                class="flex flex-col items-center justify-center p-3 bg-green-50 hover:bg-green-100 rounded-lg border border-green-200 transition-colors group disabled:opacity-50 disabled:cursor-not-allowed">
            <svg class="w-5 h-5 text-green-600 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-xs font-medium text-green-700 text-center">
                <span wire:loading.remove wire:target="markAsPaid">Marcar pagado</span>
                <span wire:loading wire:target="markAsPaid">Procesando...</span>
            </span>
        </button>

        <!-- Schedule Follow-up Button -->
        <button wire:click="openFollowUpModal"
                class="flex flex-col items-center justify-center p-3 bg-blue-50 hover:bg-blue-100 rounded-lg border border-blue-200 transition-colors group">
            <svg class="w-5 h-5 text-blue-600 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="text-xs font-medium text-blue-700 text-center">Programar seguimiento</span>
        </button>

        <!-- Send Reminder Button -->
        <button wire:click="sendReminder"
                wire:loading.attr="disabled"
                wire:target="sendReminder"
                @if(!$canSend) disabled @endif
                class="flex flex-col items-center justify-center p-3 bg-orange-50 hover:bg-orange-100 rounded-lg border border-orange-200 transition-colors group disabled:opacity-50 disabled:cursor-not-allowed">
            <svg class="w-5 h-5 text-orange-600 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span class="text-xs font-medium text-orange-700 text-center">
                <span wire:loading.remove wire:target="sendReminder">Enviar recordatorio</span>
                <span wire:loading wire:target="sendReminder">Enviando...</span>
            </span>
        </button>
    </div>

    <!-- Promise Modal -->
    @if($showPromiseModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closePromiseModal"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="registerPromise">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                        Registrar Promesa de Pago
                                    </h3>
                                    <div class="mt-4 space-y-4">
                                        <!-- Promise Date -->
                                        <div>
                                            <label for="promiseDate" class="block text-sm font-medium text-gray-700">
                                                Fecha prometida <span class="text-red-500">*</span>
                                            </label>
                                            <input type="date" 
                                                   id="promiseDate"
                                                   wire:model="promiseDate"
                                                   min="{{ date('Y-m-d') }}"
                                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                                            @error('promiseDate')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <!-- Promise Amount -->
                                        <div>
                                            <label for="promiseAmount" class="block text-sm font-medium text-gray-700">
                                                Monto prometido <span class="text-red-500">*</span>
                                            </label>
                                            <div class="mt-1 relative rounded-md shadow-sm">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <span class="text-gray-500 sm:text-sm">$</span>
                                                </div>
                                                <input type="number" 
                                                       id="promiseAmount"
                                                       wire:model="promiseAmount"
                                                       step="0.01"
                                                       min="0.01"
                                                       placeholder="0.00"
                                                       class="block w-full pl-7 pr-3 border border-gray-300 rounded-md shadow-sm py-2 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                                            </div>
                                            @error('promiseAmount')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <!-- Notes -->
                                        <div>
                                            <label for="promiseNotes" class="block text-sm font-medium text-gray-700">
                                                Notas (opcional)
                                            </label>
                                            <textarea id="promiseNotes"
                                                      wire:model="promiseNotes"
                                                      rows="2"
                                                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm"
                                                      placeholder="Agregar notas adicionales..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="registerPromise"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-600 text-base font-medium text-white hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                                <span wire:loading.remove wire:target="registerPromise">Registrar</span>
                                <span wire:loading wire:target="registerPromise">Guardando...</span>
                            </button>
                            <button type="button"
                                    wire:click="closePromiseModal"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Follow-up Modal -->
    @if($showFollowUpModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeFollowUpModal"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="scheduleFollowUp">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                        Programar Seguimiento
                                    </h3>
                                    <div class="mt-4 space-y-4">
                                        <!-- Follow-up Date -->
                                        <div>
                                            <label for="followUpDate" class="block text-sm font-medium text-gray-700">
                                                Fecha y hora <span class="text-red-500">*</span>
                                            </label>
                                            <input type="datetime-local" 
                                                   id="followUpDate"
                                                   wire:model="followUpDate"
                                                   min="{{ date('Y-m-d\TH:i') }}"
                                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                                            @error('followUpDate')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <!-- Note -->
                                        <div>
                                            <label for="followUpNote" class="block text-sm font-medium text-gray-700">
                                                Nota (opcional)
                                            </label>
                                            <textarea id="followUpNote"
                                                      wire:model="followUpNote"
                                                      rows="3"
                                                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm"
                                                      placeholder="Agregar nota para el seguimiento..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="scheduleFollowUp"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                                <span wire:loading.remove wire:target="scheduleFollowUp">Programar</span>
                                <span wire:loading wire:target="scheduleFollowUp">Guardando...</span>
                            </button>
                            <button type="button"
                                    wire:click="closeFollowUpModal"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
