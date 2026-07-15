<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLocalIndisponibilidadeRequest;
use App\Http\Requests\Api\UpdateLocalIndisponibilidadeRequest;
use App\Models\Local;
use App\Models\LocalIndisponibilidade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class LocalIndisponibilidadeController extends Controller
{
    public function index(Local $local): JsonResource
    {
        return JsonResource::collection($local->indisponibilidades()->orderBy('data_inicial')->get());
    }

    public function store(StoreLocalIndisponibilidadeRequest $request, Local $local): JsonResponse
    {
        $this->authorize('create', LocalIndisponibilidade::class);

        $data = $request->validated();
        $data['local_id'] = $local->id;

        $li = LocalIndisponibilidade::create($data);

        return response()->json($li, 201);
    }

    public function update(UpdateLocalIndisponibilidadeRequest $request, LocalIndisponibilidade $indisponibilidade): JsonResource
    {
        $this->authorize('update', $indisponibilidade);

        $indisponibilidade->update($request->validated());

        return new JsonResource($indisponibilidade->fresh());
    }

    public function destroy(LocalIndisponibilidade $indisponibilidade): JsonResponse
    {
        $this->authorize('delete', $indisponibilidade);
        $indisponibilidade->delete();
        return response()->json(null, 204);
    }
}
