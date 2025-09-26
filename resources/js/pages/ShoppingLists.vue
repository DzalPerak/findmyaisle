<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Shopping Lists',
        href: dashboard().url,
    },
];

const lists = ref<{ id: number; name: string; items: { id: number; name: string; qty: number }[] }[]>([]);
const selectedList = ref<null | number>(null);
const showListModal = ref(false);
const showManageModal = ref(false);
const showToast = ref(false);
const toastType = ref<'success' | 'error'>('success');
const toastMsg = ref('');
const listName = ref('');
const products = ref([]);
const search = ref('');
const quantities = ref<Record<number, number>>({});
let nextListId = 1;

function openListModal() {
    listName.value = '';
    quantities.value = {};
    showListModal.value = true;
}

function openManageModal(idx: number) {
    const list = lists.value[idx];
    listName.value = list.name;
    quantities.value = {};
    for (const item of list.items) {
        quantities.value[item.id] = item.qty;
    }
    selectedList.value = idx;
    showManageModal.value = true;
}

function closeModals() {
    showListModal.value = false;
    showManageModal.value = false;
}

function showNotification(msg: string, type: 'success' | 'error') {
    toastMsg.value = msg;
    toastType.value = type;
    showToast.value = true;
    setTimeout(() => (showToast.value = false), 2500);
}

function saveList() {
    const items = products.value
        .filter((p) => (quantities.value[p.id] || 0) > 0)
        .map((p) => ({ id: p.id, name: p.name, qty: quantities.value[p.id] }));
    if (!listName.value.trim() || items.length === 0) {
        showNotification('List name and at least one product required', 'error');
        return;
    }
    lists.value.push({ id: nextListId++, name: listName.value, items });
    showNotification('List created!', 'success');
    closeModals();
}

function updateList() {
    if (selectedList.value === null) return;
    const items = products.value
        .filter((p) => (quantities.value[p.id] || 0) > 0)
        .map((p) => ({ id: p.id, name: p.name, qty: quantities.value[p.id] }));
    if (!listName.value.trim() || items.length === 0) {
        showNotification('List name and at least one product required', 'error');
        return;
    }
    lists.value[selectedList.value].name = listName.value;
    lists.value[selectedList.value].items = items;
    showNotification('List updated!', 'success');
    closeModals();
}

function removeList(idx: number) {
    lists.value.splice(idx, 1);
    if (selectedList.value === idx) selectedList.value = null;
    showNotification('List removed', 'success');
}

function removeItemFromList(listIdx: number, itemId: number) {
    const list = lists.value[listIdx];
    list.items = list.items.filter((i) => i.id !== itemId);
    showNotification('Item removed', 'success');
}

function selectList(idx: number) {
    selectedList.value = idx;
}

function increment(id: number) {
    quantities.value[id] = (quantities.value[id] || 0) + 1;
}

function decrement(id: number) {
    if (quantities.value[id]) quantities.value[id]--;
}

const filteredProducts = computed(() =>
    !search.value
        ? products.value
        : products.value.filter((p) => p.name.toLowerCase().includes(search.value.toLowerCase()))
);

onMounted(async () => {
    const res = await axios.get('/api/products');
    products.value = res.data;
});
</script>

