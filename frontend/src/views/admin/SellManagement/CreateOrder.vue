<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Tạo Đơn Hàng Mới (POS)</h1>
        <p class="text-sm text-slate-500 mt-0.5">Tạo đơn bán hàng trực tiếp tại quầy hoặc tạo đơn đặt hàng thủ công cho khách</p>
      </div>
      <button 
        @click="$router.push('/admin/orders')" 
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:border-black text-slate-700 hover:text-black rounded-xl text-sm font-semibold shadow-sm transition-all active:scale-[0.98]"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Hủy & Quay lại
      </button>
    </div>

    <!-- Main Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- Cột Trái: Tìm kiếm & Danh sách sản phẩm trong đơn -->
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 space-y-6">
          <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <h2 class="text-base font-bold text-slate-800 flex items-center gap-2">
              <svg class="w-5 h-5 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
              </svg>
              Sản phẩm trong đơn
            </h2>
            <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-lg">
              {{ cart.length }} sản phẩm đã chọn
            </span>
          </div>
          
          <!-- Search Input for Products -->
          <div class="relative">
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Tìm & Thêm sản phẩm</label>
            <div class="relative flex items-center">
              <span class="absolute left-3.5 text-slate-400">
                <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
              </span>
              <input 
                v-model="searchQuery"
                @input="searchProducts"
                type="text" 
                placeholder="Nhập tên sản phẩm hoặc mã SKU..." 
                class="w-full pl-10 pr-4 py-3 text-sm border border-slate-200 rounded-xl text-slate-800 bg-slate-50/50 placeholder-slate-400 focus:bg-white focus:border-black focus:ring-4 focus:ring-slate-900/5 focus:outline-none transition-all font-medium"
              />
              <button 
                v-if="searchQuery" 
                @click="searchQuery = ''; searchResults = []" 
                class="absolute right-3 text-slate-400 hover:text-slate-600"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>

            <!-- Search Dropdown Results -->
            <Transition name="fade">
              <div 
                v-if="searchResults.length > 0 && searchQuery" 
                class="absolute z-30 w-full bg-white border border-slate-200 mt-2 max-h-80 overflow-y-auto rounded-2xl shadow-xl divide-y divide-slate-100"
              >
                <div 
                  v-for="prod in searchResults" 
                  :key="prod.id"
                  class="p-4 hover:bg-slate-50/80 transition-colors"
                >
                  <div class="font-bold text-slate-800 text-sm flex items-center justify-between">
                    <span>{{ prod.name }}</span>
                    <span class="text-xs text-slate-400 font-normal font-mono">ID: #{{ prod.id }}</span>
                  </div>
                  <div class="flex gap-2 mt-2.5 flex-wrap">
                    <button 
                      v-for="variant in prod.variants" 
                      :key="variant.id"
                      @click="addVariantToCart(prod, variant)"
                      :disabled="variant.stock_quantity <= 0"
                      class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl border transition-all cursor-pointer"
                      :class="variant.stock_quantity > 0 
                        ? 'bg-slate-100 border-slate-200 text-slate-700 hover:bg-black hover:text-white hover:border-black' 
                        : 'bg-rose-50 border-rose-100 text-rose-400 cursor-not-allowed opacity-60'"
                    >
                      <span>{{ getVariantName(variant) }}</span>
                      <span class="font-mono text-[11px] opacity-75">(Tồn: {{ variant.stock_quantity }})</span>
                    </button>
                  </div>
                </div>
              </div>
            </Transition>
          </div>

          <!-- Cart Products Table -->
          <div class="border border-slate-100 rounded-xl overflow-hidden shadow-2xs">
            <table class="w-full text-left border-collapse min-w-[500px]">
              <thead>
                <tr class="bg-slate-50/70 border-b border-slate-100 text-slate-500 text-xs font-bold uppercase tracking-wider">
                  <th class="py-3.5 px-4">Sản phẩm</th>
                  <th class="py-3.5 px-4 text-center w-32">Số lượng</th>
                  <th class="py-3.5 px-4 text-right w-32">Đơn giá</th>
                  <th class="py-3.5 px-4 text-right w-36">Thành tiền</th>
                  <th class="py-3.5 px-4 text-center w-12"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                <tr v-if="cart.length === 0">
                  <td colspan="5" class="py-12 text-center text-slate-400">
                    <div class="flex flex-col items-center justify-center gap-2">
                      <svg class="w-10 h-10 text-slate-300 stroke-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                      </svg>
                      <span class="text-sm font-medium">Chưa có sản phẩm nào được chọn.</span>
                      <span class="text-xs text-slate-400">Sử dụng ô tìm kiếm ở trên để thêm sản phẩm vào đơn.</span>
                    </div>
                  </td>
                </tr>

                <tr v-for="(item, index) in cart" :key="index" class="hover:bg-slate-50/50 transition-colors">
                  <td class="py-3.5 px-4">
                    <div class="font-bold text-slate-800 text-sm leading-snug">{{ item.product_name }}</div>
                    <div class="text-xs text-slate-400 font-mono mt-0.5 flex items-center gap-2">
                      <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-sans font-medium text-[11px]">{{ item.variant_name }}</span>
                    </div>
                  </td>
                  <td class="py-3.5 px-4 text-center">
                    <div class="flex items-center justify-center border border-slate-200 rounded-lg overflow-hidden w-24 mx-auto bg-white">
                      <button 
                        @click="item.quantity > 1 ? item.quantity-- : removeItem(index)" 
                        class="w-7 h-7 flex items-center justify-center text-slate-500 hover:bg-slate-100 transition-colors"
                      >-</button>
                      <input 
                        type="number" 
                        v-model.number="item.quantity" 
                        min="1" 
                        :max="item.max_stock" 
                        class="w-10 text-center font-mono font-bold text-slate-800 text-xs outline-none bg-transparent"
                      />
                      <button 
                        @click="item.quantity < item.max_stock ? item.quantity++ : toast.warning('Vượt quá kho!')" 
                        class="w-7 h-7 flex items-center justify-center text-slate-500 hover:bg-slate-100 transition-colors"
                      >+</button>
                    </div>
                  </td>
                  <td class="py-3.5 px-4 text-right font-semibold text-slate-700 font-mono text-sm">
                    {{ formatPrice(item.price) }}đ
                  </td>
                  <td class="py-3.5 px-4 text-right font-bold text-slate-900 font-mono text-sm">
                    {{ formatPrice(item.price * item.quantity) }}đ
                  </td>
                  <td class="py-3.5 px-4 text-center">
                    <button 
                      @click="removeItem(index)" 
                      class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                      title="Xóa khỏi đơn"
                    >
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>
                      </svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Cột Phải: Khách hàng & Tổng quan thanh toán -->
      <div class="space-y-6">
        
        <!-- Khách hàng -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 space-y-4">
          <h2 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
            Thông tin người nhận
          </h2>

          <div class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                Họ tên người nhận <span class="text-rose-500">*</span>
              </label>
              <input 
                v-model="order.shipping_name" 
                type="text" 
                :class="['w-full py-2.5 px-3.5 text-sm border rounded-xl text-slate-800 bg-slate-50/50 focus:bg-white focus:border-black focus:outline-none transition-all font-medium', errors.shipping_name ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200']" 
                placeholder="Nhập tên người nhận..."
              />
              <p v-if="errors.shipping_name" class="text-rose-500 text-xs font-medium mt-1">{{ errors.shipping_name }}</p>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                Số điện thoại <span class="text-rose-500">*</span>
              </label>
              <input 
                v-model="order.shipping_phone" 
                type="text" 
                :class="['w-full py-2.5 px-3.5 text-sm border rounded-xl text-slate-800 bg-slate-50/50 focus:bg-white focus:border-black focus:outline-none transition-all font-medium font-mono', errors.shipping_phone ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200']" 
                placeholder="Ví dụ: 0912345678"
              />
              <p v-if="errors.shipping_phone" class="text-rose-500 text-xs font-medium mt-1">{{ errors.shipping_phone }}</p>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                Địa chỉ giao hàng <span class="text-rose-500">*</span>
              </label>
              <textarea 
                v-model="order.shipping_address" 
                :class="['w-full py-2.5 px-3.5 text-sm border rounded-xl text-slate-800 bg-slate-50/50 focus:bg-white focus:border-black focus:outline-none transition-all font-medium', errors.shipping_address ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200']" 
                rows="3" 
                placeholder="Địa chỉ số nhà, tên đường, phường/xã, quận/huyện..."
              ></textarea>
              <p v-if="errors.shipping_address" class="text-rose-500 text-xs font-medium mt-1">{{ errors.shipping_address }}</p>
            </div>
          </div>
        </div>

        <!-- Thanh toán & Xác nhận -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 space-y-5">
          <h2 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
            </svg>
            Thanh toán & Đơn hàng
          </h2>
          
          <div class="space-y-2.5 text-xs text-slate-600">
            <div class="flex justify-between items-center">
              <span>Tổng tiền hàng:</span>
              <span class="font-mono font-bold text-slate-800 text-sm">{{ formatPrice(subTotal) }}đ</span>
            </div>
            
            <div class="flex justify-between items-center">
              <span>Phí vận chuyển:</span>
              <div class="flex items-center gap-1">
                <input v-model.number="order.shipping_fee" type="number" class="w-24 border border-slate-200 py-1 px-2 rounded-lg text-right font-mono font-semibold text-xs focus:border-black focus:outline-none">
                <span class="font-mono">đ</span>
              </div>
            </div>
            
            <div class="flex justify-between items-center text-rose-600">
              <span>Giảm giá thêm:</span>
              <div class="flex items-center gap-1">
                <input v-model.number="order.discount_amount" type="number" class="w-24 border border-rose-200 py-1 px-2 rounded-lg text-right font-mono font-semibold text-xs text-rose-600 focus:border-rose-500 focus:outline-none bg-rose-50/20">
                <span class="font-mono">đ</span>
              </div>
            </div>
          </div>

          <!-- Total summary card -->
          <div class="bg-slate-900 text-white p-4.5 rounded-xl flex justify-between items-center shadow-md">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-300">Khách phải trả:</span>
            <span class="font-mono font-extrabold text-xl tracking-tight text-white">{{ formatPrice(finalAmount) }}đ</span>
          </div>

          <div class="space-y-4 text-xs pt-1">
            <div>
              <label class="block font-bold text-slate-500 uppercase tracking-wider mb-1.5">Phương thức thanh toán</label>
              <select v-model="order.payment_method" class="w-full py-2.5 px-3 border border-slate-200 rounded-xl text-sm font-medium focus:border-black focus:outline-none bg-white">
                <option value="cash">Tiền mặt (tại quầy)</option>
                <option value="cod">Thanh toán khi nhận hàng (COD)</option>
                <option value="vnpay">Chuyển khoản / VNPAY</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-500 uppercase tracking-wider mb-1.5">Trạng thái thanh toán</label>
              <select v-model="order.payment_status" class="w-full py-2.5 px-3 border border-slate-200 rounded-xl text-sm font-medium focus:border-black focus:outline-none bg-white">
                <option value="paid">Đã thanh toán</option>
                <option value="unpaid">Chưa thanh toán</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-500 uppercase tracking-wider mb-1.5">Ghi chú đơn hàng</label>
              <textarea v-model="order.note" class="w-full py-2 px-3 border border-slate-200 rounded-xl text-sm font-medium focus:border-black focus:outline-none" rows="2" placeholder="Ghi chú thêm cho đơn hàng..."></textarea>
            </div>
          </div>

          <button 
            @click="submitOrder" 
            :disabled="isSubmitting || cart.length === 0"
            class="w-full py-3.5 bg-black hover:bg-neutral-800 text-white font-bold rounded-xl transition-all shadow-md flex items-center justify-center gap-2 uppercase tracking-wider text-xs disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]"
          >
            <svg v-if="isSubmitting" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
            <span>{{ isSubmitting ? 'Đang xử lý...' : 'Xác Nhận Tạo Đơn Hàng' }}</span>
          </button>
        </div>

      </div>
    </div>

    <!-- Custom Toast -->
    <Transition name="toast-fade">
      <div 
        v-if="toastState.show" 
        class="fixed bottom-6 right-6 px-5 py-3 rounded-xl shadow-xl flex items-center gap-2.5 z-50 text-sm font-semibold text-white transition-all"
        :class="toastState.type === 'error' ? 'bg-rose-600' : (toastState.type === 'warning' ? 'bg-amber-500' : 'bg-slate-800')"
      >
        <span>{{ toastState.message }}</span>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { productService } from '@/services/admin/productService'
