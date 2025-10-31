<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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

    /** @var array{name: string, email: string, role: string} */
    public array $form = [
        'name' => '',
        'email' => '',
        'role' => UserRole::Subscriber->value,
    ];

    /** @var array{name: string, email: string, role: string} */
    public array $inviteForm = [
        'name' => '',
        'email' => '',
        'role' => UserRole::Subscriber->value,
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
            'form.role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $currentRole = $selectedUser->role;
        $newRole = UserRole::from($validated['form']['role']);

        if ($currentRole === UserRole::Admin && $newRole !== UserRole::Admin) {
            if ($this->isLastActiveAdmin($selectedUser)) {
                $this->addError('form.role', __('At least one active admin is required.'));

                return;
            }
        }

        $selectedUser->forceFill([
            'name' => $validated['form']['name'],
            'email' => $validated['form']['email'],
            'role' => $newRole,
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

        if ($selectedUser->isAdmin() && $this->isLastActiveAdmin($selectedUser)) {
            $this->addError('form.role', __('You must assign another admin before deleting this account.'));

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

        if ($selectedUser->isAdmin() && $this->isLastActiveAdmin($selectedUser)) {
            $this->confirmingDeletion = false;
            $this->addError('form.role', __('You must assign another admin before deleting this account.'));

            return;
        }

        $selectedUser->delete();

        $this->confirmingDeletion = false;
        $this->selectedUserId = null;
        $this->resetForm();
        $this->resetPage();

        $this->dispatch('user-deleted');
    }

    public function inviteUser(): void
    {
        $validated = $this->validate([
            'inviteForm.name' => ['required', 'string', 'max:255'],
            'inviteForm.email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'inviteForm.role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $role = UserRole::from($validated['inviteForm']['role']);

        $user = User::query()->create([
            'name' => $validated['inviteForm']['name'],
            'email' => $validated['inviteForm']['email'],
            'password' => Str::password(16),
            'role' => $role,
        ]);

        $user->forceFill([
            'invited_at' => now(),
            'invited_by_id' => auth()->id(),
            'email_verified_at' => null,
            'suspended_at' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $status = Password::broker()->sendResetLink([
            'email' => $user->email,
        ]);

        $this->selectedUserId = (int) $user->id;
        $this->resetPage();
        $this->populateForm();

        if ($status === Password::RESET_LINK_SENT) {
            $this->resetInviteForm();
            $this->dispatch('user-invited', email: $user->email);
        } else {
            $this->addError('inviteForm.email', __($status));
        }
    }

    public function sendPasswordResetLink(): void
    {
        $selectedUser = $this->selectedUser;

        if ($selectedUser === null) {
            return;
        }

        $status = Password::broker()->sendResetLink([
            'email' => $selectedUser->email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->dispatch('password-reset-link-sent', email: $selectedUser->email);

            return;
        }

        $this->addError('form.email', __($status));
    }

    public function toggleSuspension(): void
    {
        $selectedUser = $this->selectedUser;

        if ($selectedUser === null) {
            return;
        }

        if ($selectedUser->is(auth()->user())) {
            $this->addError('form.email', __('You cannot suspend your own account.'));

            return;
        }

        if (! $selectedUser->isSuspended()) {
            if ($selectedUser->isAdmin() && $this->isLastActiveAdmin($selectedUser)) {
                $this->addError('form.role', __('You must assign another admin before suspending this account.'));

                return;
            }

            $selectedUser->forceFill([
                'suspended_at' => now(),
            ])->save();

            $this->dispatch('user-suspended');
        } else {
            $selectedUser->forceFill([
                'suspended_at' => null,
            ])->save();

            $this->dispatch('user-activated');
        }

        $this->populateForm();
    }

    public function forcePasswordRotation(): void
    {
        $selectedUser = $this->selectedUser;

        if ($selectedUser === null) {
            return;
        }

        $temporaryPassword = Str::password(32);
        $forcedById = auth()->id();

        DB::transaction(function () use ($selectedUser, $temporaryPassword, $forcedById): void {
            $selectedUser->forceFill([
                'password' => $temporaryPassword,
                'password_forced_at' => now(),
                'password_forced_by_id' => $forcedById,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')
                ->where('user_id', $selectedUser->id)
                ->delete();
        });

        $status = Password::broker()->sendResetLink([
            'email' => $selectedUser->email,
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            $this->addError('form.email', __($status));
        }

        $this->dispatch('password-rotation-forced', email: $selectedUser->email);
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

    #[Computed]
    public function roleOptions(): array
    {
        return UserRole::cases();
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
            'role' => $user->role->value,
        ];
    }

    private function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'email' => '',
            'role' => UserRole::Subscriber->value,
        ];
    }

    private function resetInviteForm(): void
    {
        $this->inviteForm = [
            'name' => '',
            'email' => '',
            'role' => UserRole::Subscriber->value,
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

    private function isLastActiveAdmin(User $user): bool
    {
        return ! User::query()
            ->where('role', UserRole::Admin)
            ->whereKeyNot($user->id)
            ->whereNull('suspended_at')
            ->exists();
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

    <div class="mt-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="md">{{ __('Invite a user') }}</flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Send an email invite so they can set a password and join the workspace.') }}
                </flux:text>
            </div>

            <x-action-message on="user-invited" class="text-sm text-emerald-600 dark:text-emerald-400">
                {{ __('Invitation sent.') }}
            </x-action-message>
        </div>

        <form wire:submit="inviteUser" class="mt-4 grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(0,1.5fr)_minmax(0,auto)]">
            <div>
                <flux:input
                    wire:model="inviteForm.name"
                    :label="__('Name')"
                    type="text"
                    required
                    autocomplete="name"
                />
                @error('inviteForm.name')
                    <flux:text color="red" class="mt-1 text-xs">{{ $message }}</flux:text>
                @enderror
            </div>

            <div>
                <flux:input
                    wire:model="inviteForm.email"
                    :label="__('Email')"
                    type="email"
                    required
                    autocomplete="email"
                />
                @error('inviteForm.email')
                    <flux:text color="red" class="mt-1 text-xs">{{ $message }}</flux:text>
                @enderror
            </div>

            <div>
                <flux:select
                    wire:model="inviteForm.role"
                    :label="__('Role')"
                    required
                >
                    @foreach ($this->roleOptions as $roleOption)
                        <flux:select.option value="{{ $roleOption->value }}">
                            {{ $roleOption->label() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                @error('inviteForm.role')
                    <flux:text color="red" class="mt-1 text-xs">{{ $message }}</flux:text>
                @enderror
            </div>

            <div class="flex items-end">
                <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="inviteUser">{{ __('Send invite') }}</span>
                    <span wire:loading wire:target="inviteUser">{{ __('Sending...') }}</span>
                </flux:button>
            </div>
        </form>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-4">
            <flux:table>
                <flux:table.header>
                    <span>{{ __('Name') }}</span>
                    <span>{{ __('Email') }}</span>
                    <span>{{ __('Role') }}</span>
                    <span>{{ __('Status') }}</span>
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

                            <flux:table.cell>
                                <flux:badge color="{{ $user->role === UserRole::Admin ? 'purple' : 'zinc' }}">
                                    {{ $user->role->label() }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if ($user->isSuspended())
                                    <flux:badge color="red">{{ __('Suspended') }}</flux:badge>
                                @else
                                    <flux:badge color="green">{{ __('Active') }}</flux:badge>
                                @endif
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
                <div class="space-y-3">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <flux:heading size="lg">{{ $selectedUser->name }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Member since :date', ['date' => optional($selectedUser->created_at)?->timezone(config('app.timezone'))->format('F j, Y') ?? __('Unknown')]) }}
                            </flux:text>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <flux:badge color="{{ $selectedUser->isAdmin() ? 'purple' : 'zinc' }}">
                                {{ $selectedUser->role->label() }}
                            </flux:badge>

                            @if ($selectedUser->isSuspended())
                                <flux:badge color="red">{{ __('Suspended') }}</flux:badge>
                            @else
                                <flux:badge color="green">{{ __('Active') }}</flux:badge>
                            @endif
                        </div>
                    </div>

                    @if ($selectedUser->invited_at)
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-500">
                            {{ __('Invited :date', ['date' => $selectedUser->invited_at->timezone(config('app.timezone'))->format('M j, Y g:i A')]) }}
                        </flux:text>
                    @endif
                </div>

                <form wire:submit="saveUser" class="mt-2 space-y-4">
                    <div class="space-y-2">
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
                    </div>

                    <div class="space-y-2">
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
                    </div>

                    <div class="space-y-2">
                        <flux:select
                            wire:model="form.role"
                            :label="__('Role')"
                            required
                        >
                            @foreach ($this->roleOptions as $roleOption)
                                <flux:select.option value="{{ $roleOption->value }}">
                                    {{ $roleOption->label() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('form.role')
                            <flux:text color="red" class="text-xs">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <flux:button type="submit" variant="primary">
                            {{ __('Save changes') }}
                        </flux:button>

                        <flux:button type="button" variant="subtle" wire:click="sendPasswordResetLink" wire:loading.attr="disabled">
                            {{ __('Email password reset link') }}
                        </flux:button>

                        <flux:button type="button" variant="danger" icon="key" wire:click="forcePasswordRotation" wire:loading.attr="disabled">
                            {{ __('Force credential rotation') }}
                        </flux:button>

                        @if ($selectedUser->isSuspended())
                            <flux:button type="button" variant="primary" wire:click="toggleSuspension" wire:loading.attr="disabled">
                                {{ __('Activate user') }}
                            </flux:button>
                        @else
                            <flux:button type="button" variant="danger" wire:click="toggleSuspension" wire:loading.attr="disabled">
                                {{ __('Suspend user') }}
                            </flux:button>
                        @endif

                        <flux:button type="button" variant="danger" icon="trash" wire:click="confirmDeleteUser">
                            {{ __('Delete user') }}
                        </flux:button>

                        <x-action-message on="user-saved" class="text-sm text-emerald-600 dark:text-emerald-400">
                            {{ __('User updated.') }}
                        </x-action-message>

                        <x-action-message on="user-suspended" class="text-sm text-emerald-600 dark:text-emerald-400">
                            {{ __('User suspended.') }}
                        </x-action-message>

                        <x-action-message on="user-activated" class="text-sm text-emerald-600 dark:text-emerald-400">
                            {{ __('User reactivated.') }}
                        </x-action-message>

                        <x-action-message on="user-deleted" class="text-sm text-emerald-600 dark:text-emerald-400">
                            {{ __('User removed.') }}
                        </x-action-message>

                        <x-action-message on="password-reset-link-sent" class="text-sm text-emerald-600 dark:text-emerald-400">
                            {{ __('Password reset email sent.') }}
                        </x-action-message>

                        <x-action-message on="password-rotation-forced" class="text-sm text-emerald-600 dark:text-emerald-400">
                            {{ __('Credentials rotation forced.') }}
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
