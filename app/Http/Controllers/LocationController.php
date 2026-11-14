<?php

namespace App\Http\Controllers;

use App\Models\Locations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public reference-data API for every address form: GET /api/locations/{states,suburbs,postcodes}.
 * Same data for every visitor, served without session or cookies (routes/web.php) so browsers/CDNs can cache it.
 * Responses are always {"data": [...], "meta": {...}}; errors are {"message": "..."} with 404/422.
 */
class LocationController extends Controller
{
    public function states(): JsonResponse
    {
        if (! Locations::available()) {
            return $this->fail('Location data is not available.', 503);
        }

        return $this->ok(Locations::states(), ['available' => true]);
    }

    public function suburbs(Request $request): JsonResponse
    {
        if (! Locations::available()) {
            return $this->fail('Location data is not available.', 503);
        }
        $stateId = $request->query('state_id');
        if (! is_string($stateId) || ! ctype_digit($stateId) || ! Locations::stateExists((int) $stateId)) {
            return $this->fail('Please choose a valid state.', 422);
        }
        $search = $request->query('search', '');
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', Locations::PER_PAGE);
        if (! is_string($search) || mb_strlen($search) > 60 || ! is_scalar($page) || ! is_scalar($perPage)) {
            return $this->fail('Invalid search.', 422);
        }
        $r = Locations::suburbs((int) $stateId, $search, max(1, min(1000, (int) $page)), (int) $perPage);

        return $this->ok($r['data'], ['page' => max(1, min(1000, (int) $page)), 'has_more' => $r['has_more']]);
    }

    public function postcodes(Request $request): JsonResponse
    {
        $id = $request->query('suburb_id');
        if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1) {
            return $this->fail('Please choose a valid suburb.', 422);
        }
        $postcodes = Locations::postcodes((int) $id);
        if ($postcodes === null) {
            return $this->fail('Suburb not found.', 404);
        }

        return $this->ok(array_map(fn ($p) => ['postcode' => $p], $postcodes), ['suburb_id' => (int) $id]);
    }

    private function ok(array $data, array $meta): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => $meta])->header('Cache-Control', 'public, max-age=300')->header('X-Robots-Tag', 'noindex');
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status)->header('Cache-Control', 'no-store');
    }
}
