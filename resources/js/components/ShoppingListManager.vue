

<template>
  <div class="min-h-screen not-dark:bg-gray-50 dark:bg-gray-900 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-4xl font-bold not-dark:text-gray-900 dark:text-white mb-4">Shopping List Manager</h1>
        <p class="text-lg not-dark:text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
          Create and manage your shopping lists with ease
        </p>
      </div>
      
      <div class="flex flex-col lg:flex-row gap-8 w-full">
        <!-- Lists Section -->
        <div class="w-full lg:w-1/3 not-dark:bg-white dark:bg-gray-800 rounded-xl shadow-lg border not-dark:border-gray-200 dark:border-gray-700 p-6 h-fit">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold not-dark:text-gray-900 dark:text-white">My Shopping Lists</h2>
            <button @click="startNewList" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200">
              Add List
            </button>
          </div>
          <div v-if="lists.length === 0" class="text-center py-8">
            <p class="not-dark:text-gray-500 dark:text-gray-400">No lists yet.</p>
          </div>
          <ul class="space-y-3">
            <li v-for="(list, idx) in lists" :key="list.id" 
                class="flex items-center justify-between p-3 not-dark:bg-gray-50 dark:bg-gray-700 rounded-lg border not-dark:border-gray-200 dark:border-gray-600">
              <span @click="selectList(idx)" 
                    class="cursor-pointer hover:text-blue-600 dark:hover:text-blue-400 font-medium not-dark:text-gray-900 dark:text-white transition-colors">
                {{ list.name }}
              </span>
              <button @click="removeList(idx)" 
                      class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium transition-colors">
                Remove
              </button>
            </li>
          </ul>
        </div>

        <!-- Selected List Details -->
        <div v-if="selectedList !== null" class="w-full lg:w-2/3 not-dark:bg-white dark:bg-gray-800 rounded-xl shadow-lg border not-dark:border-gray-200 dark:border-gray-700 p-6">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold not-dark:text-gray-900 dark:text-white">{{ lists[selectedList].name }}</h3>
          </div>
          <div v-if="lists[selectedList].items.length === 0" class="text-center py-8">
            <p class="not-dark:text-gray-500 dark:text-gray-400">No items in this list yet.</p>
          </div>
          <ul v-else class="space-y-3 mb-6">
            <li v-for="item in lists[selectedList].items" :key="item.id" 
                class="flex items-center justify-between p-3 not-dark:bg-gray-50 dark:bg-gray-700 rounded-lg border not-dark:border-gray-200 dark:border-gray-600">
              <span class="not-dark:text-gray-900 dark:text-white font-medium">
                {{ item.name }} 
                <span v-if="item.qty > 1" class="text-sm not-dark:text-gray-600 dark:text-gray-400">x{{ item.qty }}</span>
              </span>
              <button @click="removeItemFromList(selectedList, item.id)" 
                      class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 font-medium transition-colors">
                Remove
              </button>
            </li>
          </ul>
          <button @click="editList(selectedList)" 
                  class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200">
            Add Products
          </button>
        </div>
      </div>
    </div>
  </div>

    <!-- Create/Edit List Modal -->
    <div v-if="editingList !== null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="w-full max-w-2xl mx-4 not-dark:bg-white dark:bg-gray-800 rounded-xl shadow-xl border not-dark:border-gray-200 dark:border-gray-700 p-6 relative max-h-[90vh] overflow-hidden">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-bold not-dark:text-gray-900 dark:text-white">
            {{ editingList === 'new' ? 'Create New List' : 'Edit List' }}
          </h3>
          <button @click="cancelEdit" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        
        <div class="space-y-4 mb-6">
          <input v-model="listName" 
                 placeholder="List name" 
                 class="w-full p-3 border not-dark:border-gray-300 dark:border-gray-600 rounded-lg not-dark:bg-white dark:bg-gray-700 not-dark:text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          <input v-model="search" 
                 placeholder="Search products..." 
                 class="w-full p-3 border not-dark:border-gray-300 dark:border-gray-600 rounded-lg not-dark:bg-white dark:bg-gray-700 not-dark:text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
        </div>
        
        <div class="max-h-64 overflow-y-auto not-dark:bg-gray-50 dark:bg-gray-700 rounded-lg border not-dark:border-gray-200 dark:border-gray-600 p-4">
          <div v-for="product in filteredProducts" :key="product.id" 
               class="flex items-center justify-between py-3 border-b last:border-b-0 not-dark:border-gray-200 dark:border-gray-600">
            <span class="not-dark:text-gray-900 dark:text-white font-medium">{{ product.name }}</span>
            <div class="flex items-center gap-3">
              <button @click="decrement(product.id)" 
                      class="w-8 h-8 flex items-center justify-center bg-gray-300 hover:bg-gray-400 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg font-bold transition-colors">
                -
              </button>
              <span class="w-8 text-center font-semibold not-dark:text-gray-900 dark:text-white">{{ quantities[product.id] || 0 }}</span>
              <button @click="increment(product.id)" 
                      class="w-8 h-8 flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold transition-colors">
                +
              </button>
            </div>
          </div>
        </div>
        
        <div class="flex justify-end gap-3 mt-6">
          <button @click="cancelEdit" 
                  class="px-6 py-2 not-dark:bg-gray-200 dark:bg-gray-600 not-dark:text-gray-700 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors">
            Cancel
          </button>
          <button @click="saveList" 
                  class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
            {{ editingList === 'new' ? 'Create List' : 'Save Changes' }}
          </button>
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
