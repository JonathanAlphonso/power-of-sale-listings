<section id="roadmap" class="scroll-mt-28 lg:scroll-mt-32 border-t border-slate-200 bg-white px-6 py-24 lg:px-8">
    <div class="mx-auto max-w-6xl">
        <div class="max-w-3xl">
            <x-ui.section-badge>Roadmap</x-ui.section-badge>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Four milestones to launch</h2>
            <p class="mt-3 text-lg text-slate-600">We're aligning ingestion, review, and delivery to bring Ontario's power of sale intelligence online with discipline.</p>
        </div>
        <div class="mt-12 grid gap-6 lg:grid-cols-2">
            <x-ui.card variant="red">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.35em] text-red-500">
                    <span>M0</span>
                    <span>Foundation</span>
                </div>
                <h3 class="mt-4 text-2xl font-semibold text-slate-900">Baseline ingestion + admin shell</h3>
                <ul class="mt-6 space-y-3 text-sm text-slate-600">
                    <li>• Tooling prerequisites verified for local machines and CI.</li>
                    <li>• Listings schema, factories, and seed data ship Ontario demos.</li>
                    <li>• Volt + Flux layout established with authentication guardrails.</li>
                </ul>
                <span class="mt-6 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-emerald-500">Complete · Foundation ready</span>
            </x-ui.card>
            <x-ui.card variant="blue">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.35em] text-blue-500">
                    <span>M1</span>
                    <span>Admin</span>
                </div>
                <h3 class="mt-4 text-2xl font-semibold text-slate-900">Interactive admin for analysts</h3>
                <ul class="mt-6 space-y-3 text-sm text-slate-600">
                    <li>• Admin listing management, suppression, and audit trails delivered.</li>
                    <li>• User lifecycle, analytics settings, and policy coverage verified.</li>
                    <li>• Final navigation updates exposing admin-only routes underway.</li>
                </ul>
                <span class="mt-6 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-blue-500">In progress · Finalizing navigation guard</span>
            </x-ui.card>
            <x-ui.card variant="emerald">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.35em] text-emerald-500">
                    <span>M2</span>
                    <span>Ingestion</span>
                </div>
                <h3 class="mt-4 text-2xl font-semibold text-slate-900">Normalization + nightly integrity</h3>
                <ul class="mt-6 space-y-3 text-sm text-slate-600">
                    <li>• PropTx API integration with credential and scheduling controls.</li>
                    <li>• Payload normalization, snapshot storage, and change logging.</li>
                    <li>• Nightly QA, retry logic, and duplicate detection with alerts.</li>
                </ul>
                <span class="mt-6 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-emerald-500">Queued · Begins after admin wrap-up</span>
            </x-ui.card>
            <x-ui.card variant="slate">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.35em] text-slate-500">
                    <span>M3</span>
                    <span>Launch</span>
                </div>
                <h3 class="mt-4 text-2xl font-semibold text-slate-900">Public portal + notifications</h3>
                <ul class="mt-6 space-y-3 text-sm text-slate-600">
                    <li>• Public search, detail pages, and compliance content.</li>
                    <li>• Saved searches with notification preferences and digests.</li>
                    <li>• Contact workflows, rate limiting, and launch communications.</li>
                </ul>
                <span class="mt-6 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Planned · Post-ingestion rollout</span>
            </x-ui.card>
        </div>
    </div>
</section>
