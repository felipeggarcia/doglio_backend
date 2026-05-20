<?php

namespace App\Http\Controllers\V1;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Rules\ValidCpfCnpj;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Support\ApiMessages;

class UserController extends Controller
{
    /**
     * Display a listing of users (Admin only)
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Filtro por role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filtro por is_active
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Busca por nome ou email
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $users = $query->paginate($request->get('per_page', 15));

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user (Admin only)
     */
    public function store(Request $request)
    {
        if ($request->filled('cpf_cnpj')) {
            $request->merge(['cpf_cnpj' => preg_replace('/\D/', '', $request->cpf_cnpj)]);
        }

        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'password'   => 'required|string|min:8',
            'role'       => 'required|in:admin,customer',
            'city'       => 'nullable|string|max:255',
            'state'      => 'nullable|string|max:2',
            'cpf_cnpj'   => ['nullable', 'string', 'unique:users,cpf_cnpj', new ValidCpfCnpj()],
            'birth_date' => 'nullable|date|before:today',
            'is_active'  => 'boolean',
        ]);

        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role'       => $request->role,
            'city'       => $request->city,
            'state'      => $request->state,
            'cpf_cnpj'   => $request->cpf_cnpj, // já normalizado
            'birth_date' => $request->birth_date,
            'is_active'  => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => ApiMessages::USER_CREATED,
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Display the specified user (Admin only)
     */
    public function show(User $user)
    {
        return new UserResource($user);
    }

    /**
     * Update the specified user (Admin only)
     */
    public function update(Request $request, User $user)
    {
        if ($request->filled('cpf_cnpj')) {
            $request->merge(['cpf_cnpj' => preg_replace('/\D/', '', $request->cpf_cnpj)]);
        }

        $request->validate([
            'name'       => 'sometimes|string|max:255',
            'email'      => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password'   => 'sometimes|string|min:8',
            'role'       => 'sometimes|in:admin,customer',
            'city'       => 'nullable|string|max:255',
            'state'      => 'nullable|string|max:2',
            'cpf_cnpj'   => ['nullable', 'string', Rule::unique('users', 'cpf_cnpj')->ignore($user->id), new ValidCpfCnpj()],
            'birth_date' => 'nullable|date|before:today',
            'is_active'  => 'boolean',
        ]);

        $data = $request->only(['name', 'email', 'role', 'city', 'state', 'birth_date', 'is_active']);

        if ($request->has('cpf_cnpj')) {
            $data['cpf_cnpj'] = $request->cpf_cnpj; // já normalizado
        }

        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => ApiMessages::USER_UPDATED,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Remove the specified user (Admin only)
     */
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => ApiMessages::USER_DELETED,
        ]);
    }
}
