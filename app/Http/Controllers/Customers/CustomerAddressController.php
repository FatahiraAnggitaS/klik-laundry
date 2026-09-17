<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CustomerAddressActionRequest;
use App\Http\Requests\Customers\CustomerAddressRequest;
use App\Services\Customers\GetCustomerAddressesService;
use App\Services\Customers\ManageCustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerAddressController extends Controller
{
    public function __construct(
        private readonly GetCustomerAddressesService $query,
        private readonly ManageCustomerAddressService $commands,
    ) {}

    public function index(CustomerAddressActionRequest $request): Response
    {
        return Inertia::render('customer/addresses', $this->query->handle($request->identity()));
    }

    public function store(CustomerAddressRequest $request): RedirectResponse
    {
        $this->commands->create($request->identity(), $request->toDto());

        return back()->with('status', 'Alamat berhasil disimpan.');
    }

    public function update(CustomerAddressRequest $request, string $address): RedirectResponse
    {
        $this->commands->update($request->identity(), $address, $request->toDto());

        return back()->with('status', 'Alamat berhasil diperbarui.');
    }

    public function destroy(CustomerAddressActionRequest $request, string $address): RedirectResponse
    {
        $this->commands->delete($request->identity(), $address);

        return back()->with('status', 'Alamat berhasil dihapus.');
    }

    public function makeDefault(CustomerAddressActionRequest $request, string $address): RedirectResponse
    {
        $this->commands->makeDefault($request->identity(), $address);

        return back()->with('status', 'Alamat default berhasil diperbarui.');
    }
}
