<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePeriodoRequest;
use App\Http\Requests\Api\UpdatePeriodoRequest;
use App\Models\Periodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PeriodoController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $query = Periodo::query();
        if ($status = $request->query('status')) $query->where('status', $status);

        return JsonResource::collection($query->orderByDesc('data_inicio')->get());
    }

    public function store(StorePeriodoRequest $request): JsonResponse
    {
        $this->authorize('create', Periodo::class);
        $periodo = Periodo::create($request->validated());
        return response()->json($periodo, 201);
    }

    public function show(Periodo $periodo): JsonResource
    {
        return new JsonResource($periodo);
    }

    public function update(UpdatePeriodoRequest $request, Periodo $periodo): JsonResource
    {
        $this->authorize('update', $periodo);
        $periodo->update($request->validated());
        return new JsonResource($periodo->fresh());
    }

    public function destroy(Periodo $periodo): JsonResponse
    {
        $this->authorize('delete', $periodo);
        $periodo->delete();
        return response()->json(null, 204);
    }
}
