<?php

namespace App\Services\Client\Implements;

use Illuminate\Support\Facades\Http;

class ShippingService
{
    private string $baseUrl;

    private string $token;

    private int $shopId;

    private int $fromDistrictId;

    private string $fromWardCode;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ghn.url'), '/');
        $this->token = config('services.ghn.token');
        $this->shopId = (int) config('services.ghn.shop_id');
        $this->fromDistrictId = (int) config('services.ghn.from_district_id', 1454);
        $this->fromWardCode = (string) config('services.ghn.from_ward_code', '21211');
    }

    /** Lấy danh sách tỉnh/thành từ GHN */
    public function getProvinces(): array
    {
        $res = Http::withHeaders(['token' => $this->token])
            ->get("{$this->baseUrl}/master-data/province");

        return $res->successful() ? ($res->json('data') ?? []) : [];
    }

    /** Lấy danh sách quận/huyện theo tỉnh */
    public function getDistricts(int $provinceId): array
    {
        $res = Http::withHeaders(['token' => $this->token])
            ->get("{$this->baseUrl}/master-data/district", [
                'province_id' => $provinceId,
            ]);

        return $res->successful() ? ($res->json('data') ?? []) : [];
    }

    /** Lấy danh sách phường/xã theo quận */
    public function getWards(int $districtId): array
    {
        $res = Http::withHeaders(['token' => $this->token])
            ->get("{$this->baseUrl}/master-data/ward", [
                'district_id' => $districtId,
            ]);

        return $res->successful() ? ($res->json('data') ?? []) : [];
    }

    /** Lấy dịch vụ vận chuyển khả dụng */
    public function getAvailableServices(int $toDistrictId): array
    {
        $res = Http::withHeaders(['token' => $this->token])
            ->post("{$this->baseUrl}/v2/shipping-order/available-services", [
                'shop_id' => $this->shopId,
                'from_district' => $this->fromDistrictId,
                'to_district' => $toDistrictId,
            ]);

        return $res->successful() ? ($res->json('data') ?? []) : [];
    }

    /** Tính phí vận chuyển theo GHN */
    public function calculateFee(int $districtId, string $wardCode, int $serviceId, int $weightGram = 500): array
    {
        $payload = [
            'service_id' => $serviceId,
            'from_district_id' => $this->fromDistrictId,
            'from_ward_code' => $this->fromWardCode,
            'to_district_id' => $districtId,
            'to_ward_code' => $wardCode,
            'weight' => $weightGram,
            'length' => 20,
            'width' => 15,
            'height' => 5,
        ];

        $res = Http::withHeaders([
            'token' => $this->token,
            'shop_id' => $this->shopId,
        ])->post("{$this->baseUrl}/v2/shipping-order/fee", $payload);

        if ($res->successful() && isset($res->json('data')['total'])) {
            return [
                'success' => true,
                'total' => $res->json('data.total'),
                'service_fee' => $res->json('data.service_fee'),
            ];
        }

        return [
            'success' => false,
            'message' => $res->json('message') ?? 'Không thể tính phí vận chuyển',
        ];
    }
}
