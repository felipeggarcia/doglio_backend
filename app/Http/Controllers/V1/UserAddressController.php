<?php

namespace App\Http\Controllers\V1;

use App\Models\UserAddress;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserAddressResource;
use Illuminate\Http\Request;
use App\Support\ApiMessages;

class UserAddressController extends Controller
{
    /**
     * Lista todos os endereços do usuário autenticado.
     * GET /api/v1/addresses
     */
    public function index(Request $request)
    {
        $addresses = UserAddress::where('user_id', $request->user()->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return UserAddressResource::collection($addresses);
    }

    /**
     * Cria um novo endereço para o usuário autenticado.
     * POST /api/v1/addresses
     */
    public function store(Request $request)
    {
        $request->validate([
            'label' => 'nullable|string|max:100',
            'street' => 'required|string|max:255',
            'number' => 'required|string|max:20',
            'complement' => 'nullable|string|max:100',
            'city' => 'required|string|max:255',
            'state' => 'required|string|size:2',
            'zip' => 'required|string|size:8',
            'is_primary' => 'boolean',
        ]);

        // Se o novo endereço for primary, remove o primary dos outros
        if ($request->boolean('is_primary')) {
            UserAddress::where('user_id', $request->user()->id)
                ->update(['is_primary' => false]);
        }

        $address = UserAddress::create([
            'user_id' => $request->user()->id,
            'label' => $request->label,
            'street' => $request->street,
            'number' => $request->number,
            'complement' => $request->complement,
            'city' => $request->city,
            'state' => strtoupper($request->state),
            'zip' => $request->zip,
            'is_primary' => $request->boolean('is_primary'),
        ]);

        return response()->json([
            'success' => true,
            'message' => ApiMessages::ADDRESS_CREATED,
            'data' => new UserAddressResource($address),
        ], 201);
    }

    /**
     * Atualiza um endereço do usuário.
     * PUT /api/v1/addresses/{address}
     */
    public function update(Request $request, UserAddress $address)
    {
        // Garante que o endereço pertence ao usuário autenticado
        $this->authorize('update', $address);

        $request->validate([
            'label' => 'nullable|string|max:100',
            'street' => 'sometimes|string|max:255',
            'number' => 'sometimes|string|max:20',
            'complement' => 'nullable|string|max:100',
            'city' => 'sometimes|string|max:255',
            'state' => 'sometimes|string|size:2',
            'zip' => 'sometimes|string|size:8',
        ]);

        $data = $request->only(['label', 'street', 'number', 'complement', 'city', 'zip']);

        if ($request->has('state')) {
            $data['state'] = strtoupper($request->state);
        }

        $address->update($data);

        return response()->json([
            'success' => true,
            'message' => ApiMessages::ADDRESS_UPDATED,
            'data' => new UserAddressResource($address),
        ]);
    }

    /**
     * Remove (soft delete) um endereço do usuário.
     * DELETE /api/v1/addresses/{address}
     */
    public function destroy(Request $request, UserAddress $address)
    {
        $this->authorize('delete', $address);

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => ApiMessages::ADDRESS_DELETED,
        ]);
    }

    /**
     * Define um endereço como principal.
     * PATCH /api/v1/addresses/{address}/primary
     */
    public function setPrimary(Request $request, UserAddress $address)
    {
        $this->authorize('setPrimary', $address);

        // Remove primary de todos os outros
        UserAddress::where('user_id', $request->user()->id)
            ->update(['is_primary' => false]);

        $address->update(['is_primary' => true]);

        return response()->json([
            'success' => true,
            'message' => ApiMessages::ADDRESS_PRIMARY_SET,
            'data' => new UserAddressResource($address),
        ]);
    }
}
