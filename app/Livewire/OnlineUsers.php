<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Lists the other users. Online status and call buttons are driven client-side
 * by the Alpine `presence` and `call` stores (see resources/js/calls.js).
 */
class OnlineUsers extends Component
{
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
