<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ManagerPinService
{
    public function verifyManagerPin(int $outletId, string $pin): ?User
    {
        $managers = User::where('is_active', true)
            ->where(function ($query) use ($outletId) {
                $query->where('outlet_id', $outletId)
                    ->orWhereNull('outlet_id');
            })
            ->whereNotNull('pin')
            ->get();

        foreach ($managers as $user) {
            if ($user->hasAnyRole(['owner', 'manager']) && Hash::check($pin, $user->pin)) {
                return $user;
            }
        }

        return null;
    }
}
