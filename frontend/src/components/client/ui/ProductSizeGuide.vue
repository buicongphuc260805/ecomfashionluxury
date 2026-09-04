<template>
  <div class="flex justify-center overflow-x-auto w-full">
    <table class="min-w-[600px] max-w-[800px] border-collapse text-center border border-gray-300 font-text text-[13px]">
      <thead>
        <tr class="bg-[#d4e6cc] text-gray-800 font-bold">
          <th colspan="3" class="border border-gray-300 py-3 text-[14px]">HƯỚNG DẪN CHỌN SIZE NAM</th>
        </tr>
        <tr class="bg-gray-100 font-bold text-gray-700">
          <th class="border border-gray-300 py-2.5 w-[33%]">Chiều cao</th>
          <th class="border border-gray-300 py-2.5 w-[33%]">Cân nặng</th>
          <th class="border border-gray-300 py-2.5 w-[34%]">Size</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, idx) in sizeTable" :key="idx" class="hover:bg-gray-50 transition-colors">
          <td class="border border-gray-300 py-2.5">{{ row.mHeight }}</td>
          <td class="border border-gray-300 py-2.5">{{ row.mWeight }}</td>
          <td class="border border-gray-300 py-2.5 font-bold">{{ row.mSize }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const sizeTable = ref([
  { mHeight: '1m60 - 1m65', mWeight: '52 - 58 kg', mSize: 'S (38)' },
  { mHeight: '1m65 - 1m70', mWeight: '59 - 65 kg', mSize: 'M (39)' },
  { mHeight: '1m70 - 1m75', mWeight: '66 - 72 kg', mSize: 'L (40)' },
  { mHeight: '1m75 - 1m80', mWeight: '73 - 79 kg', mSize: 'XL (41)' },
  { mHeight: '1m80 - 1m85', mWeight: '80 - 87 kg', mSize: 'XXL (42)' }
])
</script>
