<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGrupoRequest;
use App\Http\Requests\Api\UpdateGrupoRequest;
use App\Models\Grupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrupoController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $query = Grupo::query();

        if ($campi = $request->query('campi_id')) {
            $query->where('campi_id', $campi);
        }

        return JsonResource::collection($query->orderBy('nome')->get());
    }

    public function store(StoreGrupoRequest $request): JsonResponse
    {
        $this->authorize('create', Grupo::class);

        $grupo = Grupo::create($request->validated());

        return response()->json($grupo, 201);
    }

    public function show(Grupo $grupo): JsonResource
    {
        return new JsonResource($grupo);
    }

    public function update(UpdateGrupoRequest $request, Grupo $grupo): JsonResource
    {
        $this->authorize('update', $grupo);

        $grupo->update($request->validated());

        return new JsonResource($grupo->fresh());
    }

    public function destroy(Grupo $grupo): JsonResponse
    {
        $this->authorize('delete', $grupo);

        $grupo->delete();

        return response()->json(null, 204);
    }
}
