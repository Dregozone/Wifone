<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class OnlineUsers extends Component
{
    /** @var array<int> */
    public array $onlineUserIds = [];

    /** @var Collection<int, User> */
    public Collection $users;

    public function mount(): void
    {
        $this->users = User::where('id', '!=', Auth::id())
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.online-users');
    }
}
