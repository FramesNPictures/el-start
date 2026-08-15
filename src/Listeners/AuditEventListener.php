<?php

namespace Fnp\ElStart\Listeners;

use Fnp\ElModule\Helpers\HClassMap;
use Fnp\ElStart\Contracts\Auditable;
use Fnp\ElStart\Models\AppAudit;
use Illuminate\Support\Facades\Auth;

class AuditEventListener
{
    /**
     * Handles every dispatched event and records the auditable ones.
     *
     * @param  string  $eventName  Name of the dispatched event
     * @param  array  $payload  Dispatched event payload
     */
    public function handle(string $eventName, array $payload): void
    {
        $event = $payload[0] ?? null;

        if (! $event instanceof Auditable) {
            return;
        }

        $class = $event::class;

        AppAudit::create([
            'event' => HClassMap::getAlias($class) ?? $class,
            'user_id' => Auth::check() ? Auth::id() : null,
            'payload' => $event->audit(),
        ]);
    }
}
