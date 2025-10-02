<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick } from 'vue';
import { Modal } from 'flowbite';
import axios from 'axios';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Shopping Lists',
        href: dashboard().url,
    },
];

// Data
const products = ref([]);
const lists = ref<{id: number, name: string, items: {id: number, name: string, qty: number}[]}[]>([]);
const search = ref('');
const quantities = ref<Record<number, number>>({});
const listName = ref('');
const selectedList = ref<null | number>(null);
const editingList = ref<null | number | 'new'>(null);
const notification = ref({ show: false, type: '', message: '' });

// Modal references
let createListModal: Modal;
let viewListModal: Modal;

onMounted(async () => {
    await fetchProducts();
    await fetchLists();
    
    // Initialize Flowbite modals
    await nextTick();
    const createModalEl = document.getElementById('create-list-modal');
    const viewModalEl = document.getElementById('view-list-modal');
    
    if (createModalEl) {
        createListModal = new Modal(createModalEl);
    }
    if (viewModalEl) {
        viewListModal = new Modal(viewModalEl);
    }
});

async function fetchProducts() {
    try {
        const res = await axios.get('/api/products');
        products.value = res.data;
    } catch (error) {
        showNotification('error', 'Failed to load products');
    }
}

async function fetchLists() {
    try {
        const res = await axios.get('/api/shopping-lists');
        lists.value = res.data;
    } catch (error) {
        showNotification('error', 'Failed to load shopping lists');
    }
}

const filteredProducts = computed(() => {
    if (!search.value) return products.value;
    return products.value.filter(p => p.name.toLowerCase().includes(search.value.toLowerCase()));
});

function showNotification(type: string, message: string) {
    notification.value = { show: true, type, message };
    setTimeout(() => {
        notification.value.show = false;
    }, 5000);
}

function startNewList() {
    listName.value = '';
    quantities.value = {};
    search.value = '';
    editingList.value = 'new';
    createListModal?.show();
}

function editList(idx: number) {
    const list = lists.value[idx];
    listName.value = list.name;
    quantities.value = {};
    for (const item of list.items) {
        quantities.value[item.id] = item.qty;
    }
    search.value = '';
    editingList.value = idx;
    viewListModal?.hide();
    createListModal?.show();
}

async function saveList() {
    const items = products.value
        .filter(p => (quantities.value[p.id] || 0) > 0)
        .map(p => ({ id: p.id, qty: quantities.value[p.id] }));
    
    if (!listName.value.trim()) {
        showNotification('error', 'Please enter a list name');
        return;
    }
    
    if (items.length === 0) {
        showNotification('error', 'Please add at least one product');
        return;
    }
    
    try {
        if (editingList.value === 'new') {
            await axios.post('/api/shopping-lists', {
                name: listName.value,
                items: items
            });
            showNotification('success', 'Shopping list created successfully');
        } else if (typeof editingList.value === 'number') {
            const listId = lists.value[editingList.value].id;
            await axios.put(`/api/shopping-lists/${listId}`, {
                name: listName.value,
                items: items
            });
            showNotification('success', 'Shopping list updated successfully');
        }
        
        await fetchLists();
        createListModal?.hide();
        editingList.value = null;
    } catch (error) {
        showNotification('error', 'Failed to save shopping list');
    }
}

function selectList(idx: number) {
    selectedList.value = idx;
    viewListModal?.show();
}

async function removeList(idx: number) {
    try {
        const listId = lists.value[idx].id;
        await axios.delete(`/api/shopping-lists/${listId}`);
        await fetchLists();
        if (selectedList.value === idx) selectedList.value = null;
        showNotification('success', 'Shopping list deleted successfully');
    } catch (error) {
        showNotification('error', 'Failed to delete shopping list');
    }
}

async function removeItemFromList(listIdx: number, itemId: number) {
    try {
        const listId = lists.value[listIdx].id;
        await axios.delete(`/api/shopping-lists/${listId}/items`, {
            data: { product_id: itemId }
        });
        await fetchLists();
        showNotification('success', 'Item removed from list');
    } catch (error) {
        showNotification('error', 'Failed to remove item');
    }
}

function increment(id: number) {
    quantities.value[id] = (quantities.value[id] || 0) + 1;
}

function decrement(id: number) {
    if (quantities.value[id]) quantities.value[id]--;
}

function cancelEdit() {
    createListModal?.hide();
    editingList.value = null;
}
</script>

