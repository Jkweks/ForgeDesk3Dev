<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CompanyLocationController extends Controller
{
    public function index()
    {
        return response()->json(
            CompanyLocation::orderByDesc('is_primary')->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        $location = DB::transaction(function () use ($data) {
            $location = CompanyLocation::create($data + [
                'sort_order' => (int) CompanyLocation::max('sort_order') + 1,
            ]);
            $this->settlePrimary($location, (bool) ($data['is_primary'] ?? false));

            return $location->fresh();
        });

        return response()->json([
            'message' => 'Location created',
            'location' => $location,
        ], 201);
    }

    public function update(Request $request, CompanyLocation $companyLocation)
    {
        $data = $this->validatePayload($request);

        $location = DB::transaction(function () use ($companyLocation, $data) {
            $companyLocation->update($data);
            $this->settlePrimary($companyLocation, (bool) ($data['is_primary'] ?? $companyLocation->is_primary));

            return $companyLocation->fresh();
        });

        return response()->json([
            'message' => 'Location updated',
            'location' => $location,
        ]);
    }

    public function destroy(CompanyLocation $companyLocation)
    {
        if (CompanyLocation::count() <= 1) {
            return response()->json(['message' => 'At least one company location is required'], 422);
        }

        $wasPrimary = $companyLocation->is_primary;
        $companyLocation->delete();

        // Never leave the company without a primary.
        if ($wasPrimary) {
            $next = CompanyLocation::orderBy('sort_order')->orderBy('id')->first();
            $next?->update(['is_primary' => true]);
        }

        return response()->json(['message' => 'Location deleted']);
    }

    private function validatePayload(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'is_primary' => 'sometimes|boolean',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'fax' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            abort(response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }

    /**
     * Enforce exactly one primary. If this location was set primary, clear the
     * flag on every other row; if nothing is primary anymore, promote this one.
     */
    private function settlePrimary(CompanyLocation $location, bool $wantsPrimary): void
    {
        if ($wantsPrimary) {
            CompanyLocation::where('id', '!=', $location->id)->where('is_primary', true)
                ->update(['is_primary' => false]);
            if (! $location->is_primary) {
                $location->update(['is_primary' => true]);
            }

            return;
        }

        if (! CompanyLocation::where('is_primary', true)->exists()) {
            $location->update(['is_primary' => true]);
        }
    }
}
