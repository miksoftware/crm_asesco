<?php

namespace App\Livewire\Channels;

use App\Models\Channel;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.app')]
#[Title('Editar Canal')]
class Edit extends Component
{
    public Channel $channel;

    public string $name = '';
    public string $instance_name = '';
    public bool $is_active = true;
    public array $selectedUsers = [];

    public function mount(Channel $channel): void
    {
        $this->channel = $channel;
        $this->name = $channel->name;
        $this->instance_name = $channel->instance_name;
        $this->is_active = $channel->is_active;
        $this->selectedUsers = $channel->users->pluck('id')->toArray();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $this->channel->update([
            'name' => $this->name,
            'is_active' => $this->is_active,
        ]);

        $this->channel->users()->sync($this->selectedUsers);

        session()->flash('toast', ['type' => 'success', 'message' => 'Canal actualizado correctamente']);
        $this->redirectRoute('channels.index');
    }

    public function render()
    {
        return view('livewire.channels.edit', [
            'users' => User::orderBy('name')->get(),
        ]);
    }
}