<template>
    <Head title="Shopping Lists" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col md:flex-row gap-6 w-full p-4">
            <!-- Lists -->
            <div class="w-full md:w-1/3">
                <div class="not-dark:bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold">My Shopping Lists</h2>
                        <button
                            data-modal-target="add-list-modal"
                            data-modal-toggle="add-list-modal"
                            @click="openListModal"
                            type="button"
                            class="not-dark:bg-blue-700 dark:bg-blue-600 text-white font-medium rounded-lg text-sm px-4 py-2 text-center"
                        >
                            Add List
                        </button>
                    </div>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li
                            v-for="(list, idx) in lists"
                            :key="list.id"
                            class="flex items-center justify-between py-2"
                        >
                            <span @click="selectList(idx)" class="cursor-pointer hover:underline">{{ list.name }}</span>
                            <button
                                @click="removeList(idx)"
                                type="button"
                                class="not-dark:bg-red-600 dark:bg-red-700 text-white font-medium rounded-lg text-xs px-3 py-1"
                            >
                                Remove
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
            <!-- List Details -->
            <div v-if="selectedList !== null" class="w-full md:w-2/3">
                <div class="not-dark:bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold">{{ lists[selectedList].name }}</h3>
                        <button
                            data-modal-target="manage-list-modal"
                            data-modal-toggle="manage-list-modal"
                            @click="openManageModal(selectedList)"
                            type="button"
                            class="not-dark:bg-green-600 dark:bg-green-700 text-white font-medium rounded-lg text-sm px-4 py-2"
                        >
                            Manage
                        </button>
                    </div>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li
                            v-for="item in lists[selectedList].items"
                            :key="item.id"
                            class="flex items-center justify-between py-2"
                        >
                            <span>{{ item.name }} <span v-if="item.qty > 1">x{{ item.qty }}</span></span>
                            <button
                                @click="removeItemFromList(selectedList, item.id)"
                                type="button"
                                class="not-dark:bg-red-600 dark:bg-red-700 text-white font-medium rounded-lg text-xs px-3 py-1"
                            >
                                Remove
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Add List Modal -->
        <div
            v-if="showListModal"
            id="add-list-modal"
            tabindex="-1"
            aria-hidden="true"
            class="fixed top-0 left-0 right-0 z-50 flex justify-center items-center w-full h-full bg-black/50"
        >
            <div class="relative w-full max-w-md not-dark:bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <button
                    type="button"
                    @click="closeModals"
                    class="absolute top-2 right-2 not-dark:text-gray-400 dark:text-gray-300 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center"
                    data-modal-hide="add-list-modal"
                >
                    <svg
                        class="w-5 h-5"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                        ></path>
                    </svg>
                </button>
                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Create Shopping List</h3>
                <input
                    v-model="listName"
                    placeholder="List name"
                    class="not-dark:bg-gray-50 not-dark:text-gray-600 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 mb-4"
                />
                <input
                    v-model="search"
                    placeholder="Search products..."
                    class="not-dark:bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 mb-4"
                />
                <div class="max-h-48 overflow-y-auto divide-y divide-gray-200 dark:divide-gray-700 mb-4">
                    <div
                        v-for="product in filteredProducts"
                        :key="product.id"
                        class="flex items-center justify-between py-2"
                    >
                        <span>{{ product.name }}</span>
                        <div class="flex items-center gap-2">
                            <button
                                @click="decrement(product.id)"
                                type="button"
                                class="not-dark:bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg px-2 py-1"
                            >
                                -
                            </button>
                            <span class="w-6 text-center">{{ quantities[product.id] || 0 }}</span>
                            <button
                                @click="increment(product.id)"
                                type="button"
                                class="not-dark:bg-blue-600 dark:bg-blue-700 text-white rounded-lg px-2 py-1"
                            >
                                +
                            </button>
                        </div>
                    </div>
                </div>
                <button
                    @click="saveList"
                    type="button"
                    class="w-full not-dark:bg-blue-700 dark:bg-blue-600 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center"
                >
                    Create List
                </button>
            </div>
        </div>

        <!-- Manage List Modal -->
        <div
            v-if="showManageModal"
            id="manage-list-modal"
            tabindex="-1"
            aria-hidden="true"
            class="fixed top-0 left-0 right-0 z-50 flex justify-center items-center w-full h-full bg-black/50"
        >
            <div class="relative w-full max-w-md not-dark:bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <button
                    type="button"
                    @click="closeModals"
                    class="absolute top-2 right-2 not-dark:text-gray-400 dark:text-gray-300 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center"
                    data-modal-hide="manage-list-modal"
                >
                    <svg
                        class="w-5 h-5"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                        ></path>
                    </svg>
                </button>
                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Manage Shopping List</h3>
                <input
                    v-model="listName"
                    placeholder="List name"
                    class="not-dark:bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 mb-4"
                />
                <input
                    v-model="search"
                    placeholder="Search products..."
                    class="not-dark:bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 mb-4"
                />
                <div class="max-h-48 overflow-y-auto divide-y divide-gray-200 dark:divide-gray-700 mb-4">
                    <div
                        v-for="product in filteredProducts"
                        :key="product.id"
                        class="flex items-center justify-between py-2"
                    >
                        <span>{{ product.name }}</span>
                        <div class="flex items-center gap-2">
                            <button
                                @click="decrement(product.id)"
                                type="button"
                                class="not-dark:bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg px-2 py-1"
                            >
                                -
                            </button>
                            <span class="w-6 text-center">{{ quantities[product.id] || 0 }}</span>
                            <button
                                @click="increment(product.id)"
                                type="button"
                                class="not-dark:bg-blue-600 dark:bg-blue-700 text-white rounded-lg px-2 py-1"
                            >
                                +
                            </button>
                        </div>
                    </div>
                </div>
                <button
                    @click="updateList"
                    type="button"
                    class="w-full not-dark:bg-green-600 dark:bg-green-700 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center"
                >
                    Update List
                </button>
            </div>
        </div>

        <!-- Toast Notification -->
        <div v-if="showToast" class="fixed top-6 right-6 z-50">
            <div
                :class="
                    toastType === 'success'
                        ? 'not-dark:bg-green-100 not-dark:text-green-800 dark:bg-green-900 dark:text-green-200'
                        : 'not-dark:bg-red-100 not-dark:text-red-800 dark:bg-red-900 dark:text-red-200'
                "
                class="flex items-center p-4 mb-4 text-sm rounded-lg shadow"
                role="alert"
            >
                <svg
                    v-if="toastType === 'success'"
                    class="inline w-5 h-5 mr-3"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                >
                    <path
                        fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 00-1.414 0L9 11.586l-2.293-2.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l7-7a1 1 0 000-1.414z"
                        clip-rule="evenodd"
                    ></path>
                </svg>
                <svg
                    v-else
                    class="inline w-5 h-5 mr-3"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                >
                    <path
                        fill-rule="evenodd"
                        d="M18 13a1 1 0 01-1 1H7a1 1 0 110-2h9a1 1 0 011 1zm-1-5a1 1 0 00-1-1H7a1 1 0 100 2h9a1 1 0 001-1zM7 17a1 1 0 100-2h6a1 1 0 100 2H7z"
                        clip-rule="evenodd"
                    ></path>
                </svg>
                <span class="font-medium">{{ toastMsg }}</span>
            </div>
        </div>
    </AppLayout>
</template>
