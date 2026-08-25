<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Lightweight JSON listing used for the category picker (with optional search).
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('q')->toString();

        $categories = Category::query()
            ->when($search, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        return response()->json($categories);
    }

    /**
     * Quick-create a category from the picker when it doesn't exist yet.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
        ]);

        $category = Category::create($data);

        return response()->json($category, 201);
    }
}
