<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Http\Requests\DistrictRequest;
use App\Models\District;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistrictController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $districts = District::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('naam', 'like', "%{$search}%");
            })
            ->orderBy('naam')
            ->get();

        return response()->json(['data' => $districts]);
    }

    public function store(DistrictRequest $request): JsonResponse
    {
        $district = District::query()->create($request->validated());

        return response()->json(['data' => $district], 201);
    }

    public function update(DistrictRequest $request, District $district): JsonResponse
    {
        $district->update($request->validated());

        return response()->json(['data' => $district]);
    }

    public function destroy(District $district): JsonResponse
    {
        if ($district->gemeentes()->exists()) {
            return response()->json([
                'message' => __('api.beheer.district_has_gemeentes'),
            ], 409);
        }

        $district->delete();

        return response()->json(null, 204);
    }
}