<template>
    <Head title="Shopping Lists" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Notification -->
        <div v-if="notification.show" 
             :class="[
                'fixed top-4 right-4 z-50 p-4 mb-4 text-sm rounded-lg',
                notification.type === 'success' ? 'text-green-800 not-dark:bg-green-50 dark:bg-gray-800 dark:text-green-400' : 'text-red-800 not-dark:bg-red-50 dark:bg-gray-800 dark:text-red-400'
             ]" 
             role="alert">
            <span class="font-medium">{{ notification.type === 'success' ? 'Success!' : 'Error!' }}</span>
            {{ notification.message }}
        </div>

        <div class="p-4">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold not-dark:text-gray-900 dark:text-white">My Shopping Lists</h1>
                    <button @click="startNewList" 
                            type="button" 
                            class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">
                        Create New List
                    </button>
                </div>
            </div>

            <!-- Lists Grid -->
            <div v-if="lists.length === 0" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 not-dark:text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h3 class="mt-2 text-sm font-medium not-dark:text-gray-900 dark:text-white">No shopping lists</h3>
                <p class="mt-1 text-sm not-dark:text-gray-500 dark:text-gray-400">Get started by creating a new shopping list.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div v-for="(list, idx) in lists" :key="list.id" 
                     class="max-w-sm p-6 not-dark:bg-white border not-dark:border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700 cursor-pointer hover:shadow-lg transition-shadow">
                    <h5 @click="selectList(idx)" class="mb-2 text-2xl font-bold tracking-tight not-dark:text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-500">
                        {{ list.name }}
                    </h5>
                    <p class="mb-3 font-normal not-dark:text-gray-700 dark:text-gray-400">
                        {{ list.items.length }} items
                    </p>
                    <div class="flex gap-2">
                        <button @click="selectList(idx)" 
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            View
                        </button>
                        <button @click="removeList(idx)" 
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white bg-red-700 rounded-lg hover:bg-red-800 focus:ring-4 focus:outline-none focus:ring-red-300 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-800">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit List Modal -->
        <div id="create-list-modal" tabindex="-1" aria-hidden="true" 
             class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
            <div class="relative p-4 w-full max-w-4xl max-h-full">
                <div class="relative not-dark:bg-white rounded-lg shadow dark:bg-gray-700">
                    <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t not-dark:border-gray-200 dark:border-gray-600">
                        <h3 class="text-xl font-semibold not-dark:text-gray-900 dark:text-white">
                            {{ editingList === 'new' ? 'Create New List' : 'Edit List' }}
                        </h3>
                        <button @click="cancelEdit" type="button" 
                                class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white">
                            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                            </svg>
                        </button>
                    </div>
                    <div class="p-4 md:p-5 space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium not-dark:text-gray-900 dark:text-white">List Name</label>
                            <input v-model="listName" type="text" 
                                   class="bg-gray-50 border border-gray-300 not-dark:text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" 
                                   placeholder="Enter list name" required>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium not-dark:text-gray-900 dark:text-white">Search Products</label>
                            <input v-model="search" type="text" 
                                   class="bg-gray-50 border border-gray-300 not-dark:text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" 
                                   placeholder="Search products...">
                        </div>
                        <div class="max-h-64 overflow-y-auto">
                            <div v-for="product in filteredProducts" :key="product.id" 
                                 class="flex items-center justify-between py-2 px-3 border-b not-dark:border-gray-200 dark:border-gray-600">
                                <span class="not-dark:text-gray-900 dark:text-white">{{ product.name }}</span>
                                <div class="flex items-center gap-2">
                                    <button @click="decrement(product.id)" 
                                            class="text-white bg-gray-700 hover:bg-gray-800 focus:ring-4 focus:ring-gray-300 font-medium rounded-lg text-sm px-2 py-1 dark:bg-gray-600 dark:hover:bg-gray-700 focus:outline-none dark:focus:ring-gray-800">
                                        -
                                    </button>
                                    <span class="w-6 text-center not-dark:text-gray-900 dark:text-white">{{ quantities[product.id] || 0 }}</span>
                                    <button @click="increment(product.id)" 
                                            class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-2 py-1 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">
                                        +
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b dark:border-gray-600">
                        <button @click="saveList" type="button" 
                                class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            {{ editingList === 'new' ? 'Create List' : 'Update List' }}
                        </button>
                        <button @click="cancelEdit" type="button" 
                                class="py-2.5 px-5 ms-3 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border not-dark:border-gray-200 not-dark:hover:bg-gray-100 not-dark:hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- View List Modal -->
        <div id="view-list-modal" tabindex="-1" aria-hidden="true" 
             class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
            <div class="relative p-4 w-full max-w-3xl max-h-full">
                <div class="relative not-dark:bg-white rounded-lg shadow dark:bg-gray-700">
                    <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t not-dark:border-gray-200 dark:border-gray-600">
                        <h3 class="text-xl font-semibold not-dark:text-gray-900 dark:text-white">
                            {{ selectedList !== null ? lists[selectedList]?.name : '' }}
                        </h3>
                        <button @click="viewListModal?.hide()" type="button" 
                                class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white">
                            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                            </svg>
                        </button>
                    </div>
                    <div class="p-4 md:p-5 space-y-4">
                        <div v-if="selectedList !== null && lists[selectedList]?.items?.length === 0" 
                             class="text-center py-4 not-dark:text-gray-500 dark:text-gray-400">
                            No items in this list
                        </div>
                        <div v-else-if="selectedList !== null" class="space-y-2">
                            <div v-for="item in lists[selectedList]?.items" :key="item.id" 
                                 class="flex items-center justify-between py-2 px-3 not-dark:bg-gray-50 dark:bg-gray-600 rounded-lg">
                                <span class="not-dark:text-gray-900 dark:text-white">
                                    {{ item.name }} 
                                    <span v-if="item.qty > 1" class="text-sm not-dark:text-gray-500 dark:text-gray-400">x{{ item.qty }}</span>
                                </span>
                                <button @click="removeItemFromList(selectedList, item.id)" 
                                        class="text-white bg-red-700 hover:bg-red-800 focus:ring-4 focus:ring-red-300 font-medium rounded-lg text-sm px-2 py-1 dark:bg-red-600 dark:hover:bg-red-700 focus:outline-none dark:focus:ring-red-800">
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b dark:border-gray-600">
                        <button v-if="selectedList !== null" @click="editList(selectedList)" type="button" 
                                class="text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:outline-none focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-800">
                            Add Products
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
