<section id="database" class="border-t border-slate-200 bg-white px-6 py-24 lg:px-8">
    <div class="mx-auto max-w-6xl">
        <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-[0_20px_55px_rgba(15,23,42,0.08)]">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Database diagnostics</h2>
                    <p class="mt-2 text-sm text-slate-600">A quick snapshot of the current MySQL connection powering this page.</p>
                </div>
                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em] {{ $dbConnected ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-red-200 bg-red-50 text-red-600' }}">
                    {{ $dbConnected ? 'Connected' : 'Disconnected' }}
                </span>
            </div>

            @if ($dbConnected)
                <dl class="mt-8 grid gap-6 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                        <dt class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Database</dt>
                        <dd class="mt-2 text-lg font-semibold text-slate-900">{{ $databaseName ?? 'Unknown' }}</dd>
                        <p class="mt-2 text-sm text-slate-600">Active connection determined by the Laravel configuration.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                        <dt class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Sample tables</dt>
                        <dd class="mt-2 text-lg font-semibold text-slate-900">
                            @if (! empty($tableSample))
                                {{ implode(', ', $tableSample) }}
                            @else
                                {{ __('None detected') }}
                            @endif
                        </dd>
                        <p class="mt-2 text-sm text-slate-600">Limited to the first five tables returned by <code class="whitespace-nowrap text-xs text-slate-500">SHOW TABLES</code>.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                        <dt class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Users table total</dt>
                        <dd class="mt-2 text-lg font-semibold text-slate-900">
                            {{ $userCount === null ? __('Not available') : number_format($userCount) }}
                        </dd>
                        <p class="mt-2 text-sm text-slate-600">Pulled live using Eloquent to confirm model wiring after migrations.</p>
                    </div>
                </dl>
            @else
                <div class="mt-6 rounded-2xl border border-red-100 bg-red-50/80 p-6 text-sm text-red-700">
                    <p>{{ __('Unable to connect using the configured credentials.') }}</p>
                    @if ($dbErrorMessage)
                        <p class="mt-2 text-xs text-red-500/80">{{ $dbErrorMessage }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
