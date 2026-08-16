<?php

namespace Fnp\ElStart\Listeners;

use Fnp\ElModule\Helpers\HClassMap;
use Fnp\ElStart\Contracts\Auditable;
use Fnp\ElStart\Models\AppAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        $name = HClassMap::getAlias($class) ?? $class;

        try {
            AppAudit::create([
                'event' => $name,
                'user_id' => Auth::check() ? Auth::id() : null,
                'payload' => $event->audit(),
            ]);
        } catch (Throwable $exception) {
            // Silently fail to prevent audit failures from breaking the app,
            // but leave a trace of it for whoever goes looking. The payload
            // stays out of the log: it belongs in the audit table, not here.
            Log::debug('[APP] Audit entry for ' . $name . ' could not be written.', [
                'exception' => $exception,
            ]);
        }
    }
}
