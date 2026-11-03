<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    public function current(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $shift = Shift::with(['user', 'cashMovements'])
            ->where('outlet_id', $outletId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        return response()->json(['data' => $shift]);
    }

    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opening_cash' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $existing = Shift::where('outlet_id', $outletId)
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'shift' => ['Shift Anda masih terbuka. Silakan tutup shift terlebih dahulu.'],
            ]);
        }

        $shift = Shift::create([
            'outlet_id' => $outletId,
            'user_id' => $request->user()->id,
            'opened_at' => now(),
            'opening_cash' => $validated['opening_cash'],
            'expected_cash' => $validated['opening_cash'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'open',
        ]);

        return response()->json(['data' => $shift], 201);
    }

    public function storeCashMovement(Request $request, Shift $shift): JsonResponse
    {
        if ($shift->status !== 'open') {
            throw ValidationException::withMessages([
                'shift' => ['Shift sudah ditutup.'],
            ]);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:in,out'],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $movement = CashMovement::create([
            'outlet_id' => $shift->outlet_id,
            'shift_id' => $shift->id,
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'reason' => $validated['reason'],
        ]);

        return response()->json(['data' => $movement], 201);
    }

    public function close(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'actual_cash' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $shift = isset($validated['shift_id'])
            ? Shift::findOrFail($validated['shift_id'])
            : Shift::where('outlet_id', $outletId)->where('status', 'open')->latest('opened_at')->firstOrFail();

        $cashPayments = Payment::where('shift_id', $shift->id)
            ->whereHas('paymentMethod', fn ($q) => $q->where('type', 'cash'))
            ->sum('amount');

        $cashRefunds = Refund::where('shift_id', $shift->id)
            ->whereHas('payment.paymentMethod', fn ($q) => $q->where('type', 'cash'))
            ->sum('amount');

        $cashIn = CashMovement::where('shift_id', $shift->id)->where('type', 'in')->sum('amount');
        $cashOut = CashMovement::where('shift_id', $shift->id)->where('type', 'out')->sum('amount');

        $expectedCash = (int) $shift->opening_cash + (int) $cashPayments - (int) $cashRefunds + (int) $cashIn - (int) $cashOut;
        $actualCash = (int) $validated['actual_cash'];
        $discrepancy = $actualCash - $expectedCash;

        $shift->update([
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'discrepancy' => $discrepancy,
            'notes' => $validated['notes'] ?? $shift->notes,
            'status' => 'closed',
        ]);

        return response()->json([
            'data' => $shift->fresh(['user', 'cashMovements']),
        ]);
    }
}
