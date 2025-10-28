<section id="pipeline" class="scroll-mt-28 lg:scroll-mt-32 border-t border-slate-200 bg-gradient-to-b from-white via-[#f7fbff] to-[#fff5f5] px-6 py-24 lg:px-8">
    <div class="mx-auto max-w-6xl">
        <div class="max-w-3xl">
            <x-ui.section-badge>Workflow</x-ui.section-badge>
            <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Automation pipeline, end to end</h2>
            <p class="mt-4 text-lg text-slate-600">Orchestrated jobs, clear ownership, and observability ensure every listing reaches the right team in record time.</p>
        </div>
        <div class="mt-12 grid gap-6 lg:grid-cols-4">
            <x-ui.pipeline-step number="01" title="Source & Capture" variant="red">
                Scheduled Playwright runs capture MLS, bank, and court sources with pinned browser versions for stability.
            </x-ui.pipeline-step>

            <x-ui.pipeline-step number="02" title="Normalize & Score" variant="blue">
                Laravel queues enrich, geocode, and score each property while JSON payloads remain accessible for audits.
            </x-ui.pipeline-step>

            <x-ui.pipeline-step number="03" title="Review & Approve" variant="emerald">
                Volt review queues surface anomalies, with Flux UI modals guiding verification playbooks and escalation paths.
            </x-ui.pipeline-step>

            <x-ui.pipeline-step number="04" title="Deliver & Learn">
                Exports hit S3, notifications reach stakeholders, and metrics flow into runbooks, Horizon, and future observability suites.
            </x-ui.pipeline-step>
        </div>
    </div>
</section>
