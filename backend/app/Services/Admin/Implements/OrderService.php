<?php

namespace App\Services\Admin\Implements;

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Repositories\Admin\Interfaces\OrderRepositoryInterface;
use App\Services\Admin\Interfaces\OrderServiceInterface;
use App\Services\Client\Interfaces\NotificationServiceInterface;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService implements OrderServiceInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly NotificationServiceInterface $notificationService
    ) {}

    public function getList(array $filters): LengthAwarePaginator
    {
        return $this->orderRepository->paginate($filters);
    }

    public function getStats(): array
    {
        return $this->orderRepository->getStats();
    }

    public function getDetail(int $id): ?Order
    {
        return $this->orderRepository->findByIdWithRelations($id);
    }

    public function create(array $data): Model
    {
        return $this->orderRepository->create($data);
    }

    public function createOrder(array $data): Order
    {
        try {
            DB::beginTransaction();

            $orderCode = 'ORD-'.strtoupper(Str::random(8));

            $subTotal = 0;
            $itemsData = [];

            foreach ($data['items'] as $item) {
                $variant = ProductVariant::findOrFail($item['product_variant_id']);

                if ($variant->stock_quantity < $item['quantity']) {
                    throw new Exception("Sản phẩm '{$variant->sku}' không đủ tồn kho (còn {$variant->stock_quantity}).");
                }

                $price = $variant->sale_price ?: $variant->price;
                $subTotal += $price * $item['quantity'];

                $itemsData[] = [
                    'product_variant_id' => $variant->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $price,
                    'cost_price' => $variant->cost_price ?? 0,
                ];

                // Trừ kho
                $variant->decrement('stock_quantity', $item['quantity']);
            }

            $shippingFee = $data['shipping_fee'] ?? 0;
            $discountAmount = $data['discount_amount'] ?? 0;
            $finalAmount = max(0, $subTotal + $shippingFee - $discountAmount);

            // Xử lý address / name cho guest
            $shippingName = $data['shipping_name'] ?? null;
            $shippingPhone = $data['shipping_phone'] ?? null;
            $shippingAddress = $data['shipping_address'] ?? null;

            if (! empty($data['customer_id'])) {
                $customer = Customer::find($data['customer_id']);
                if ($customer && empty($shippingName)) {
                    $shippingName = $customer->name; // Note: Customer uses first_name/last_name? We'll see.
                    $shippingPhone = $customer->phone_number;
                }
                $customerId = $data['customer_id'];
            } else {
                // Tự động tạo hoặc lấy Khách lẻ (Guest) để pass qua validation DB
                $guestCustomer = Customer::firstOrCreate(
                    ['email' => 'khachle@ecomfashion.com'],
                    [
                        'first_name' => 'Khách',
                        'last_name' => 'Lẻ (POS)',
                        'phone_number' => '0000000000',
                        'password' => bcrypt(Str::random(16)),
                        'status' => 1, // Int instead of string
                    ]
                );
                $customerId = $guestCustomer->id;
            }

            $orderData = [
                'order_code' => $orderCode,
                'customer_id' => $customerId,
                'shipping_name' => $shippingName,
                'shipping_phone' => $shippingPhone,
                'shipping_address' => $shippingAddress,
                'shipping_fee' => $shippingFee,
                'coupon_discount_amount' => $discountAmount,
                'sub_total_amount' => $subTotal,
                'final_amount' => $finalAmount,
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_status'],
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
            ];

            $order = $this->orderRepository->create($orderData);

            // Insert Order Details
            foreach ($itemsData as $itemData) {
                $order->details()->create($itemData);
            }

            DB::commit();

            return $order->load('details.productVariant.product');

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(Model $model, array $data): Order
    {
        try {
            DB::beginTransaction();

            $oldStatus = $model->status;
            $newStatus = $data['status'] ?? $oldStatus;
            $oldPaymentStatus = $model->payment_status;
            $newPaymentStatus = $data['payment_status'] ?? $oldPaymentStatus;

            // 1. Kiểm tra ma trận chuyển trạng thái Đơn hàng (Strict Sequential State Machine)
            $allowedStatusTransitions = [
                'pending'   => ['pending', 'confirmed', 'cancelled'],
                'confirmed' => ['confirmed', 'shipping', 'cancelled'],
                'shipping'  => ['shipping', 'completed', 'cancelled'],
                'completed' => ['completed'], // Trạng thái cuối: Khóa hoàn toàn
                'cancelled' => ['cancelled'], // Trạng thái cuối: Khóa hoàn toàn
            ];

            if (isset($allowedStatusTransitions[$oldStatus])) {
                if (! in_array($newStatus, $allowedStatusTransitions[$oldStatus])) {
                    $statusNames = [
                        'pending'   => 'Chờ xử lý',
                        'confirmed' => 'Đã xác nhận',
                        'shipping'  => 'Đang giao hàng',
                        'completed' => 'Đã giao hàng (Hoàn thành)',
                        'cancelled' => 'Đã hủy',
                    ];
                    $oldName = $statusNames[$oldStatus] ?? $oldStatus;
                    $newName = $statusNames[$newStatus] ?? $newStatus;
                    throw new Exception("Không thể chuyển đơn hàng từ '{$oldName}' sang '{$newName}'. Vui lòng tuân thủ quy trình xử lý theo đúng thứ tự (Chờ xử lý ➔ Đã xác nhận ➔ Đang giao ➔ Đã giao).");
                }
            }

            // 2. Tự động & Ràng buộc trạng thái thanh toán (Payment Status Rules)
            // - Đơn chuyển sang completed -> tự động cập nhật payment_status = 'paid'
            if ($newStatus === 'completed') {
                $data['payment_status'] = 'paid';
                $newPaymentStatus = 'paid';
            }

            // - Đơn đã hoàn thành & đã thanh toán -> Không cho chuyển về 'unpaid'
            if ($oldStatus === 'completed' && $oldPaymentStatus === 'paid' && $newPaymentStatus === 'unpaid') {
                throw new Exception("Đơn hàng đã hoàn thành và thanh toán, không thể chuyển về trạng thái 'Chưa thanh toán'.");
            }

            // - Đơn thanh toán VNPAY đã paid -> Không cho chuyển về 'unpaid'
            if ($model->payment_method === 'vnpay' && $oldPaymentStatus === 'paid' && $newPaymentStatus === 'unpaid') {
                throw new Exception("Đơn hàng đã thanh toán online thành công qua VNPAY, không thể chuyển về 'Chưa thanh toán'.");
            }

            // - Đơn đã bị Hủy (cancelled) -> KHÓA THÔNG TIN THANH TOÁN, không được đổi sang 'paid'
            if ($oldStatus === 'cancelled' && $newPaymentStatus === 'paid') {
                throw new Exception("Đơn hàng đã bị Hủy, không thể thay đổi trạng thái thanh toán thành 'Đã thanh toán'.");
            }

            // - Trạng thái 'refunded' (Đã hoàn tiền) chỉ cho phép khi đơn hàng bị 'cancelled'
            if ($newPaymentStatus === 'refunded' && $newStatus !== 'cancelled') {
                throw new Exception("Trạng thái 'Đã hoàn tiền' chỉ áp dụng đối với các đơn hàng đã bị Hủy.");
            }

            // Load details for stock logic if not loaded
            $model->load('details.productVariant');

            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                foreach ($model->details as $detail) {
                    if ($detail->productVariant) {
                        $detail->productVariant->increment('stock_quantity', $detail->quantity);
                    }
                }

                if ($model->coupon_id && $model->customer_id) {
                    $model->load('coupon');
                    if ($model->coupon) {
                        $model->coupon->decrement('used_count');
                        DB::table('customer_coupons')
                            ->where('customer_id', $model->customer_id)
                            ->where('coupon_id', $model->coupon_id)
                            ->delete();
                    }
                }
            } elseif ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
                foreach ($model->details as $detail) {
                    if ($detail->productVariant) {
                        if ($detail->productVariant->stock_quantity < $detail->quantity) {
                            throw new Exception("Sản phẩm '{$detail->productVariant->sku}' không đủ tồn kho để khôi phục đơn hàng.");
                        }
                        $detail->productVariant->decrement('stock_quantity', $detail->quantity);
                    }
                }
            }

            $updated = $this->orderRepository->update($model, $data);

            if ($newStatus !== $oldStatus && $updated->customer_id) {
                $statusMap = [
                    'confirmed' => 'Đã xác nhận',
                    'shipping' => 'Đang giao hàng',
                    'completed' => 'Đã giao thành công',
                    'cancelled' => 'Đã hủy',
                ];

                if (isset($statusMap[$newStatus])) {
                    $this->notificationService->notify(
                        $updated->customer_id,
                        'order_status_updated',
                        "Đơn hàng {$updated->order_code} ".strtolower($statusMap[$newStatus]),
                        "Trạng thái đơn hàng {$updated->order_code} của bạn đã được cập nhật thành: {$statusMap[$newStatus]}."
                    );
                }
            }

            $updated->load([
                'customer',
                'coupon',
                'details.productVariant.product',
                'details.productVariant.attributeValues.attribute',
            ]);

            DB::commit();

            return $updated;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function delete(Model $model): void
    {
        $this->orderRepository->delete($model);
    }
}
