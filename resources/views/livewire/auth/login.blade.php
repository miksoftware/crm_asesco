<div class="w-full max-w-md" wire:loading.class="pointer-events-none">
    <div class="glass rounded-2xl shadow-2xl p-8">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl gradient-bg mb-4">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">ASESCO <span class="text-primary-500">BPO</span></h1>
            <p class="text-gray-500 text-sm mt-1">Asesorías Especializadas y Cobranzas</p>
        </div>

        <!-- Form -->
        <form wire:submit.prevent="login" class="space-y-5">
            @if (session('status'))
                <div class="p-3 bg-green-100 border border-green-300 text-green-700 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                        </svg>
                    </div>
                    <input wire:model="email" type="email" id="email" 
                        class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all duration-200 @error('email') border-red-500 @enderror"
                        placeholder="tu@email.com"
                        wire:loading.attr="disabled">
                </div>
                @error('email')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <input wire:model="password" type="password" id="password"
                        class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all duration-200 @error('password') border-red-500 @enderror"
                        placeholder="••••••••"
                        wire:loading.attr="disabled">
                </div>
                @error('password')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input wire:model="remember" type="checkbox" class="w-4 h-4 text-primary-500 border-gray-300 rounded focus:ring-primary-500">
                    <span class="ml-2 text-sm text-gray-600">Recordarme</span>
                </label>
            </div>

            <button type="submit" 
                class="w-full py-3 px-4 gradient-bg text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-75 disabled:cursor-not-allowed disabled:transform-none disabled:hover:shadow-lg flex items-center justify-center gap-2"
                wire:loading.attr="disabled">
                <svg wire:loading class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove>Iniciar Sesión</span>
                <span wire:loading>Ingresando...</span>
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-gray-500">
            © {{ date('Y') }} ASESCO BPO. Todos los derechos reservados.
        </p>
    </div>
</div>
