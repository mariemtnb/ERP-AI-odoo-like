<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\DrfPagination;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::withCount('products')->orderBy('name');
        if ($search = $request->query('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (Category $c) => $c->toApi())
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'description' => ['sometimes', 'string'],
        ]);

        return response()->json(Category::create($data)->toApi(), 201);
    }

    public function show(Category $category)
    {
        return response()->json($category->toApi());
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category->id)],
            'description' => ['sometimes', 'string'],
        ]);
        $category->update($data);

        return response()->json($category->toApi());
    }

    public function destroy(Category $category)
    {
        try {
            $category->delete();
        } catch (QueryException) {
            return response()->json(
                ['detail' => 'Cannot delete a category that still has products.'],
                409
            );
        }

        return response()->json(null, 204);
    }
}
