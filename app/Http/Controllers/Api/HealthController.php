<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Liveness for the container healthcheck and for anything watching the deployment. It
 * touches the database on purpose: the served application reads its connection settings
 * from `.env` rather than from the compose file, so a check that only proved PHP was
 * answering would report a healthy container whose every real route returns 500.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->select('select 1');
        } catch (Throwable $exception) {
            Log::error('Health check could not reach the database.', ['exception' => $exception]);

            return response()->json(['status' => 'error', 'database' => 'unavailable'], 503);
        }

        return response()->json(['status' => 'ok', 'database' => 'ok']);
    }
}
