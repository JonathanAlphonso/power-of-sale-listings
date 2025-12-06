<?php

it('shows milestone progress aligned with the task list', function (): void {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('M1 Admin workspace hardening in progress')
        ->assertSee('Complete · Foundation ready')
        ->assertSee('In progress · Finalizing navigation guard')
        ->assertSee('Queued · Begins after admin wrap-up')
        ->assertSee('Planned · Post-ingestion rollout')
        ->assertSee('Tooling prerequisites verified for local machines and CI.')
        ->assertSee('Admin listing management, suppression, and audit trails delivered.')
        ->assertSee('PropTx API integration with credential and scheduling controls.');
});
