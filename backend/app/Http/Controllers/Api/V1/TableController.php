<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\DiningTable;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableController extends Controller
{
    public function areas(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;
        $areas = Area::with('tables')->where('outlet_id', $outletId)->orderBy('sort_order')->get();

        return response()->json(['data' => $areas]);
    }

    public function storeArea(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $area = Area::create([
            'outlet_id' => $outletId,
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $area], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $tables = DiningTable::with('area')
            ->where('outlet_id', $outletId)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $tables]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'name' => ['required', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'pos_x' => ['nullable', 'integer'],
            'pos_y' => ['nullable', 'integer'],
        ]);

        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $table = DiningTable::create([
            'outlet_id' => $outletId,
            'area_id' => $validated['area_id'],
            'name' => $validated['name'],
            'capacity' => $validated['capacity'] ?? 4,
            'pos_x' => $validated['pos_x'] ?? 0,
            'pos_y' => $validated['pos_y'] ?? 0,
            'status' => 'available',
        ]);

        return response()->json(['data' => $table], 201);
    }

    public function update(Request $request, DiningTable $table): JsonResponse
    {
        $validated = $request->validate([
            'area_id' => ['sometimes', 'integer', 'exists:areas,id'],
            'name' => ['sometimes', 'string', 'max:50'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'pos_x' => ['sometimes', 'integer'],
            'pos_y' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string', 'in:available,occupied,bill_requested,cleaning'],
        ]);

        $table->update($validated);

        return response()->json(['data' => $table]);
    }

    public function move(Request $request, DiningTable $table): JsonResponse
    {
        $validated = $request->validate([
            'target_table_id' => ['required', 'integer', 'exists:tables,id'],
        ]);

        $targetTable = DiningTable::findOrFail($validated['target_table_id']);

        DB::transaction(function () use ($table, $targetTable) {
            Order::where('table_id', $table->id)
                ->whereIn('status', ['open', 'held'])
                ->update(['table_id' => $targetTable->id]);

            $targetTable->update(['status' => 'occupied']);
            $table->update([
                'status' => 'available',
                'merged_into_table_id' => null,
            ]);
        });

        return response()->json([
            'source_table' => $table->fresh(),
            'target_table' => $targetTable->fresh(),
        ]);
    }

    public function merge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'primary_table_id' => ['required', 'integer', 'exists:tables,id'],
            'secondary_table_ids' => ['required', 'array', 'min:1'],
            'secondary_table_ids.*' => ['integer', 'exists:tables,id'],
        ]);

        $primary = DiningTable::findOrFail($validated['primary_table_id']);

        DB::transaction(function () use ($primary, $validated) {
            $primary->update(['status' => 'occupied']);

            DiningTable::whereIn('id', $validated['secondary_table_ids'])
                ->update([
                    'merged_into_table_id' => $primary->id,
                    'status' => 'occupied',
                ]);

            Order::whereIn('table_id', $validated['secondary_table_ids'])
                ->whereIn('status', ['open', 'held'])
                ->update(['table_id' => $primary->id]);
        });

        return response()->json([
            'primary_table' => $primary->fresh(),
            'secondary_tables' => DiningTable::whereIn('id', $validated['secondary_table_ids'])->get(),
        ]);
    }
}
