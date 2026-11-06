<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class PanelPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('clicktap.performance_logging') || ! $request->is('admin/publicaciones-sitio', 'livewire/update')) {
            return $next($request);
        }

        $traceId = (string) Str::uuid();
        $startedAt = hrtime(true);
        $queryCount = 0;
        $queryMilliseconds = 0.0;
        $supabaseQueries = 0;
        $catalogQueries = 0;
        DB::listen(function ($query) use (&$queryCount, &$queryMilliseconds, &$supabaseQueries, &$catalogQueries): void {
            $queryCount++;
            $queryMilliseconds += $query->time;
            if ($query->connectionName === 'supabase') {
                $supabaseQueries++;
            }
            if ($query->connectionName === 'catalog') {
                $catalogQueries++;
            }
        });

        $status = 500;
        $responseBytes = 0;
        try {
            $response = $next($request);
            $status = $response->getStatusCode();
            $responseBytes = strlen((string) $response->getContent());
            $response->headers->set('X-Clicktap-Trace-Id', $traceId);

            return $response;
        } finally {
            Log::info('clicktap.panel_performance', [
                'trace_id' => $traceId,
                'route' => $request->is('livewire/update') ? 'livewire/update' : 'admin/publicaciones-sitio',
                'interaction' => $this->interaction($request),
                'status' => $status,
                'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 1),
                'db_queries' => $queryCount,
                'db_ms' => round($queryMilliseconds, 1),
                'direct_supabase_queries' => $supabaseQueries,
                'catalog_queries' => $catalogQueries,
                'response_bytes' => $responseBytes,
            ]);
        }
    }

    private function interaction(Request $request): string
    {
        if (! $request->is('livewire/update')) {
            return 'initial';
        }

        $allowed = ['showActivity', 'closeActivity', 'toggleFilter', 'createAlbum', 'saveAlbum', 'addSelectedToAlbum', 'deleteAlbum', 'removePageFromAlbum', 'startSaleForPage', 'withdrawPage'];
        foreach ((array) $request->input('components', []) as $component) {
            if (! is_array($component)) {
                continue;
            }
            foreach ((array) ($component['calls'] ?? []) as $call) {
                if (! is_array($call)) {
                    continue;
                }
                $method = $call['method'] ?? null;
                if (is_string($method) && in_array($method, $allowed, true)) {
                    return $method;
                }
            }
            foreach (array_keys((array) ($component['updates'] ?? [])) as $field) {
                if (in_array($field, ['search', 'filter', 'albumFilter', 'sort', 'perPage', 'paginators'], true)) {
                    return $field;
                }
            }
        }

        return 'other';
    }
}
