<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBuyerAddressRequest;
use App\Http\Requests\UpdateBuyerProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// Buyer profile & single-address CRUD (decision Ren 2026-11-06).
// Always resolves the acting buyer from Auth::guard('web')->user();
// never accepts user_id or email from request input.
class ProfileController extends Controller
{
    public function show(): View
    {
        $user = Auth::guard('web')->user();
        $address = $user->addresses()->orderBy('id')->first();

        return view('shop.profil-show', [
            'user' => $user,
            'address' => $address,
        ]);
    }

    public function edit(): View
    {
        $user = Auth::guard('web')->user();
        $address = $user->addresses()->orderBy('id')->first();

        return view('shop.profil-form', [
            'user' => $user,
            'address' => $address,
        ]);
    }

    public function update(UpdateBuyerProfileRequest $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $validated = $request->validated();

        $user->update([
            'name' => $validated['name'],
            'username' => $validated['username'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'birthdate' => $validated['birthdate'] ?? null,
        ]);

        return redirect()->route('profil.show')
            ->with('status', 'Profil berhasil diperbarui.');
    }

    public function updateAddress(UpdateBuyerAddressRequest $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $validated = $request->validated();

        $address = $user->addresses()->orderBy('id')->first();

        $attributes = [
            'recipient_name' => $validated['recipient_name'],
            'recipient_phone' => $validated['recipient_phone'] ?? null,
            'province' => $validated['province'],
            'city' => $validated['city'],
            'district' => $validated['district'],
            'village' => $validated['village'],
            'postal_code' => $validated['postal_code'],
            'street_address' => $validated['street_address'],
            'address_type' => $validated['address_type'],
        ];

        if ($address) {
            $address->update($attributes);
        } else {
            $user->addresses()->create($attributes);
        }

        return redirect()->route('profil.show')
            ->with('status', 'Alamat berhasil disimpan.');
    }
}
