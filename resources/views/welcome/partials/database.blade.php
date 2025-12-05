<section id="database" class="scroll-mt-28 lg:scroll-mt-32 border-t border-slate-200 bg-white px-6 py-24 lg:px-8 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="mx-auto max-w-6xl">
        <x-ui.card>
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div>
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl dark:text-white">Database diagnostics</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-zinc-400">A quick snapshot of the current MySQL connection powering this page.</p>
                </div>
                <x-ui.section-badge :variant="$dbConnected ? 'success' : 'danger'">
                    {{ $dbConnected ? 'Connected' : 'Disconnected' }}
                </x-ui.section-badge>
            </div>

            @if ($dbConnected)
                @if (config('app.env') === 'local')
                    <dl class="mt-8 grid gap-6 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6 dark:border-zinc-800 dark:bg-zinc-900/50">
                            <dt class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500 dark:text-zinc-500">Database</dt>
                            <dd class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">{{ $databaseName ?? 'Unknown' }}</dd>
                            <p class="mt-2 text-sm text-slate-600 dark:text-zinc-400">Active connection determined by the Laravel configuration.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6 dark:border-zinc-800 dark:bg-zinc-900/50">
                            <dt class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500 dark:text-zinc-500">Sample tables</dt>
                            <dd class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">
                                @if (! empty($tableSample))
                                    {{ implode(', ', $tableSample) }}
                                @else
                                    {{ __('None detected') }}
                                @endif
                            </dd>
                            <p class="mt-2 text-sm text-slate-600 dark:text-zinc-400">Limited to the first five tables returned by <code class="whitespace-nowrap text-xs text-slate-500 dark:text-zinc-500">SHOW TABLES</code>.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6 dark:border-zinc-800 dark:bg-zinc-900/50">
                            <dt class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500 dark:text-zinc-500">Users table total</dt>
                            <dd class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">
                                {{ $userCount === null ? __('Not available') : number_format($userCount) }}
                            </dd>
                            <p class="mt-2 text-sm text-slate-600 dark:text-zinc-400">Pulled live using Eloquent to confirm model wiring after migrations.</p>
                        </div>
                    </dl>
                @else
                    <div class="mt-6 rounded-2xl border border-amber-100 bg-amber-50/80 p-6 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-400">
                        <p>{{ __('Database connection is configured and healthy.') }}</p>
                        <p class="mt-2 text-xs text-amber-700/80 dark:text-amber-500/80">
                            {{ __('Detailed diagnostics are only shown in local environments to protect sensitive information.') }}
                        </p>
                    </div>
                @endif
            @else
                <div class="mt-6 rounded-2xl border border-red-100 bg-red-50/80 p-6 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-400">
                    <p>{{ __('Unable to connect using the configured credentials.') }}</p>
                    @if (config('app.env') === 'local' && $dbErrorMessage)
                        <p class="mt-2 text-xs text-red-500/80 dark:text-red-400/80">{{ $dbErrorMessage }}</p>
                    @endif
                </div>
            @endif
        </x-ui.card>
    </div>
</section>
