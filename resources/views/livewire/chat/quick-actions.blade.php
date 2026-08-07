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

        <!-- Mark as Unread Button -->
        <button wire:click="markAsUnread"
                wire:loading.attr="disabled"
                wire:target="markAsUnread"
                class="flex flex-col items-center justify-center p-3 bg-purple-50 hover:bg-purple-100 rounded-lg border border-purple-200 transition-colors group disabled:opacity-50 col-span-2">
            <svg class="w-5 h-5 text-purple-600 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <span class="text-xs font-medium text-purple-700 text-center">
                <span wire:loading.remove wire:target="markAsUnread">Marcar como no leído</span>
                <span wire:loading wire:target="markAsUnread">Procesando...</span>
            </span>
        </button>
    </div>

    <!-- Promise Modal -->
    @teleport('body')
        <div x-data="{ show: @entangle('showPromiseModal') }" 
             x-show="show" 
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto" 
             aria-labelledby="modal-title" 
             role="dialog" 
             aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-black/30 backdrop-blur-sm transition-opacity" 
                     x-show="show"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     wire:click="closePromiseModal"></div>

                <div class="relative bg-white rounded-2xl shadow-xl transform transition-all sm:max-w-md w-full p-6"
                     x-show="show"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                    
                    <form wire:submit="registerPromise">
                        <div class="text-center mb-4">
                            <div class="w-16 h-16 rounded-full bg-yellow-100 flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Registrar Promesa de Pago</h3>
                            <p class="text-sm text-gray-500">{{ $contact->display_name }}</p>
                        </div>

                        <div class="space-y-4 text-left">
                            <!-- Promise Date -->
                            <div>
                                <label for="promiseDate" class="block text-sm font-medium text-gray-700 mb-1">
                                    Fecha y hora prometida <span class="text-red-500">*</span>
                                </label>
                                <input type="datetime-local" 
                                       id="promiseDate"
                                       wire:model="promiseDate"
                                       min="{{ date('Y-m-d\TH:i') }}"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 text-sm">
                                @error('promiseDate')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Promise Amount -->
                            <div>
                                <label for="promiseAmount" class="block text-sm font-medium text-gray-700 mb-1">
                                    Monto prometido <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <span class="text-gray-500 text-sm">$</span>
                                    </div>
                                    <input type="number" 
                                           id="promiseAmount"
                                           wire:model="promiseAmount"
                                           step="0.01"
                                           min="0.01"
                                           placeholder="0.00"
                                           class="w-full pl-8 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 text-sm">
                                </div>
                                @error('promiseAmount')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Notes -->
                            <div>
                                <label for="promiseNotes" class="block text-sm font-medium text-gray-700 mb-1">
                                    Mensaje a enviar (se enviará automáticamente)
                                </label>
                                <textarea id="promiseNotes"
                                          wire:model="promiseNotes"
                                          rows="3"
                                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 text-sm resize-none"
                                          placeholder="Escribe el mensaje exacto que se enviará al cliente..."></textarea>
                                <p class="mt-1 text-xs text-gray-500">
                                    El mensaje se enviará al cliente exactamente como lo escribas aquí en la fecha y hora indicadas. Quedará registrado con tu nombre.
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-3 mt-6">
                            <button type="button"
                                    wire:click="closePromiseModal"
                                    class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors font-medium">
                                Cancelar
                            </button>
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="registerPromise"
                                    class="flex-1 px-4 py-2.5 bg-yellow-500 text-white rounded-xl hover:bg-yellow-600 transition-colors font-medium disabled:opacity-50 flex items-center justify-center gap-2">
                                <svg wire:loading wire:target="registerPromise" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="registerPromise">Registrar</span>
                                <span wire:loading wire:target="registerPromise">Guardando...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endteleport

    <!-- Follow-up Modal -->
    @teleport('body')
        <div x-data="{ show: @entangle('showFollowUpModal') }" 
             x-show="show" 
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto" 
             aria-labelledby="modal-title" 
             role="dialog" 
             aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-black/30 backdrop-blur-sm transition-opacity" 
                     x-show="show"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     wire:click="closeFollowUpModal"></div>

                <div class="relative bg-white rounded-2xl shadow-xl transform transition-all sm:max-w-md w-full p-6"
                     x-show="show"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                    
                    <form wire:submit="scheduleFollowUp">
                        <div class="text-center mb-4">
                            <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Programar Seguimiento</h3>
                            <p class="text-sm text-gray-500">{{ $contact->display_name }}</p>
                        </div>

                        <div class="space-y-4 text-left">
                            <!-- Follow-up Date -->
                            <div>
                                <label for="followUpDate" class="block text-sm font-medium text-gray-700 mb-1">
                                    Fecha y hora <span class="text-red-500">*</span>
                                </label>
                                <input type="datetime-local" 
                                       id="followUpDate"
                                       wire:model="followUpDate"
                                       min="{{ date('Y-m-d\TH:i') }}"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                @error('followUpDate')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Note -->
                            <div>
                                <label for="followUpNote" class="block text-sm font-medium text-gray-700 mb-1">
                                    Nota (opcional)
                                </label>
                                <textarea id="followUpNote"
                                          wire:model="followUpNote"
                                          rows="3"
                                          class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm resize-none"
                                          placeholder="Agregar nota para el seguimiento..."></textarea>
                            </div>
                        </div>

                        <div class="flex gap-3 mt-6">
                            <button type="button"
                                    wire:click="closeFollowUpModal"
                                    class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors font-medium">
                                Cancelar
                            </button>
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="scheduleFollowUp"
                                    class="flex-1 px-4 py-2.5 bg-blue-500 text-white rounded-xl hover:bg-blue-600 transition-colors font-medium disabled:opacity-50 flex items-center justify-center gap-2">
                                <svg wire:loading wire:target="scheduleFollowUp" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="scheduleFollowUp">Programar</span>
                                <span wire:loading wire:target="scheduleFollowUp">Guardando...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endteleport
</div>
