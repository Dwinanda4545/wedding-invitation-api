<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsappDeviceStoreRequest;
use App\Http\Requests\WhatsappDeviceUpdateRequest;
use App\Models\WhatsappDevice;

class WhatsappDeviceController extends Controller
{
    public function index()
    {
        $devices = WhatsappDevice::query()->orderBy('name')->get()
            ->map(fn (WhatsappDevice $device) => $this->payload($device));

        return response()->json(['data' => $devices]);
    }

    public function store(WhatsappDeviceStoreRequest $request)
    {
        $data = $request->validated();
        if (! array_key_exists('is_active', $data)) {
            $data['is_active'] = true;
        }

        $device = WhatsappDevice::query()->create($data);

        return response()->json(['data' => $this->payload($device)], 201);
    }

    public function show(WhatsappDevice $whatsappDevice)
    {
        return response()->json(['data' => $this->payload($whatsappDevice)]);
    }

    public function update(WhatsappDeviceUpdateRequest $request, WhatsappDevice $whatsappDevice)
    {
        $whatsappDevice->update($request->validated());

        return response()->json(['data' => $this->payload($whatsappDevice->fresh())]);
    }

    public function destroy(WhatsappDevice $whatsappDevice)
    {
        if ($whatsappDevice->events()->exists() || $whatsappDevice->invitationSends()->exists()) {
            return response()->json([
                'message' => 'Device is in use. Deactivate it instead of deleting.',
            ], 422);
        }

        $whatsappDevice->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(WhatsappDevice $device): array
    {
        return [
            'id' => $device->id,
            'name' => $device->name,
            'provider_device_id' => $device->provider_device_id,
            'phone_label' => $device->phone_label,
            'is_active' => $device->is_active,
            'created_at' => $device->created_at,
            'updated_at' => $device->updated_at,
        ];
    }
}