import http from '@/services/shared/http'

const router = useRouter()

const toastState = ref({ show: false, message: '', type: 'success' })
let toastTimer = null

const showToast = (message, type = 'success') => {
  toastState.value = { show: true, message, type }
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toastState.value.show = false }, 3000)
}

const toast = {
  success: (msg) => showToast(msg, 'success'),
  error: (msg) => showToast(msg, 'error'),
  warning: (msg) => showToast(msg, 'warning')
}

const errors = ref({})

const searchQuery = ref('')
const searchResults = ref([])
const cart = ref([])
const isSubmitting = ref(false)

const order = ref({
  shipping_name: '',
  shipping_phone: '',
  shipping_address: '',
  shipping_fee: 0,
  discount_amount: 0,
  payment_method: 'cash',
  payment_status: 'paid',
  note: ''
})

const searchTimeout = ref(null)

const searchProducts = () => {
  clearTimeout(searchTimeout.value)
  if (!searchQuery.value) {
    searchResults.value = []
    return
  }
  searchTimeout.value = setTimeout(async () => {
    try {
      const res = await productService.getAll({ search: searchQuery.value, per_page: 5 })
      searchResults.value = res.data?.data || []
    } catch (e) {
      console.error(e)
    }
  }, 300)
}

const getVariantName = (variant) => {
  if (!variant.attribute_values || variant.attribute_values.length === 0) return variant.sku
  return variant.attribute_values.map(a => a.value).join(' - ')
}

