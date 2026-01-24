<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Illuminate\Support\Facades\Auth;

#[Layout('layouts.guest')]
class Login extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    #[Rule('required|min:6')]
    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate();

        // Check if user exists and is active
        $user = \App\Models\User::where('email', $this->email)->first();
        
        if ($user && !$user->is_active) {
            $this->addError('email', 'Tu cuenta está desactivada. Contacta al administrador.');
            return;
        }

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            session()->regenerate();
            
            // Update last login timestamp
            Auth::user()->update(['last_login_at' => now()]);
            
            // Redirección tradicional con JavaScript para evitar problemas de Livewire
            $this->js('window.location.href = "' . route('dashboard') . '"');
            return;
        }

        $this->addError('email', 'Las credenciales no coinciden con nuestros registros.');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
