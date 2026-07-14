<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLocalRequest;
use App\Http\Requests\Api\UpdateLocalRequest;
use App\Models\Local;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocalController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $query = Local::query();

        if ($campiId = $request->query('campi_id')) {
            $query->where('campi_id', $campiId);
        }
        if ($grupoId = $request->query('grupo_id')) {
            $query->where('grupo_id', $grupoId);
        }
        if ($tipo = $request->query('tipo')) {
            $query->where('tipo', $tipo);
        }

        return JsonResource::collection($query->orderBy('nome')->get());
    }

    public function store(StoreLocalRequest $request): JsonResponse
    {
        $this->authorize('create', Local::class);

        $local = Local::create($request->validated());

        return response()->json($local, 201);
    }

    public function show(Local $local): JsonResource
    {
        return new JsonResource($local);
    }

    public function update(UpdateLocalRequest $request, Local $local): JsonResource
    {
        $this->authorize('update', $local);

        $local->update($request->validated());

        return new JsonResource($local->fresh());
    }

    public function destroy(Local $local): JsonResponse
    {
        $this->authorize('delete', $local);

        $local->delete();

        return response()->json(null, 204);
    }
}
