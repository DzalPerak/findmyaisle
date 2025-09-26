<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useUserRoles } from '@/composables/useUserRoles';
import { BreadcrumbItem } from '@/types';
import { 
    Plus, 
    Search, 
    LayoutGrid, 
    List, 
    MapPin, 
    Edit, 
    Trash2, 
    Eye,
    MoreVertical,
    Store,
    FileText,
    Loader
} from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import ShopFormModal from '@/components/admin/ShopFormModal.vue';
import ShopViewModal from '@/components/admin/ShopViewModal.vue';
import ShopDeleteModal from '@/components/admin/ShopDeleteModal.vue';

// Shop Management System
interface Shop {
    id: number;
    name: string;
    location: string;
    latitude?: number | string;
    longitude?: number | string;
    description?: string;
    dxf_file_path?: string;
    created_at: string;
    updated_at: string;
}

interface ShopsResponse {
    data: Shop[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

// Permissions check
const { canAccessAdmin, hasRole } = useUserRoles();
const canEdit = computed(() => hasRole('admin') || hasRole('editor'));

// State management
const shops = ref<Shop[]>([]);
const loading = ref(false);
const searchQuery = ref('');
const viewMode = ref<'grid' | 'list'>('grid');
const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    perPage: 12,
    total: 0
});

// Modal states
const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const showViewModal = ref(false);
const selectedShop = ref<Shop | null>(null);

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Shops', href: '/admin/shops' }
];

// Helper function to safely format coordinates
const formatCoordinate = (coord: string | number | undefined, decimals: number = 4): string => {
    if (coord === undefined || coord === null) return '';
    const num = typeof coord === 'string' ? parseFloat(coord) : coord;
    return isNaN(num) ? '' : num.toFixed(decimals);
};

// API Functions
const fetchShops = async (page = 1, search = '') => {
    loading.value = true;
    try {
        const params = new URLSearchParams({
            page: page.toString(),
            per_page: pagination.value.perPage.toString(),
            ...(search && { search })
        });

        const response = await fetch(`/api/shops?${params}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error('Failed to fetch shops');
        }

        const data: ShopsResponse = await response.json();
        shops.value = data.data;
        pagination.value = {
            currentPage: data.current_page,
            lastPage: data.last_page,
            perPage: data.per_page,
            total: data.total
        };
    } catch (error) {
        console.error('Error fetching shops:', error);
    } finally {
        loading.value = false;
    }
};

const deleteShop = async (shop: Shop) => {
    loading.value = true;
    try {
        const response = await fetch(`/api/shops/${shop.id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });

        if (!response.ok) {
            throw new Error('Failed to delete shop');
        }

        await fetchShops(pagination.value.currentPage, searchQuery.value);
        showDeleteModal.value = false;
        selectedShop.value = null;
    } catch (error) {
        console.error('Error deleting shop:', error);
    } finally {
        loading.value = false;
    }
};

// Event handlers
const handleSearch = () => {
    fetchShops(1, searchQuery.value);
};

const handlePageChange = (page: number) => {
    fetchShops(page, searchQuery.value);
};

const openAddModal = () => {
    selectedShop.value = null;
    showAddModal.value = true;
};

const openEditModal = (shop: Shop) => {
    selectedShop.value = shop;
    showEditModal.value = true;
};

const openViewModal = (shop: Shop) => {
    selectedShop.value = shop;
    showViewModal.value = true;
};

const openDeleteModal = (shop: Shop) => {
    selectedShop.value = shop;
    showDeleteModal.value = true;
};

const onShopSaved = () => {
    fetchShops(pagination.value.currentPage, searchQuery.value);
    showAddModal.value = false;
    showEditModal.value = false;
};

// Lifecycle
onMounted(() => {
    if (canAccessAdmin.value) {
        fetchShops();
    }
});
</script>

