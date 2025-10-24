<x-layouts.site :title="config('app.name', 'Power of Sale Ontario')">
    @php
        $hasLogin = Route::has('login');
        $hasRegister = Route::has('register');
    @endphp

    <div class="relative overflow-hidden bg-[#f6f8fb] text-slate-800">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-40 top-[-18rem] h-[28rem] w-[28rem] rounded-full bg-[#ffeadb]/60 blur-3xl"></div>
            <div class="absolute bottom-[-16rem] right-[-10rem] h-[30rem] w-[30rem] rounded-full bg-[#d5e6ff]/60 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(220,38,38,0.08),_transparent_60%)]"></div>
        </div>

        <div class="relative z-10">
            <section id="hero" class="px-6 pb-24 pt-20 sm:pt-28 lg:px-8 scroll-mt-32">
                <div class="mx-auto max-w-6xl">
                    <div class="relative overflow-hidden rounded-[2.5rem] border border-slate-200 bg-white/95 px-6 py-14 text-center shadow-[0_30px_90px_rgba(15,23,42,0.08)] sm:px-10 lg:px-16">
                        <div class="pointer-events-none absolute inset-y-0 left-0 hidden w-2/5 bg-[radial-gradient(circle_at_top_left,_rgba(217,4,41,0.15),_transparent_55%)] sm:block"></div>
                        <div class="pointer-events-none absolute inset-y-0 right-0 hidden w-2/5 bg-[radial-gradient(circle_at_top_right,_rgba(37,99,235,0.14),_transparent_60%)] sm:block"></div>
                        <div class="relative">
                            <div class="mx-auto mb-6 inline-flex items-center gap-2 rounded-full border border-red-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-red-600 shadow-sm">
                                <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                <span>M0 · Ontario launch groundwork underway</span>
                            </div>
                            <h1 class="text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                                Elevate Ontario power of sale discovery with Canadian precision
                            </h1>
                            <p class="mt-6 text-lg leading-relaxed text-slate-600 sm:text-xl">
                                We are crafting a Canada-first listings intelligence platform that blends lender disclosures, MLS feeds, and legal notices into a clean Ontario-focused dataset for investors, brokers, and compliance teams.
                            </p>
                            <div class="mt-6 flex flex-wrap justify-center gap-3 text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">
                                <span class="rounded-full border border-red-200 bg-white px-3 py-1 text-red-500">Ontario MLS coverage</span>
                                <span class="rounded-full border border-blue-200 bg-white px-3 py-1 text-blue-600">Canadian compliance ready</span>
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600">Bilingual data support</span>
                            </div>
                            <div class="mt-8 flex flex-wrap items-center justify-center gap-2 text-[0.7rem] font-semibold uppercase tracking-[0.4em] text-slate-400">
                                <span>Trusted by teams in</span>
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-slate-500">Toronto</span>
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-slate-500">Ottawa</span>
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-slate-500">Hamilton</span>
                            </div>
                            <div class="mt-10 flex flex-wrap justify-center gap-4">
                                <a href="#roadmap" class="rounded-full bg-[#d90429] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#b90322]">Explore the roadmap</a>
                                <a href="#pipeline" class="rounded-full border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-[#d90429] hover:text-[#d90429]">See the pipeline</a>
                            </div>
                            <dl class="mt-14 grid gap-6 text-left sm:grid-cols-3">
                                <div class="rounded-3xl border border-red-100 bg-white p-6 shadow-[0_15px_45px_rgba(217,4,41,0.12)]">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.25em] text-red-500">Coverage</dt>
                                    <dd class="mt-3 text-3xl font-semibold text-slate-900">Province-wide</dd>
                                    <p class="mt-3 text-sm text-slate-600">Ingest MLS, lender disclosures, and public registry data into a unified Ontario timeline.</p>
                                </div>
                                <div class="rounded-3xl border border-blue-100 bg-white p-6 shadow-[0_15px_45px_rgba(59,130,246,0.12)]">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-600">Freshness</dt>
                                    <dd class="mt-3 text-3xl font-semibold text-slate-900">Sub-24h</dd>
                                    <p class="mt-3 text-sm text-slate-600">Playwright-driven scrapes queue through Laravel jobs with Redis-backed retries.</p>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_15px_45px_rgba(15,23,42,0.1)]">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-500">Confidence</dt>
                                    <dd class="mt-3 text-3xl font-semibold text-slate-900">Audit-ready</dd>
                                    <p class="mt-3 text-sm text-slate-600">JSON payloads, verification logs, and export history are preserved for Canadian compliance.</p>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </section>

            <section id="product" class="border-t border-slate-200 bg-gradient-to-br from-white via-[#fff5f5] to-[#f3f7ff] px-6 py-24 lg:px-8 scroll-mt-32">
                <div class="mx-auto max-w-6xl">
                    <div class="max-w-3xl">
                        <span class="inline-flex items-center gap-2 rounded-full border border-red-100 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-red-500">Platform</span>
                        <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Purpose-built for the power of sale lifecycle</h2>
                        <p class="mt-4 text-lg text-slate-600">Every workflow in the build plan informs how the product takes shape—from sourcing to stakeholder hand-off.</p>
                    </div>
                    <div class="mt-12 grid gap-6 md:grid-cols-2">
                        <article class="rounded-3xl border border-red-100 bg-white/90 p-8 shadow-[0_20px_60px_rgba(217,4,41,0.08)]">
                            <h3 class="text-xl font-semibold text-slate-900">Unified Listings Intelligence</h3>
                            <p class="mt-3 text-sm text-slate-600">Listings, raw ingests, verifications, exports, and audit logs share a consistent schema so teams can filter by municipality, lender, or asset class instantly.</p>
                            <ul class="mt-6 space-y-2 text-sm text-slate-600">
                                <li>• Dummy listings seeded via factories for rapid demos.</li>
                                <li>• JSON columns retain raw payloads for post-processing.</li>
                                <li>• Flux UI tables surface scores, tags, and follow-ups.</li>
                            </ul>
                        </article>
                        <article class="rounded-3xl border border-blue-100 bg-white/90 p-8 shadow-[0_20px_60px_rgba(59,130,246,0.08)]">
                            <h3 class="text-xl font-semibold text-slate-900">Operational Control Room</h3>
                            <p class="mt-3 text-sm text-slate-600">Volt-powered admin pages orchestrate verifications, manual reviews, and queue monitoring—keeping humans in the loop where it matters.</p>
                            <ul class="mt-6 space-y-2 text-sm text-slate-600">
                                <li>• Reusable Flux modals manage verification workflows.</li>
                                <li>• Role-based access via Laravel Fortify policies.</li>
                                <li>• Audit trails ensure export decisions stay traceable.</li>
                            </ul>
                        </article>
                        <article class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow-[0_20px_60px_rgba(15,23,42,0.08)]">
                            <h3 class="text-xl font-semibold text-slate-900">Insights & Delivery</h3>
                            <p class="mt-3 text-sm text-slate-600">Queue-backed exports hydrate S3, while scheduled dashboards highlight distressed trends across Ontario using future Chart.js widgets.</p>
                            <ul class="mt-6 space-y-2 text-sm text-slate-600">
                                <li>• CSV/Parquet exports encrypted with SSE-KMS.</li>
                                <li>• Staging vs production guardrails baked into Horizon.</li>
                                <li>• Automated alerts blend email, webhooks, and queues.</li>
                            </ul>
                        </article>
                        <article class="rounded-3xl border border-emerald-100 bg-white/90 p-8 shadow-[0_20px_60px_rgba(16,185,129,0.08)]">
                            <h3 class="text-xl font-semibold text-slate-900">Field Team Enablement</h3>
                            <p class="mt-3 text-sm text-slate-600">Responsive views deliver property snapshots, while offline-first notes sync back into Volt components once connectivity returns.</p>
                            <ul class="mt-6 space-y-2 text-sm text-slate-600">
                                <li>• Tailwind 4 tokens keep dark mode and accessibility aligned.</li>
                                <li>• Livewire actions trigger map pins and inspection checklists.</li>
                                <li>• Context-rich prompts support fast diligence.</li>
                            </ul>
                        </article>
                    </div>
                </div>
            </section>

            <section id="roadmap" class="border-t border-slate-200 bg-white px-6 py-24 lg:px-8 scroll-mt-32">
                <div class="mx-auto max-w-6xl">
                    <div class="max-w-2xl">
                        <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-slate-500">Delivery</span>
                        <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Roadmap to launch</h2>
                        <p class="mt-4 text-lg text-slate-600">Each milestone builds confidence—from dummy data smoke tests through production cutover procedures.</p>
                    </div>
                    <div class="mt-12 grid gap-6 lg:grid-cols-3">
                        <article class="flex h-full flex-col rounded-3xl border border-red-100 bg-white p-6 shadow-[0_20px_50px_rgba(217,4,41,0.08)]">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.3em] text-red-500">M0 · Week 1</h3>
                            <p class="mt-3 text-xl font-semibold text-slate-900">Project Initialization</p>
                            <ul class="mt-5 space-y-2 text-sm text-slate-600">
                                <li>• Livewire starter kit configured with Volt/Flux layout.</li>
                                <li>• Dummy listings seeded for end-to-end smoke tests.</li>
                                <li>• GitHub Actions pipeline and .env templates in place.</li>
                            </ul>
                            <span class="mt-6 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-red-500">Status · In progress</span>
                        </article>
                        <article class="flex h-full flex-col rounded-3xl border border-blue-100 bg-white p-6 shadow-[0_20px_50px_rgba(59,130,246,0.08)]">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.3em] text-blue-600">M1 · Weeks 2-4</h3>
                            <p class="mt-3 text-xl font-semibold text-slate-900">Data Foundation</p>
                            <ul class="mt-5 space-y-2 text-sm text-slate-600">
                                <li>• MySQL schema tuned for listings, verifications, exports.</li>
                                <li>• Playwright-powered scraping jobs queued via Redis.</li>
                                <li>• S3-connected export flows with encryption defaults.</li>
                            </ul>
                            <span class="mt-6 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-blue-600">Next checkpoint · Automated QA</span>
                        </article>
                        <article class="flex h-full flex-col rounded-3xl border border-emerald-100 bg-white p-6 shadow-[0_20px_50px_rgba(16,185,129,0.08)]">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.3em] text-emerald-600">M2 · Weeks 5-8</h3>
                            <p class="mt-3 text-xl font-semibold text-slate-900">Review & Delivery</p>
                            <ul class="mt-5 space-y-2 text-sm text-slate-600">
                                <li>• Volt dashboards for verification, queue monitoring, health.</li>
                                <li>• Alerts & exports hardened with escalation playbooks.</li>
                                <li>• Compliance documentation and operational runbooks.</li>
                            </ul>
                            <span class="mt-6 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-emerald-600">Launch-ready · Pending cutover</span>
                        </article>
                    </div>
                </div>
            </section>

            <section id="pipeline" class="border-t border-slate-200 bg-gradient-to-b from-white via-[#f7fbff] to-[#fff5f5] px-6 py-24 lg:px-8 scroll-mt-32">
                <div class="mx-auto max-w-6xl">
                    <div class="max-w-3xl">
                        <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-slate-500">Workflow</span>
                        <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Automation pipeline, end to end</h2>
                        <p class="mt-4 text-lg text-slate-600">Orchestrated jobs, clear ownership, and observability ensure every listing reaches the right team in record time.</p>
                    </div>
                    <div class="mt-12 grid gap-6 lg:grid-cols-4">
                        <article class="group rounded-3xl border border-red-100 bg-white p-6 shadow-[0_18px_40px_rgba(217,4,41,0.08)]">
                            <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-sm font-semibold text-red-600">01</span>
                            <h3 class="text-lg font-semibold text-slate-900">Source & Capture</h3>
                            <p class="mt-3 text-sm text-slate-600">Scheduled Playwright runs capture MLS, bank, and court sources with pinned browser versions for stability.</p>
                        </article>
                        <article class="group rounded-3xl border border-blue-100 bg-white p-6 shadow-[0_18px_40px_rgba(59,130,246,0.08)]">
                            <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-600">02</span>
                            <h3 class="text-lg font-semibold text-slate-900">Normalize & Score</h3>
                            <p class="mt-3 text-sm text-slate-600">Laravel queues enrich, geocode, and score each property while JSON payloads remain accessible for audits.</p>
                        </article>
                        <article class="group rounded-3xl border border-emerald-100 bg-white p-6 shadow-[0_18px_40px_rgba(16,185,129,0.08)]">
                            <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-600">03</span>
                            <h3 class="text-lg font-semibold text-slate-900">Review & Approve</h3>
                            <p class="mt-3 text-sm text-slate-600">Volt review queues surface anomalies, with Flux UI modals guiding verification playbooks and escalation paths.</p>
                        </article>
                        <article class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.08)]">
                            <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700">04</span>
                            <h3 class="text-lg font-semibold text-slate-900">Deliver & Learn</h3>
                            <p class="mt-3 text-sm text-slate-600">Exports hit S3, notifications reach stakeholders, and metrics flow into runbooks, Horizon, and future observability suites.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="database" class="border-t border-slate-200 bg-white px-6 py-24 lg:px-8 scroll-mt-32">
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
                                            None detected
                                        @endif
                                    </dd>
                                    <p class="mt-2 text-sm text-slate-600">Limited to the first five tables returned by <code class="whitespace-nowrap text-xs text-slate-500">SHOW TABLES</code>.</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                                    <dt class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Users table total</dt>
                                    <dd class="mt-2 text-lg font-semibold text-slate-900">
                                        {{ $userCount === null ? 'Not available' : number_format($userCount) }}
                                    </dd>
                                    <p class="mt-2 text-sm text-slate-600">Pulled live using Eloquent to confirm model wiring after migrations.</p>
                                </div>
                            </dl>
                        @else
                            <div class="mt-6 rounded-2xl border border-red-100 bg-red-50/80 p-6 text-sm text-red-700">
                                <p>Unable to connect using the configured credentials.</p>
                                @if ($dbErrorMessage)
                                    <p class="mt-2 text-xs text-red-500/80">{{ $dbErrorMessage }}</p>
                                @endif
                            </div>
                        @endif

                    </div>
                </div>
            </section>

            <section id="listings-demo" class="border-t border-slate-200 bg-[#f8fbff] px-6 py-24 lg:px-8 scroll-mt-32">
                <div class="mx-auto max-w-6xl">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-slate-500">Live Demo</span>
                            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Recent demo listings</h2>
                            <p class="mt-2 text-sm text-slate-600">Pulled directly from the application database to verify ingestion pipelines end-to-end.</p>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-[0.35em] {{ $sampleListings->isNotEmpty() ? 'text-emerald-500' : 'text-red-500' }}">
                            {{ $sampleListings->isNotEmpty() ? 'Rendering from database' : 'Unable to display listings' }}
                        </span>
                    </div>

                    @php
                        $fallbackImage = 'https://live-images.stratuscollab.com/ZxJdIMfbzmv_nD9_6Fc-YiOnigxaucskCqQMXAwKSuQ/rs:fill:1900:1900:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS9GVS1HeS1SZldMdU9oRE5Jc3NnWTBMVEFubXFxNlZMbGVBYjZLTUUxcVhzL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2TXpRMk9UVTJNRFF0TUdRellpMDBaV1JsTFRoa1kySXROakZrTUROak5UUTJNRFpsTG1wd1p3LmpwZw.jpg';
                    @endphp

                    @if ($sampleListings->isNotEmpty())
                        <div class="mt-10 grid gap-6 md:grid-cols-3">
                            @foreach ($sampleListings as $listing)
                                @php
                                    $primaryMedia = $listing->media->firstWhere('is_primary', true) ?? $listing->media->first();
                                    $imageUrl = $primaryMedia?->preview_url ?? $primaryMedia?->url ?? '';
                                    if (! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                                        $imageUrl = $fallbackImage;
                                    }
                                    $bedroomsText = $listing->bedrooms !== null ? $listing->bedrooms.' bd' : '— bd';
                                    $bathroomsText = $listing->bathrooms !== null ? rtrim(rtrim(number_format((float) $listing->bathrooms, 1), '0'), '.').' ba' : '— ba';
                                    $sizeText = $listing->square_feet_text
                                        ?? ($listing->square_feet ? number_format($listing->square_feet).' sq ft' : 'Size TBD');
                                    $pricePrefix = $listing->currency === 'CAD' ? '$' : $listing->currency;
                                    $priceDisplay = $listing->price ? number_format((float) $listing->price, 0) : '—';
                                @endphp
                                <article class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition hover:-translate-y-1 hover:shadow-[0_25px_60px_rgba(15,23,42,0.12)]">
                                    <div class="relative h-44 overflow-hidden bg-slate-100">
                                        <img
                                            src="{{ $imageUrl }}"
                                            alt="{{ $listing->street_address ?? 'Listing image' }}"
                                            class="h-full w-full object-cover transition duration-700 group-hover:scale-105"
                                            loading="lazy"
                                        >
                                        <span class="absolute left-4 top-4 inline-flex items-center rounded-full bg-white/90 px-3 py-1 text-xs font-semibold uppercase tracking-[0.4em] text-emerald-600">
                                            {{ $listing->display_status ?? 'ACTIVE' }}
                                        </span>
                                    </div>
                                    <div class="border-t border-slate-200 px-5 py-6">
                                        <div class="flex items-center justify-between gap-3">
                                            <h3 class="text-base font-semibold text-slate-900">{{ $listing->street_address ?? 'Address TBD' }}</h3>
                                            <span class="text-sm font-semibold text-slate-500">{{ $listing->municipality?->name ?? $listing->city ?? 'Ontario' }}</span>
                                        </div>
                                        <p class="mt-2 text-sm text-slate-600">{{ $bedroomsText }} · {{ $bathroomsText }} · {{ $sizeText }}</p>
                                        <p class="mt-4 text-lg font-semibold text-slate-900">
                                            {{ $pricePrefix }}{{ $priceDisplay }}
                                            <span class="ml-2 text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">{{ $listing->sale_type ? strtoupper($listing->sale_type) : 'N/A' }}</span>
                                        </p>
                                        <div class="mt-4 flex items-center justify-between text-xs uppercase tracking-[0.35em] text-slate-400">
                                            <span>{{ $listing->source?->name ?? $listing->board_code ?? 'Unknown' }}</span>
                                            <span>{{ optional($listing->modified_at)->diffForHumans() ?? 'Just now' }}</span>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-10 rounded-3xl border border-red-100 bg-white p-8 text-sm text-red-600 shadow-[0_18px_45px_rgba(248,113,113,0.18)]">
                            @if (! $dbConnected)
                                <p class="font-semibold">Database connection unavailable.</p>
                                <p class="mt-2 text-xs text-red-500/80">
                                    {{ $dbErrorMessage ?? 'Verify your connection credentials and rerun the page.' }}
                                </p>
                            @elseif (! $listingsTableExists)
                                <p class="font-semibold">Listings table missing.</p>
                                <p class="mt-2 text-xs text-red-500/80">Run <code class="text-[0.7rem] font-semibold">php artisan migrate</code> to create the required schema.</p>
                            @else
                                <p class="font-semibold">No listings available to display.</p>
                                <p class="mt-2 text-xs text-red-500/80">Seed demo data with <code class="text-[0.7rem] font-semibold">php artisan migrate --seed</code> or ingest data through the CSV/queue pipeline.</p>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            <section id="tech" class="border-t border-slate-200 bg-white px-6 py-24 lg:px-8 scroll-mt-32">
                <div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-2">
                    <span class="inline-flex w-full items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-slate-500 lg:col-span-2">Technology Stack</span>
                    <article class="rounded-3xl border border-red-100 bg-white p-8 shadow-[0_20px_55px_rgba(217,4,41,0.08)]">
                        <h3 class="text-2xl font-semibold text-slate-900">Laravel-first foundation</h3>
                        <p class="mt-4 text-sm text-slate-600">Modern Laravel 12 conventions keep the stack consistent, testable, and ready for scale.</p>
                        <ul class="mt-6 space-y-3 text-sm text-slate-600">
                            <li>• Livewire 3 + Volt single-file components for interactive admin tooling.</li>
                            <li>• Redis-backed queues embraced by Horizon for visibility.</li>
                            <li>• Pest-powered regression suites enforce confidence before deploy.</li>
                        </ul>
                    </article>
                    <article class="rounded-3xl border border-blue-100 bg-white p-8 shadow-[0_20px_55px_rgba(59,130,246,0.08)]">
                        <h3 class="text-2xl font-semibold text-slate-900">Automation ecosystem</h3>
                        <p class="mt-4 text-sm text-slate-600">Every integration is vetted per the build plan for resilience and compliance.</p>
                        <ul class="mt-6 space-y-3 text-sm text-slate-600">
                            <li>• Node.js 20 + Playwright scraping runtime with pinned browsers.</li>
                            <li>• Amazon S3 with SSE-KMS and staged promotion workflows.</li>
                            <li>• Observability runway for Pail, Sentry, and OpenTelemetry.</li>
                        </ul>
                    </article>
                </div>
            </section>

            <section id="cta" class="border-t border-slate-200 bg-gradient-to-r from-[#fff5f5] via-white to-[#f3f7ff] px-6 py-24 lg:px-8 scroll-mt-32">
                <div class="mx-auto max-w-4xl rounded-3xl border border-red-100 bg-white p-12 text-center shadow-[0_25px_70px_rgba(217,4,41,0.12)]">
                    <span class="inline-flex items-center gap-2 rounded-full border border-red-100 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-red-500">Partner Program</span>
                    <h2 class="text-3xl font-semibold text-slate-900 sm:text-4xl">Ready to shape the future of Ontario power of sale intelligence?</h2>
                    <p class="mt-4 text-lg text-slate-600">Become an early design partner to influence workflows, data coverage, and automated diligence before launch.</p>
                    <div class="mt-10 flex flex-wrap justify-center gap-4">
                        @if ($hasRegister)
                            <a href="{{ route('register') }}" class="rounded-full bg-[#d90429] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#b90322]">Join the waitlist</a>
                        @endif
                        @if ($hasLogin)
                            <a href="{{ route('login') }}" class="rounded-full border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-600 transition hover:border-[#d90429] hover:text-[#d90429]">Partner login</a>
                        @endif
                        @if (! $hasRegister && ! $hasLogin)
                            <a href="mailto:hello@powerofsale.ca" class="rounded-full bg-[#d90429] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#b90322]">Contact us</a>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-layouts.site>
