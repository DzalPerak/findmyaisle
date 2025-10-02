

<template>
  <div class="flex flex-col md:flex-row gap-8 w-full">
    <!-- Lists Section -->
    <div class="w-full md:w-1/3 bg-white/90 dark:bg-neutral-900/90 rounded-xl shadow p-6 h-fit">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold">My Shopping Lists</h2>
        <button @click="startNewList" class="bg-green-500 text-white px-3 py-1 rounded">Add List</button>
      </div>
      <div v-if="lists.length === 0" class="text-gray-500">No lists yet.</div>
      <ul class="divide-y">
        <li v-for="(list, idx) in lists" :key="list.id" class="flex items-center justify-between py-2">
          <span @click="selectList(idx)" class="cursor-pointer hover:underline">{{ list.name }}</span>
          <button @click="removeList(idx)" class="text-red-500">Remove</button>
        </li>
      </ul>
    </div>

    <!-- Selected List Details -->
    <div v-if="selectedList !== null" class="w-full md:w-2/3 bg-white/90 dark:bg-neutral-900/90 rounded-xl shadow p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold">{{ lists[selectedList].name }}</h3>
      </div>
      <ul class="divide-y">
        <li v-for="item in lists[selectedList].items" :key="item.id" class="flex items-center justify-between py-2">
          <span>{{ item.name }} <span v-if="item.qty > 1">x{{ item.qty }}</span></span>
          <button @click="removeItemFromList(selectedList, item.id)" class="text-red-500">Remove</button>
        </li>
      </ul>
      <button @click="editList(selectedList)" class="mt-4 bg-green-500 text-white px-3 py-1 rounded">Add Products</button>
    </div>

    <!-- Create/Edit List Modal -->
    <div v-if="editingList !== null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="w-full max-w-lg bg-white dark:bg-neutral-900 rounded-xl shadow-lg p-6 relative">
        <div class="flex items-center gap-2 mb-4">
          <input v-model="listName" placeholder="List name" class="border rounded p-2 flex-1" />
          <button @click="saveList" class="bg-blue-500 text-white px-3 py-1 rounded">Confirm</button>
          <button @click="cancelEdit" class="ml-2 text-gray-500">Cancel</button>
        </div>
        <input v-model="search" placeholder="Search products..." class="border rounded p-2 mb-4 w-full" />
        <div class="max-h-64 overflow-y-auto divide-y">
          <div v-for="product in filteredProducts" :key="product.id" class="flex items-center justify-between py-2">
            <span>{{ product.name }}</span>
            <div class="flex items-center gap-2">
              <button @click="decrement(product.id)" class="px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded">-</button>
              <span class="w-6 text-center">{{ quantities[product.id] || 0 }}</span>
              <button @click="increment(product.id)" class="px-2 py-1 bg-blue-500 text-white rounded">+</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const products = ref([]);
const search = ref('');
const quantities = ref<Record<number, number>>({});
const lists = ref<{id: number, name: string, items: {id: number, name: string, qty: number}[]}[]>([]);
const listName = ref('');
const editingList = ref<null | number | 'new'>(null);
const selectedList = ref<null | number>(null);
let nextListId = 1;

onMounted(async () => {
  await fetchProducts();
});

async function fetchProducts() {
  const res = await axios.get('/api/products');
  products.value = res.data;
}

const filteredProducts = computed(() => {
  if (!search.value) return products.value;
  return products.value.filter(p => p.name.toLowerCase().includes(search.value.toLowerCase()));
});

function increment(id: number) {
  quantities.value[id] = (quantities.value[id] || 0) + 1;
}
function decrement(id: number) {
  if (quantities.value[id]) quantities.value[id]--;
}

function startNewList() {
  listName.value = '';
  quantities.value = {};
  editingList.value = 'new';
}

function saveList() {
  const items = products.value
    .filter(p => (quantities.value[p.id] || 0) > 0)
    .map(p => ({ id: p.id, name: p.name, qty: quantities.value[p.id] }));
  if (!listName.value.trim() || items.length === 0) return;
  if (editingList.value === 'new') {
    lists.value.push({ id: nextListId++, name: listName.value, items });
    selectedList.value = lists.value.length - 1;
  } else if (typeof editingList.value === 'number') {
    const idx = editingList.value;
    lists.value[idx].name = listName.value;
    lists.value[idx].items = items;
    selectedList.value = idx;
  }
  editingList.value = null;
}

function selectList(idx: number) {
  selectedList.value = idx;
}

function removeList(idx: number) {
  lists.value.splice(idx, 1);
  if (selectedList.value === idx) selectedList.value = null;
}

function removeItemFromList(listIdx: number, itemId: number) {
  const list = lists.value[listIdx];
  list.items = list.items.filter(i => i.id !== itemId);
}

function editList(idx: number) {
  const list = lists.value[idx];
  listName.value = list.name;
  quantities.value = {};
  for (const item of list.items) {
    quantities.value[item.id] = item.qty;
  }
  editingList.value = idx;
}

function cancelEdit() {
  editingList.value = null;
}
</script>