<template>
    <Head title="Shop Management" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Permission Check -->
        <div v-if="!canAccessAdmin" class="flex items-center justify-center min-h-96">
            <div class="text-center">
                <Store class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4" />
                <h3 class="text-xl font-semibold not-dark:text-gray-900 dark:text-white mb-2">
                    Access Restricted
                </h3>
                <p class="not-dark:text-gray-600 dark:text-gray-400">
                    You don't have permission to manage shops.
                </p>
            </div>
        </div>

        <!-- Main Content -->
        <div v-else class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold not-dark:text-gray-900 dark:text-white">
                        Shop Management
                    </h1>
                    <p class="not-dark:text-gray-600 dark:text-gray-400 mt-1">
                        Manage your shop locations and information
                    </p>
                </div>
                
                <Button v-if="canEdit" @click="openAddModal" class="flex items-center gap-2">
                    <Plus class="w-4 h-4" />
                    Add New Shop
                </Button>
            </div>

            <!-- Toolbar -->
            <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                <!-- Search -->
                <div class="relative flex-1 max-w-md">
                    <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search shops..."
                        class="pl-10"
                        @keyup.enter="handleSearch"
                    />
                </div>

                <!-- View Mode Toggle -->
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :class="{ 'bg-blue-50 border-blue-200 text-blue-700 dark:bg-blue-950 dark:border-blue-800 dark:text-blue-300': viewMode === 'grid' }"
                        @click="viewMode = 'grid'"
                    >
                        <LayoutGrid class="w-4 h-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :class="{ 'bg-blue-50 border-blue-200 text-blue-700 dark:bg-blue-950 dark:border-blue-800 dark:text-blue-300': viewMode === 'list' }"
                        @click="viewMode = 'list'"
                    >
                        <List class="w-4 h-4" />
                    </Button>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <Loader class="w-8 h-8 animate-spin text-blue-600" />
            </div>

            <!-- Empty State -->
            <div v-else-if="shops.length === 0" class="text-center py-12">
                <Store class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4" />
                <h3 class="text-lg font-semibold not-dark:text-gray-900 dark:text-white mb-2">
                    No shops found
                </h3>
                <p class="not-dark:text-gray-600 dark:text-gray-400 mb-4">
                    {{ searchQuery ? 'No shops match your search criteria.' : 'Get started by adding your first shop.' }}
                </p>
                <Button v-if="canEdit && !searchQuery" @click="openAddModal">
                    <Plus class="w-4 h-4 mr-2" />
                    Add Your First Shop
                </Button>
            </div>

            <!-- Grid View -->
            <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <div
                    v-for="shop in shops"
                    :key="shop.id"
                    class="group not-dark:bg-white dark:bg-gray-800 rounded-lg border not-dark:border-gray-200 dark:border-gray-700 p-6 hover:shadow-md transition-all duration-200"
                >
                    <!-- Card Header -->
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                                <Store class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold not-dark:text-gray-900 dark:text-white truncate">
                                    {{ shop.name }}
                                </h3>
                            </div>
                        </div>
                        
                        <!-- Actions Dropdown -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="ghost" size="sm" class="h-8 w-8 p-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <MoreVertical class="w-4 h-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="openViewModal(shop)" class="cursor-pointer">
                                    <Eye class="w-4 h-4 mr-2" />
                                    View Details
                                </DropdownMenuItem>
                                <DropdownMenuItem v-if="canEdit" @click="openEditModal(shop)" class="cursor-pointer">
                                    <Edit class="w-4 h-4 mr-2" />
                                    Edit Shop
                                </DropdownMenuItem>
                                <DropdownMenuItem v-if="canEdit" @click="openDeleteModal(shop)" class="cursor-pointer text-red-600 dark:text-red-400">
                                    <Trash2 class="w-4 h-4 mr-2" />
                                    Delete Shop
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <!-- Card Content -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-sm not-dark:text-gray-600 dark:text-gray-400">
                            <MapPin class="w-4 h-4" />
                            <span class="truncate">{{ shop.location }}</span>
                        </div>
                        
                        <div v-if="shop.description" class="text-sm not-dark:text-gray-600 dark:text-gray-400">
                            <p class="line-clamp-2">{{ shop.description }}</p>
                        </div>

                        <div v-if="shop.dxf_file_path" class="flex items-center gap-2 text-xs text-green-600 dark:text-green-400">
                            <FileText class="w-3 h-3" />
                            <span>Layout file attached</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List View -->
            <div v-else class="not-dark:bg-white dark:bg-gray-800 rounded-lg border not-dark:border-gray-200 dark:border-gray-700">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="not-dark:bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium not-dark:text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Shop
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium not-dark:text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Location
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium not-dark:text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Coordinates
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium not-dark:text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Layout
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium not-dark:text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y not-dark:divide-gray-200 dark:divide-gray-700">
                            <tr v-for="shop in shops" :key="shop.id" class="hover:not-dark:bg-gray-50 hover:dark:bg-gray-700/50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                                            <Store class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <div>
                                            <div class="font-medium not-dark:text-gray-900 dark:text-white">
                                                {{ shop.name }}
                                            </div>
                                            <div v-if="shop.description" class="text-sm not-dark:text-gray-500 dark:text-gray-400 truncate max-w-xs">
                                                {{ shop.description }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm not-dark:text-gray-900 dark:text-white">
                                    {{ shop.location }}
                                </td>
                                <td class="px-6 py-4 text-sm not-dark:text-gray-500 dark:text-gray-400">
                                    <span v-if="shop.latitude && shop.longitude">
                                        {{ formatCoordinate(shop.latitude, 4) }}, {{ formatCoordinate(shop.longitude, 4) }}
                                    </span>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span v-if="shop.dxf_file_path" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                        <FileText class="w-3 h-3 mr-1" />
                                        Available
                                    </span>
                                    <span v-else class="text-sm not-dark:text-gray-500 dark:text-gray-400">-</span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button variant="ghost" size="sm" @click="openViewModal(shop)">
                                            <Eye class="w-4 h-4" />
                                        </Button>
                                        <Button v-if="canEdit" variant="ghost" size="sm" @click="openEditModal(shop)">
                                            <Edit class="w-4 h-4" />
                                        </Button>
                                        <Button v-if="canEdit" variant="ghost" size="sm" @click="openDeleteModal(shop)" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                            <Trash2 class="w-4 h-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.lastPage > 1" class="flex items-center justify-between">
                <div class="text-sm not-dark:text-gray-700 dark:text-gray-300">
                    Showing {{ ((pagination.currentPage - 1) * pagination.perPage) + 1 }} to 
                    {{ Math.min(pagination.currentPage * pagination.perPage, pagination.total) }} of 
                    {{ pagination.total }} results
                </div>
                <div class="flex items-center gap-2">
                    <Button 
                        variant="outline" 
                        size="sm" 
                        :disabled="pagination.currentPage <= 1"
                        @click="handlePageChange(pagination.currentPage - 1)"
                    >
                        Previous
                    </Button>
                    <Button 
                        variant="outline" 
                        size="sm" 
                        :disabled="pagination.currentPage >= pagination.lastPage"
                        @click="handlePageChange(pagination.currentPage + 1)"
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <ShopFormModal
            v-model:open="showAddModal"
            mode="add"
            @saved="onShopSaved"
        />
        
        <ShopFormModal
            v-model:open="showEditModal"
            mode="edit"
            :shop="selectedShop"
            @saved="onShopSaved"
        />
        
        <ShopViewModal
            v-model:open="showViewModal"
            :shop="selectedShop"
        />
        
        <ShopDeleteModal
            v-model:open="showDeleteModal"
            :shop="selectedShop"
            @confirmed="deleteShop"
        />
    </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
