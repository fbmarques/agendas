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

        $data = $request->validated();
        $gerentes = $data['gerentes'] ?? null;
        unset($data['gerentes']);

        $local = Local::create($data);

        if (is_array($gerentes)) {
            $local->gerentes()->sync($gerentes);
        }

        return response()->json($local->fresh(), 201);
    }

    public function show(Local $local): JsonResource
    {
        return new JsonResource($local);
    }

    public function update(UpdateLocalRequest $request, Local $local): JsonResource
    {
        $this->authorize('update', $local);

        $data = $request->validated();
        $gerentes = $data['gerentes'] ?? null;
        unset($data['gerentes']);

        $local->update($data);

        if (is_array($gerentes)) {
            $local->gerentes()->sync($gerentes);
        }

        return new JsonResource($local->fresh());
    }

    public function gerentes(Local $local): JsonResource
    {
        return JsonResource::collection($local->gerentes()->orderBy('full_name')->get());
    }

    public function setGerentes(Request $request, Local $local): JsonResource
    {
        $this->authorize('update', $local);

        $data = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $local->gerentes()->sync($data['user_ids']);

        return JsonResource::collection($local->gerentes()->orderBy('full_name')->get());
    }

    public function destroy(Local $local): JsonResponse
    {
        $this->authorize('delete', $local);

        $local->delete();

        return response()->json(null, 204);
    }
}
