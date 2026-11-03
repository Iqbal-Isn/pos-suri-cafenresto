<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Printer;
use App\Models\ReceiptTemplate;
use App\Models\Setting;
use App\Models\TaxRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        return response()->json([
            'outlet' => Outlet::find($outletId),
            'tax_rule' => TaxRule::where('outlet_id', $outletId)->where('is_active', true)->first(),
            'receipt_template' => ReceiptTemplate::where('outlet_id', $outletId)->first(),
            'settings' => Setting::where('outlet_id', $outletId)->pluck('value', 'key'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $validated = $request->validate([
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_inclusive' => ['nullable', 'boolean'],
            'service_charge_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rounding_multiple' => ['nullable', 'integer', 'min:0'],
            'discount_approval_threshold' => ['nullable', 'integer', 'min:0'],
            'header_text' => ['nullable', 'string'],
            'footer_text' => ['nullable', 'string'],
        ]);

        $taxRule = TaxRule::firstOrCreate(
            ['outlet_id' => $outletId, 'is_active' => true],
            ['name' => 'PB1', 'rate' => 10, 'service_charge_rate' => 5, 'rounding_multiple' => 100]
        );

        $taxRule->update(array_filter([
            'rate' => $validated['tax_rate'] ?? $taxRule->rate,
            'is_inclusive' => $validated['tax_inclusive'] ?? $taxRule->is_inclusive,
            'service_charge_rate' => $validated['service_charge_rate'] ?? $taxRule->service_charge_rate,
            'rounding_multiple' => $validated['rounding_multiple'] ?? $taxRule->rounding_multiple,
            'discount_approval_threshold' => $validated['discount_approval_threshold'] ?? $taxRule->discount_approval_threshold,
        ], fn ($v) => ! is_null($v)));

        if (isset($validated['header_text']) || isset($validated['footer_text'])) {
            ReceiptTemplate::updateOrCreate(
                ['outlet_id' => $outletId],
                [
                    'header_text' => $validated['header_text'] ?? null,
                    'footer_text' => $validated['footer_text'] ?? null,
                ]
            );
        }

        return $this->index($request);
    }

    public function printers(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        return response()->json([
            'data' => Printer::where('outlet_id', $outletId)->get(),
        ]);
    }

    public function storePrinter(Request $request): JsonResponse
    {
        $outletId = $request->integer('outlet_id') ?: $request->user()->outlet_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'station' => ['required', 'string', 'in:cashier,bar,kitchen,pastry'],
            'connection_type' => ['required', 'string', 'in:usb,lan,bluetooth,agent'],
            'address' => ['nullable', 'string', 'max:255'],
            'paper_width' => ['nullable', 'integer', 'in:58,80'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $printer = Printer::create([
            'outlet_id' => $outletId,
            'name' => $validated['name'],
            'station' => $validated['station'],
            'connection_type' => $validated['connection_type'],
            'address' => $validated['address'] ?? null,
            'paper_width' => $validated['paper_width'] ?? 80,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $printer], 201);
    }
}
