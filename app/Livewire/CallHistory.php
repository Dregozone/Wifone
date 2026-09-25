<?php

namespace App\Livewire;

use App\Models\Call;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The signed-in user's own call log. Only calls they made or received are ever queried.
 */
#[Title('Call history')]
class CallHistory extends Component
{
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, Call>
     */
    #[Computed]
    public function calls(): LengthAwarePaginator
    {
        Call::expireStale();

        return Call::query()
            ->involving(Auth::user())
            ->with(['caller:id,name', 'receiver:id,name'])
            ->latest('id')
            ->paginate(20);
    }

    /**
     * Refresh the list when a call finishes in this tab (dispatched from resources/js/calls.js).
     */
    #[On('call-finished')]
    public function refreshCalls(): void
    {
        unset($this->calls);
    }

    public function render(): View
    {
        return view('livewire.call-history');
    }
}
