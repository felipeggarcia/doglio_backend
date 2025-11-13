<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Requests\V1\Admin\CategoryStoreRequest;
use App\Http\Requests\V1\Admin\CategoryUpdateRequest;

class CategoryController extends Controller
{
    /**
     * Define a autorização no construtor. 
     * Apenas usuários com role 'admin' podem acessar o CRUD.
     */
    public function __construct()
    {
        // Garante que APENAS admins podem usar este controller
        $this->middleware('auth:sanctum')->except('index', 'show');
        $this->middleware(function ($request, $next) {
            if ($request->user() && $request->user()->role !== 'admin') {
                return response()->json(['message' => 'Acesso negado. Apenas administradores podem gerenciar categorias.'], 403);
            }
            return $next($request);
        })->except('index', 'show'); // Checagem para Admin em store, update, destroy
    }

    /**
     * READ: Lista todas as categorias, incluindo as que não estão em destaque.
     */
    public function index()
    {
        $categories = Category::all();
        // Usamos o CategoryResource que você já fez!
        return CategoryResource::collection($categories);
    }

    /**
     * CREATE: Cria uma nova categoria.
     */
    public function store(CategoryStoreRequest $request)
    {
        $category = Category::create($request->validated() + [
            'slug' => \Str::slug($request->name)
        ]);
        
        return new CategoryResource($category);
    }

    /**
     * READ: Mostra detalhes de uma categoria específica.
     */
    public function show(Category $category)
    {
        return new CategoryResource($category);
    }

    /**
     * UPDATE: Atualiza uma categoria existente.
     */
    public function update(CategoryUpdateRequest $request, Category $category)
    {
        $data = $request->validated();
        
        // Atualiza o slug se o nome foi alterado
        if (isset($data['name'])) {
            $data['slug'] = \Str::slug($data['name']);
        }

        $category->update($data);

        return new CategoryResource($category);
    }

    /**
     * DELETE: Deleta uma categoria.
     */
    public function destroy(Category $category)
    {
        // O MySQL/Laravel vai cuidar de remover as referências Many-to-Many automaticamente (cascade)
        $category->delete();

        return response()->json(null, 204);
    }
}
