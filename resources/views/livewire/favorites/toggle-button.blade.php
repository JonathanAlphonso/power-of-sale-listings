<?php

use App\Models\Listing;
use App\Models\UserFavorite;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public int $listingId;

    public function mount(int $listingId): void
    {
        $this->listingId = $listingId;
    }

    public function toggle(): void
    {
        $user = auth()->user();

        if ($user === null) {
            $this->redirect(route('login'));
            return;
        }

        $existing = $user->favorites()->where('listing_id', $this->listingId)->first();

        if ($existing !== null) {
            $existing->delete();
        } else {
            $user->favorites()->create([
                'listing_id' => $this->listingId,
            ]);
        }

        unset($this->isFavorited);
    }

    #[Computed]
    public function isFavorited(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasFavorited($this->listingId);
    }
}; ?>

<button
    wire:click="toggle"
    class="flex h-8 w-8 items-center justify-center rounded-full {{ $this->isFavorited ? 'bg-red-500 text-white' : 'bg-white/90 text-slate-600 hover:bg-red-500 hover:text-white' }} shadow-lg transition dark:bg-zinc-800/90 dark:text-zinc-300 {{ $this->isFavorited ? '' : 'dark:hover:bg-red-500 dark:hover:text-white' }}"
    title="{{ $this->isFavorited ? __('Remove from favorites') : __('Add to favorites') }}"
>
    <flux:icon name="heart" class="h-5 w-5" :variant="$this->isFavorited ? 'solid' : 'outline'" />
</button>
