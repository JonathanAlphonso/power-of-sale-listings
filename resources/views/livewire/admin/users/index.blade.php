<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    private const PER_PAGE_OPTIONS = [10, 25, 50];

    protected string $paginationTheme = 'tailwind';

    #[Url(as: 'search', except: '')]
    public string $search = '';

    #[Url(as: 'per', except: '10')]
    public string $perPage = '10';

    public ?int $selectedUserId = null;

    /** @var array{name: string, email: string} */
    public array $form = [
        'name' => '',
        'email' => '',
    ];

    public bool $confirmingDeletion = false;

    public function mount(): void
    {
        $this->perPage = (string) $this->resolvePerPage((int) $this->perPage);
        $this->primeSelection();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = (string) $this->resolvePerPage((int) $this->perPage);
        $this->resetPage();
    }

    public function selectUser(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->populateForm();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->perPage = (string) self::PER_PAGE_OPTIONS[0];
        $this->resetPage();
        $this->selectedUserId = null;
        $this->resetForm();
    }

    public function saveUser(): void
    {
        $selectedUser = $this->selectedUser;

        if ($selectedUser === null) {
            return;
        }

        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($selectedUser->id),
            ],
        ]);

        $selectedUser->forceFill([
            'name' => $validated['form']['name'],
            'email' => $validated['form']['email'],
        ]);

        if ($selectedUser->isDirty('email')) {
            $selectedUser->email_verified_at = null;
        }

        $selectedUser->save();

        $this->dispatch('user-saved', name: $selectedUser->name);
    }

    public function confirmDeleteUser(): void
    {
        $selectedUser = $this->selectedUser;

        if ($selectedUser === null) {
            return;
        }

        if ($selectedUser->is(auth()->user())) {
            $this->addError('form.email', __('You cannot delete your own account from this workspace.'));

            return;
        }

        $this->confirmingDeletion = true;
    }

    public function deleteUser(): void
    {
        $selectedUser = $this->selectedUser;

        if ($selectedUser === null) {
            $this->confirmingDeletion = false;

            return;
        }

        if ($selectedUser->is(auth()->user())) {
            $this->confirmingDeletion = false;

            return;
        }

        $selectedUser->delete();

        $this->confirmingDeletion = false;
        $this->selectedUserId = null;
        $this->resetForm();
        $this->resetPage();

        $this->dispatch('user-deleted');
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage((int) $this->perPage);

        $paginator = User::query()
            ->when($this->search !== '', function (Builder $builder): void {
                $builder->where(function (Builder $query): void {
                    $query
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $collection = $paginator->getCollection();

        if ($collection->isEmpty()) {
            $this->selectedUserId = null;
            $this->resetForm();

            return $paginator;
        }

        if ($this->selectedUserId === null || $collection->doesntContain('id', $this->selectedUserId)) {
            $this->selectedUserId = (int) $collection->first()->id;
            $this->populateForm();
        }

        return $paginator;
    }

    #[Computed]
    public function perPageOptions(): array
    {
        return self::PER_PAGE_OPTIONS;
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        if ($this->selectedUserId === null) {
            return null;
        }

        return User::query()->find($this->selectedUserId);
    }

    private function primeSelection(): void
    {
        $firstUserId = User::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('id');

        if ($firstUserId !== null) {
            $this->selectedUserId = (int) $firstUserId;
            $this->populateForm();
        }
    }

    private function populateForm(): void
    {
        $user = $this->selectedUser;

        if ($user === null) {
            $this->resetForm();

            return;
        }

        $this->form = [
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    private function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'email' => '',
        ];
    }

    private function resolvePerPage(int $value): int
    {
        foreach (self::PER_PAGE_OPTIONS as $option) {
            if ($option === $value) {
                return $option;
            }
        }

        return self::PER_PAGE_OPTIONS[0];
    }
}; ?>

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator<\App\Models\User> $users */
    $users = $this->users;
    /** @var \App\Models\User|null $selectedUser */
    $selectedUser = $this->selectedUser;
@endphp

<section class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-2 pb-6">
        <flux:heading size="xl">{{ __('Users') }}</flux:heading>

        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Browse, inspect, and update user accounts for your organization.') }}
        </flux:text>
    </div>

    <div class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input
            wire:model.live.debounce.400ms="search"
            :label="__('Search')"
            icon="magnifying-glass"
            :placeholder="__('Name or email')"
        />

        <flux:select
            wire:model.live="perPage"
            :label="__('Results per page')"
        >
            @foreach ($this->perPageOptions as $option)
                <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button
            variant="subtle"
            icon="arrow-path"
            class="sm:col-span-2 lg:col-span-1 sm:self-end"
            wire:click="resetFilters"
        >
            {{ __('Reset filters') }}
        </flux:button>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-4">
            <flux:table>
                <flux:table.header>
                    <span>{{ __('Name') }}</span>
                    <span>{{ __('Email') }}</span>
                    <span class="text-right">{{ __('Joined') }}</span>
                </flux:table.header>

                <flux:table.rows>
                    @forelse ($users as $user)
                        <flux:table.row
                            wire:key="user-row-{{ $user->id }}"
                            wire:click="selectUser({{ $user->id }})"
                            :selected="$selectedUser && $selectedUser->id === $user->id"
                            :interactive="true"
                        >
                            <flux:table.cell>
                                <span class="font-semibold text-zinc-900 dark:text-white">{{ $user->name }}</span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ $user->email }}</span>
                            </flux:table.cell>

                            <flux:table.cell alignment="end">
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ optional($user->created_at)?->timezone(config('app.timezone'))->format('M j, Y') ?? __('—') }}
                                </span>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ optional($user->created_at)?->diffForHumans() ?? __('No timestamp') }}
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.empty>
                            {{ __('No users match the current filters.') }}
                        </flux:table.empty>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <div class="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-600 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
                <span>
                    {{ trans_choice(':count user|:count users', $users->total(), ['count' => number_format($users->total())]) }}
                </span>

                <div>
                    {{ $users->links() }}
                </div>
            </div>
        </div>

        <div class="flex h-full flex-col gap-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
            @if ($selectedUser)
                <div class="space-y-1">
                    <flux:heading size="lg">{{ $selectedUser->name }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Member since :date', ['date' => optional($selectedUser->created_at)?->timezone(config('app.timezone'))->format('F j, Y') ?? __('Unknown')]) }}
                    </flux:text>
                </div>

                <form wire:submit="saveUser" class="mt-4 space-y-4">
                    <flux:input
                        wire:model="form.name"
                        :label="__('Name')"
                        type="text"
                        required
                        autocomplete="name"
                    />
                    @error('form.name')
                        <flux:text color="red" class="text-xs">{{ $message }}</flux:text>
                    @enderror

                    <flux:input
                        wire:model="form.email"
                        :label="__('Email')"
                        type="email"
                        required
                        autocomplete="email"
                    />
                    @error('form.email')
                        <flux:text color="red" class="text-xs">{{ $message }}</flux:text>
                    @enderror

                    <div class="flex flex-wrap items-center gap-3">
                        <flux:button type="submit" variant="primary">
                            {{ __('Save changes') }}
                        </flux:button>

                        <flux:button type="button" variant="danger" icon="trash" wire:click="confirmDeleteUser">
                            {{ __('Delete user') }}
                        </flux:button>

                        <x-action-message on="user-saved" class="text-sm text-emerald-600 dark:text-emerald-400">
                            {{ __('User updated.') }}
                        </x-action-message>

                        <x-action-message on="user-deleted" class="text-sm text-emerald-600 dark:text-emerald-400">
                            {{ __('User removed.') }}
                        </x-action-message>
                    </div>
                </form>
            @else
                <div class="flex h-full flex-col items-center justify-center gap-3 text-center">
                    <flux:icon.users class="h-10 w-10 text-zinc-400 dark:text-zinc-600" />
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Select a user from the table to view their details and manage the account.') }}
                    </flux:text>
                </div>
            @endif
        </div>
    </div>

    <flux:modal
        wire:model="confirmingDeletion"
        class="max-w-md"
    >
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Delete user account') }}</flux:heading>
            <flux:text>
                {{ __('This will permanently delete the selected user and remove their access to the platform. This action cannot be undone.') }}
            </flux:text>

            <div class="flex items-center justify-end gap-2">
                <flux:button variant="outline" wire:click="$set('confirmingDeletion', false)">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button variant="danger" wire:click="deleteUser">
                    {{ __('Delete user') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