const addVariantToCart = (prod, variant) => {
  if (variant.stock_quantity <= 0) return;

  const existing = cart.value.find(i => i.product_variant_id === variant.id)
  if (existing) {
    if (existing.quantity < variant.stock_quantity) {
      existing.quantity++
      toast.success('Đã tăng số lượng sản phẩm!')
    } else {
      toast.warning('Vượt quá số lượng tồn kho!')
    }
  } else {
    cart.value.push({
      product_variant_id: variant.id,
      product_name: prod.name,
      variant_name: getVariantName(variant),
      price: variant.sale_price || variant.price,
      quantity: 1,
      max_stock: variant.stock_quantity
    })
    toast.success('Đã thêm sản phẩm!')
  }
  searchQuery.value = ''
  searchResults.value = []
}

const removeItem = (index) => {
  cart.value.splice(index, 1)
}

const subTotal = computed(() => {
  return cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0)
})

const finalAmount = computed(() => {
  const total = subTotal.value + (order.value.shipping_fee || 0) - (order.value.discount_amount || 0)
  return total > 0 ? total : 0
})

const formatPrice = (value) => {
  return new Intl.NumberFormat('vi-VN').format(value)
}

const submitOrder = async () => {
  if (cart.value.length === 0) return toast.error('Giỏ hàng trống!')
  
  errors.value = {}
  if (!order.value.shipping_name) errors.value.shipping_name = 'Vui lòng nhập họ tên người nhận.'
  if (!order.value.shipping_phone) errors.value.shipping_phone = 'Vui lòng nhập số điện thoại.'
  if (!order.value.shipping_address) errors.value.shipping_address = 'Vui lòng nhập địa chỉ giao hàng.'
  
  if (Object.keys(errors.value).length > 0) {
    return toast.error('Vui lòng kiểm tra lại thông tin bắt buộc!')
  }

  isSubmitting.value = true
  try {
    const payload = {
      ...order.value,
      status: 'completed', 
      items: cart.value.map(i => ({
        product_variant_id: i.product_variant_id,
        quantity: i.quantity
      }))
    }

    if (payload.payment_method === 'cod') {
      payload.status = 'pending'
      payload.payment_status = 'unpaid'
    }

    await http.post('/admin/orders', payload)
    toast.success('Tạo đơn hàng thành công!')
    router.push('/admin/orders')
  } catch (err) {
    toast.error(err.response?.data?.message || 'Có lỗi xảy ra!')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

.toast-fade-enter-active, .toast-fade-leave-active {
  transition: all 0.3s ease;
}
.toast-fade-enter-from, .toast-fade-leave-to {
  opacity: 0;
  transform: translateY(20px);
}
</style>
