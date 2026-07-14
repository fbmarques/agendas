<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCampiRequest;
use App\Http\Requests\Api\UpdateCampiRequest;
use App\Models\Campi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class CampiController extends Controller
{
    public function index(): JsonResource
    {
        return JsonResource::collection(Campi::query()->orderBy('nome')->get());
    }

    public function store(StoreCampiRequest $request): JsonResponse
    {
        $this->authorize('create', Campi::class);

        $campi = Campi::create($request->validated());

        return response()->json($campi, 201);
    }

    public function show(Campi $campi): JsonResource
    {
        return new JsonResource($campi);
    }

    public function update(UpdateCampiRequest $request, Campi $campi): JsonResource
    {
        $this->authorize('update', $campi);

        $campi->update($request->validated());

        return new JsonResource($campi->fresh());
    }

    public function destroy(Campi $campi): JsonResponse
    {
        $this->authorize('delete', $campi);

        $campi->delete();

        return response()->json(null, 204);
    }
}
