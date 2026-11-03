<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KitchenTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $query = KitchenTicket::with('order.table')
            ->where('outlet_id', $outletId);

        if ($request->filled('station')) {
            $query->where('station', $request->string('station'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'data' => $query->latest()->get(),
        ]);
    }

    public function updateStatus(Request $request, KitchenTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:new,processing,ready,served,cancelled'],
        ]);

        $ticket->update([
            'status' => $validated['status'],
            'completed_at' => in_array($validated['status'], ['ready', 'served'], true) ? now() : $ticket->completed_at,
        ]);

        return response()->json(['data' => $ticket]);
    }
}
