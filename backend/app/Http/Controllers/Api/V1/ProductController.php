<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ModifierGroup;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $query = Product::with(['category', 'variants', 'modifierGroups.modifiers', 'prices'])
            ->where('outlet_id', $outletId);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('favorites_only')) {
            $query->where('is_favorite', true);
        }

        return response()->json([
            'data' => $query->orderByDesc('is_favorite')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'integer', 'min:0'],
            'station' => ['required', 'string', 'in:bar,kitchen,pastry'],
            'is_available' => ['nullable', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
            'image_url' => ['nullable', 'string'],
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['required_with:variants', 'string'],
            'variants.*.type' => ['nullable', 'string'],
            'variants.*.price_adjustment' => ['nullable', 'integer'],
            'prices' => ['nullable', 'array'],
            'prices.*.order_type' => ['required_with:prices', 'string', 'in:dine_in,take_away,delivery,pre_order'],
            'prices.*.price' => ['required_with:prices', 'integer', 'min:0'],
            'modifier_group_ids' => ['nullable', 'array'],
            'modifier_group_ids.*' => ['integer', 'exists:modifier_groups,id'],
        ]);

        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $product = DB::transaction(function () use ($validated, $outletId) {
            $product = Product::create([
                'outlet_id' => $outletId,
                'category_id' => $validated['category_id'],
                'sku' => $validated['sku'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'base_price' => $validated['base_price'],
                'station' => $validated['station'],
                'is_available' => $validated['is_available'] ?? true,
                'is_favorite' => $validated['is_favorite'] ?? false,
                'image_url' => $validated['image_url'] ?? null,
            ]);

            foreach ($validated['variants'] ?? [] as $variant) {
                $product->variants()->create([
                    'name' => $variant['name'],
                    'type' => $variant['type'] ?? 'size',
                    'price_adjustment' => $variant['price_adjustment'] ?? 0,
                    'is_available' => $variant['is_available'] ?? true,
                ]);
            }

            foreach ($validated['prices'] ?? [] as $priceRow) {
                $product->prices()->create([
                    'order_type' => $priceRow['order_type'],
                    'price' => $priceRow['price'],
                ]);
            }

            if (! empty($validated['modifier_group_ids'])) {
                $product->modifierGroups()->sync($validated['modifier_group_ids']);
            }

            return $product->load(['category', 'variants', 'modifierGroups.modifiers', 'prices']);
        });

        return response()->json(['data' => $product], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => $product->load(['category', 'variants', 'modifierGroups.modifiers', 'prices']),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:64'],
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'base_price' => ['sometimes', 'integer', 'min:0'],
            'station' => ['sometimes', 'string', 'in:bar,kitchen,pastry'],
            'is_available' => ['nullable', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
            'image_url' => ['nullable', 'string'],
            'modifier_group_ids' => ['nullable', 'array'],
            'modifier_group_ids.*' => ['integer', 'exists:modifier_groups,id'],
        ]);

        $product->update($validated);

        if (array_key_exists('modifier_group_ids', $validated)) {
            $product->modifierGroups()->sync($validated['modifier_group_ids'] ?? []);
        }

        return response()->json([
            'data' => $product->load(['category', 'variants', 'modifierGroups.modifiers', 'prices']),
        ]);
    }

    public function updateAvailability(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'is_available' => ['required', 'boolean'],
        ]);

        $product->update(['is_available' => $validated['is_available']]);

        return response()->json(['data' => $product]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Produk dihapus.']);
    }

    public function modifierGroups(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;
        $groups = ModifierGroup::with('modifiers')->where('outlet_id', $outletId)->get();

        return response()->json(['data' => $groups]);
    }
}
