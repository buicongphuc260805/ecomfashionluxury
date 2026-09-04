<?php

namespace App\Services\Admin\Implements;

use App\Models\GoodReceipt;
use App\Repositories\Admin\Interfaces\GoodReceiptRepoInterface;
use App\Repositories\Admin\Interfaces\ProductVariantRepositoryInterface;
use App\Services\Admin\Interfaces\GoodReceiptDetailServiceInterface;
use App\Services\Admin\Interfaces\GoodReceiptServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GoodReceiptService implements GoodReceiptServiceInterface
{
    public function __construct(
        private readonly GoodReceiptRepoInterface $repo,
        private readonly GoodReceiptDetailServiceInterface $good_receipt_detail_service,
        private readonly ProductVariantRepositoryInterface $product_variant_repo
    ) {}

    public function getList(array $filters): LengthAwarePaginator
    {
        return $this->repo->paginate($filters);
    }

    public function create(array $data): GoodReceipt
    {
        return DB::transaction(function () use ($data) {
            $details = $data['good_receipt_details'] ?? [];

            unset($data['good_receipt_details']);

            $created = $this->repo->create($data);
            if (! empty($details)) {
                $this->good_receipt_detail_service->insertMany($details, $created->id);

                if (($data['status'] ?? 'pending') === 'completed') {
                    foreach ($details as $detail) {
                        if (isset($detail['product_variant_id']) && isset($detail['quantity'])) {
                            $this->product_variant_repo->incrementStock($detail['product_variant_id'], $detail['quantity']);
                        }
                    }
                }
            }

            return $created->load(['goodReceiptDetail.productVariant.product']);
        });
    }

    public function update(Model $model, array $data): GoodReceipt
    {
        return DB::transaction(function () use ($model, $data) {
            $oldStatus = $model->status;
            $newStatus = $data['status'] ?? $oldStatus;

            // 1. Kiểm tra trạng thái đã đóng (Terminal statuses)
            if ($oldStatus === 'completed' && $newStatus !== 'completed') {
                throw new \Exception('Phiếu nhập đã hoàn thành (đã cộng tồn kho), không thể thay đổi trạng thái.');
            }

            if ($oldStatus === 'cancel' && $newStatus !== 'cancel') {
                throw new \Exception('Phiếu nhập đã bị hủy, không thể thay đổi trạng thái.');
            }

            // 2. Không cho phép lùi trạng thái từ đã duyệt về chờ duyệt
            if ($oldStatus === 'approved' && $newStatus === 'pending') {
                throw new \Exception('Phiếu nhập đã được duyệt, không thể quay lại trạng thái Chờ duyệt.');
            }

            $details = $data['good_receipt_details'] ?? [];

            unset($data['good_receipt_details']);

            $updated = $this->repo->update($model, $data);

            if (! empty($details)) {
                $this->good_receipt_detail_service->syncDetail($model, $details);
            }

            if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                $updated->load('goodReceiptDetail');
                foreach ($updated->goodReceiptDetail as $detail) {
                    if ($detail->product_variant_id) {
                        $this->product_variant_repo->incrementStock($detail->product_variant_id, $detail->quantity);
                    }
                }
            }

            return $updated->load(['goodReceiptDetail.productVariant.product']);
        });
    }

    public function delete(Model $model): void
    {
        $this->repo->delete($model);
    }

    public function getStats(): array
    {
        return $this->repo->getStats();
    }
}
