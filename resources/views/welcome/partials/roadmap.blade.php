<section id="roadmap" class="border-t border-slate-200 bg-white px-6 py-24 lg:px-8 scroll-mt-32">
    <div class="mx-auto max-w-6xl">
        <div class="max-w-3xl">
            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-slate-500">Roadmap</span>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Four milestones to launch</h2>
            <p class="mt-3 text-lg text-slate-600">We’re aligning ingestion, review, and delivery to bring Ontario’s power of sale intelligence online with discipline.</p>
        </div>
        <div class="mt-12 grid gap-6 lg:grid-cols-2">
            <article class="rounded-3xl border border-red-100 bg-white p-8 shadow-[0_20px_55px_rgba(217,4,41,0.12)]">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.35em] text-red-500">
                    <span>M0</span>
                    <span>Foundation</span>
                </div>
                <h3 class="mt-4 text-2xl font-semibold text-slate-900">Baseline ingestion + admin shell</h3>
                <ul class="mt-6 space-y-3 text-sm text-slate-600">
                    <li>• Laravel starter baseline validated locally and in CI.</li>
                    <li>• Listings schema, seed data, and factory coverage.</li>
                    <li>• Volt admin shell with Flux navigation and access control.</li>
                </ul>
                <span class="mt-6 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-red-500">In progress · Current sprint</span>
            </article>
            <article class="rounded-3xl border border-blue-100 bg-white p-8 shadow-[0_20px_55px_rgba(59,130,246,0.12)]">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.35em] text-blue-500">
                    <span>M1</span>
                    <span>Admin</span>
                </div>
                <h3 class="mt-4 text-2xl font-semibold text-slate-900">Interactive admin for analysts</h3>
                <ul class="mt-6 space-y-3 text-sm text-slate-600">
                    <li>• Filtering, sorting, and previewing canonical listings.</li>
                    <li>• Audit log visibility and saved search management.</li>
                    <li>• Role-based access policies hardened with Fortify.</li>
                </ul>
                <span class="mt-6 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-blue-500">Planned · Q2 kickoff</span>
            </article>
            <article class="rounded-3xl border border-emerald-100 bg-white p-8 shadow-[0_20px_55px_rgba(16,185,129,0.12)]">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.35em] text-emerald-500">
                    <span>M2</span>
                    <span>Ingestion</span>
                </div>
                <h3 class="mt-4 text-2xl font-semibold text-slate-900">Normalization + nightly integrity</h3>
                <ul class="mt-6 space-y-3 text-sm text-slate-600">
                    <li>• CSV import workflows with validation and queuing.</li>
                    <li>• Normalized payload storage with status history.</li>
                    <li>• Data health jobs with alerts and triage guidance.</li>
                </ul>
                <span class="mt-6 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-emerald-500">Scoped · Dependency on M1</span>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-8 shadow-[0_20px_55px_rgba(15,23,42,0.12)]">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.35em] text-slate-500">
                    <span>M3</span>
                    <span>Launch</span>
                </div>
                <h3 class="mt-4 text-2xl font-semibold text-slate-900">Public portal + notifications</h3>
                <ul class="mt-6 space-y-3 text-sm text-slate-600">
                    <li>• Public search, detail view, and compliance pages.</li>
                    <li>• Saved searches with email digests and rate limiting.</li>
                    <li>• Launch runbooks, monitoring, and response playbooks.</li>
                </ul>
                <span class="mt-6 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Launch-ready · Pending cutover</span>
            </article>
        </div>
    </div>
</section>
