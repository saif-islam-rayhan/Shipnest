<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAddressRequest;
use App\Models\UserAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(Request $request): View
    {
        $addresses = $request->user()->addresses()->latest()->get();

        return view('account.addresses.index', compact('addresses'));
    }

    public function create(): View
    {
        return view('account.addresses.form', ['address' => new UserAddress]);
    }

    public function store(StoreAddressRequest $request): RedirectResponse
    {
        $this->saveAddress($request->user(), $request->validated());

        return redirect()
            ->route('account.addresses.index')
            ->with('success', 'Address added successfully.');
    }

    public function edit(Request $request, UserAddress $address): View
    {
        $this->authorizeAddress($request, $address);

        return view('account.addresses.form', compact('address'));
    }

    public function update(StoreAddressRequest $request, UserAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        $data = $request->validated();

        if ($data['is_default'] ?? false) {
            UserAddress::query()->where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        $address->update([
            'label' => $data['label'],
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'address_line1' => $data['address_line1'],
            'city' => $data['city'],
            'district' => $data['district'],
            'thana' => $data['thana'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'is_default' => $data['is_default'] ?? false,
        ]);

        return redirect()
            ->route('account.addresses.index')
            ->with('success', 'Address updated successfully.');
    }

    public function destroy(Request $request, UserAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        $address->delete();

        return redirect()
            ->route('account.addresses.index')
            ->with('success', 'Address deleted successfully.');
    }

    public function setDefault(Request $request, UserAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        UserAddress::query()->where('user_id', $request->user()->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('success', 'Default address updated.');
    }

    protected function saveAddress($user, array $data): UserAddress
    {
        if ($data['is_default'] ?? false) {
            UserAddress::query()->where('user_id', $user->id)->update(['is_default' => false]);
        }

        return UserAddress::query()->create([
            'user_id' => $user->id,
            'label' => $data['label'],
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'address_line1' => $data['address_line1'],
            'city' => $data['city'],
            'district' => $data['district'],
            'thana' => $data['thana'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'is_default' => $data['is_default'] ?? false,
        ]);
    }

    protected function authorizeAddress(Request $request, UserAddress $address): void
    {
        if ($address->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
