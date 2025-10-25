<div class="flex flex-col w-full mx-auto space-y-6 text-sm" wire:cloak>
    @if ($twoFactorEnabled)
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <flux:badge color="green">{{ __('Enabled') }}</flux:badge>
            </div>

            <flux:text>
                {{ __('With two-factor authentication enabled, you will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
            </flux:text>

            <livewire:settings.two-factor.recovery-codes :$requiresConfirmation/>

            <div class="flex justify-start">
                <flux:button
                    variant="danger"
                    icon="shield-exclamation"
                    icon:variant="outline"
                    wire:click="disable"
                >
                    {{ __('Disable 2FA') }}
                </flux:button>
            </div>
        </div>
    @else
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <flux:badge color="red">{{ __('Disabled') }}</flux:badge>
            </div>

            <flux:text variant="subtle">
                {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
            </flux:text>

            <flux:button
                variant="primary"
                icon="shield-check"
                icon:variant="outline"
                wire:click="enable"
            >
                {{ __('Enable 2FA') }}
            </flux:button>
        </div>
    @endif
</div>
