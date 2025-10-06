<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { notify } from '@/utils/notifications';
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
    Loader,
    Map
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
import ShopLayoutEditorModal from '@/components/admin/ShopLayoutEditorModal.vue';

// Shop Management System
interface Shop {
    id: number;
    name: string;
    location: string;
    latitude?: number | string | null;
    longitude?: number | string | null;
    description?: string;
    dxf_file_path?: string | null;
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

// Page and flash messages
const page = usePage();

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
const showLayoutEditorModal = ref(false);
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

const deleteShop = (shop: Shop) => {
    loading.value = true;
    
    router.delete(`/api/shops/${shop.id}`, {
        onSuccess: () => {
            notify.success(
                'Shop deleted successfully',
                `${shop.name} has been permanently deleted.`
            );
            fetchShops(pagination.value.currentPage, searchQuery.value);
            showDeleteModal.value = false;
            selectedShop.value = null;
        },
        onError: (errors) => {
            console.error('Error deleting shop:', errors);
            notify.error(
                'Failed to delete shop',
                'An error occurred while trying to delete the shop. Please try again.'
            );
        },
        onFinish: () => {
            loading.value = false;
        }
    });
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

const openLayoutEditorModal = (shop: Shop) => {
    selectedShop.value = shop;
    showLayoutEditorModal.value = true;
};

const onShopSaved = () => {
    fetchShops(pagination.value.currentPage, searchQuery.value);
    showAddModal.value = false;
    showEditModal.value = false;
};

const onLayoutSaved = () => {
    fetchShops(pagination.value.currentPage, searchQuery.value);
    showLayoutEditorModal.value = false;
};

// Watch for flash messages
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) {
        notify.success('Success', flash.success);
    }
    if (flash?.error) {
        notify.error('Error', flash.error);
    }
}, { immediate: true, deep: true });

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
                <Store class="w-16 h-16 mx-auto not-dark:text-gray-400 dark:text-gray-600 mb-4" />
                <h3 class="text-xl font-semibold not-dark:text-gray-900 dark:text-white mb-2">
                    Access Restricted
                </h3>
                <p class="not-dark:text-gray-600 dark:text-gray-400">
                    You don't have permission to manage shops.
                </p>
            </div>
        </div>

        <!-- Main Content -->
        <div v-else class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="not-dark:bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8 space-y-6">
                        <!-- Header -->
                        <div class="text-center">
                            <h1 class="text-4xl font-bold not-dark:text-gray-900 dark:text-white mb-4">
                                Shop Management
                            </h1>
                            <p class="text-lg not-dark:text-gray-600 dark:text-gray-300 max-w-2xl mx-auto mb-8">
                                Manage your shop locations and information with comprehensive administrative tools
                            </p>
                    
                    <Button v-if="canEdit" @click="openAddModal" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 inline-flex items-center gap-2">
                        <Plus class="w-5 h-5" />
                        Add New Shop
                    </Button>
                </div>

                <!-- Quick Stats Section -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="not-dark:bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 text-center border not-dark:border-gray-200 dark:border-gray-700">
                        <div class="text-3xl font-bold not-dark:text-gray-900 dark:text-white mb-2">{{ pagination.total }}</div>
                        <div class="text-sm not-dark:text-gray-500 dark:text-gray-400">Total Shops</div>
                    </div>
                    <div class="not-dark:bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 text-center border not-dark:border-gray-200 dark:border-gray-700">
                        <div class="text-3xl font-bold text-green-600 dark:text-green-400 mb-2">{{ shops.filter(shop => shop.dxf_file_path).length }}</div>
                        <div class="text-sm not-dark:text-gray-500 dark:text-gray-400">With Layouts</div>
                    </div>
                    <div class="not-dark:bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 text-center border not-dark:border-gray-200 dark:border-gray-700">
                        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-2">{{ shops.filter(shop => shop.latitude && shop.longitude).length }}</div>
                        <div class="text-sm not-dark:text-gray-500 dark:text-gray-400">With Coordinates</div>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="not-dark:bg-white dark:bg-gray-800 rounded-xl shadow-sm border not-dark:border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                        <!-- Search -->
                        <div class="relative flex-1 max-w-md">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 not-dark:text-gray-400 w-4 h-4" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search shops..."
                                class="pl-10 not-dark:border-gray-300 dark:border-gray-600 focus:border-blue-500 focus:ring-blue-500"
                                @keyup.enter="handleSearch"
                            />
                        </div>

                        <!-- View Mode Toggle -->
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :class="{ 'bg-blue-600 border-blue-600 text-white hover:bg-blue-700': viewMode === 'grid', 'not-dark:border-gray-300 dark:border-gray-600': viewMode !== 'grid' }"
                                @click="viewMode = 'grid'"
                            >
                                <LayoutGrid class="w-4 h-4" />
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :class="{ 'bg-blue-600 border-blue-600 text-white hover:bg-blue-700': viewMode === 'list', 'not-dark:border-gray-300 dark:border-gray-600': viewMode !== 'list' }"
                                @click="viewMode = 'list'"
                            >
                                <List class="w-4 h-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="flex items-center justify-center py-16">
                    <div class="text-center">
                        <Loader class="w-12 h-12 animate-spin text-blue-600 mx-auto mb-4" />
                        <p class="text-lg not-dark:text-gray-600 dark:text-gray-400">Loading shops...</p>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else-if="shops.length === 0" class="text-center py-16">
                    <div class="max-w-md mx-auto">
                        <div class="w-20 h-20 not-dark:bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-6">
                            <Store class="w-10 h-10 not-dark:text-gray-400 dark:text-gray-600" />
                        </div>
                        <h3 class="text-2xl font-bold not-dark:text-gray-900 dark:text-white mb-4">
                            {{ searchQuery ? 'No shops found' : 'No shops yet' }}
                        </h3>
                        <p class="text-lg not-dark:text-gray-600 dark:text-gray-400 mb-6">
                            {{ searchQuery ? 'No shops match your search criteria. Try adjusting your search terms.' : 'Get started by adding your first shop to begin managing your locations.' }}
                        </p>
                        <Button v-if="canEdit && !searchQuery" @click="openAddModal" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200">
                            <Plus class="w-5 h-5 mr-2" />
                            Add Your First Shop
                        </Button>
                    </div>
                </div>

                <!-- Grid View -->
                <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div
                        v-for="shop in shops"
                        :key="shop.id"
                        class="group not-dark:bg-white dark:bg-gray-800 rounded-xl shadow-lg border not-dark:border-gray-200 dark:border-gray-700 p-8 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1"
                    >
                        <!-- Card Header -->
                        <div class="flex items-start justify-between mb-6">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg">
                                    <Store class="w-8 h-8 text-white" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-xl font-bold not-dark:text-gray-900 dark:text-white truncate">
                                        {{ shop.name }}
                                    </h3>
                                    <p class="text-sm not-dark:text-gray-500 dark:text-gray-400 mt-1">
                                        Shop Location
                                    </p>
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
                                <DropdownMenuItem v-if="canEdit" @click="openLayoutEditorModal(shop)" class="cursor-pointer">
                                    <Map class="w-4 h-4 mr-2" />
                                    Edit Layout
                                </DropdownMenuItem>
                                <DropdownMenuItem v-if="canEdit" @click="openDeleteModal(shop)" class="cursor-pointer text-red-600 dark:text-red-400">
                                    <Trash2 class="w-4 h-4 mr-2" />
                                    Delete Shop
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                        <!-- Card Content -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-3 not-dark:text-gray-700 dark:text-gray-300">
                                <div class="w-8 h-8 not-dark:bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                    <MapPin class="w-4 h-4 not-dark:text-gray-500 dark:text-gray-400" />
                                </div>
                                <span class="truncate font-medium">{{ shop.location }}</span>
                            </div>
                            
                            <div v-if="shop.description" class="not-dark:text-gray-600 dark:text-gray-400">
                                <p class="line-clamp-3 leading-relaxed">{{ shop.description }}</p>
                            </div>

                            <div class="flex items-center justify-between pt-4 border-t not-dark:border-gray-100 dark:border-gray-700">
                                <div v-if="shop.dxf_file_path" class="flex items-center gap-2 px-3 py-1 bg-green-50 dark:bg-green-900/20 rounded-full">
                                    <FileText class="w-4 h-4 text-green-600 dark:text-green-400" />
                                    <span class="text-sm text-green-700 dark:text-green-300 font-medium">Layout Available</span>
                                </div>
                                <div v-else class="flex items-center gap-2 px-3 py-1 not-dark:bg-gray-50 dark:bg-gray-800 rounded-full">
                                    <FileText class="w-4 h-4 not-dark:text-gray-400" />
                                    <span class="text-sm not-dark:text-gray-500 dark:text-gray-400">No Layout</span>
                                </div>
                                
                                <!-- Quick Actions -->
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <Button variant="ghost" size="sm" @click="openViewModal(shop)" class="h-8 w-8 p-0 hover:bg-blue-50 dark:hover:bg-blue-900/20">
                                        <Eye class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                    </Button>
                                    <Button v-if="canEdit" variant="ghost" size="sm" @click="openEditModal(shop)" class="h-8 w-8 p-0 hover:bg-green-50 dark:hover:bg-green-900/20">
                                        <Edit class="w-4 h-4 text-green-600 dark:text-green-400" />
                                    </Button>
                                    <Button v-if="canEdit" variant="ghost" size="sm" @click="openLayoutEditorModal(shop)" class="h-8 w-8 p-0 hover:bg-purple-50 dark:hover:bg-purple-900/20">
                                        <Map class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                                    </Button>
                                    <Button v-if="canEdit" variant="ghost" size="sm" @click="openDeleteModal(shop)" class="h-8 w-8 p-0 hover:bg-red-50 dark:hover:bg-red-900/20">
                                        <Trash2 class="w-4 h-4 text-red-600 dark:text-red-400" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- List View -->
                <div v-else class="not-dark:bg-white dark:bg-gray-800 rounded-xl shadow-lg border not-dark:border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="not-dark:bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-8 py-4 text-left text-sm font-semibold not-dark:text-gray-700 dark:text-gray-300 tracking-wide">
                                    Shop Information
                                </th>
                                <th class="px-8 py-4 text-left text-sm font-semibold not-dark:text-gray-700 dark:text-gray-300 tracking-wide">
                                    Location
                                </th>
                                <th class="px-8 py-4 text-left text-sm font-semibold not-dark:text-gray-700 dark:text-gray-300 tracking-wide">
                                    Coordinates
                                </th>
                                <th class="px-8 py-4 text-center text-sm font-semibold not-dark:text-gray-700 dark:text-gray-300 tracking-wide">
                                    Layout Status
                                </th>
                                <th class="px-8 py-4 text-center text-sm font-semibold not-dark:text-gray-700 dark:text-gray-300 tracking-wide">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y not-dark:divide-gray-200 dark:divide-gray-700">
                            <tr v-for="shop in shops" :key="shop.id" class="hover:not-dark:bg-blue-50 hover:dark:bg-gray-700/50 transition-colors duration-200">
                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-md">
                                            <Store class="w-6 h-6 text-white" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-lg font-semibold not-dark:text-gray-900 dark:text-white">
                                                {{ shop.name }}
                                            </div>
                                            <div v-if="shop.description" class="text-sm not-dark:text-gray-500 dark:text-gray-400 truncate max-w-sm mt-1">
                                                {{ shop.description }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-2">
                                        <MapPin class="w-4 h-4 not-dark:text-gray-400" />
                                        <span class="text-sm font-medium not-dark:text-gray-900 dark:text-white">{{ shop.location }}</span>
                                    </div>
                                </td>
                                <td class="px-8 py-6 text-sm not-dark:text-gray-600 dark:text-gray-400">
                                    <span v-if="shop.latitude && shop.longitude" class="font-mono">
                                        {{ formatCoordinate(shop.latitude, 4) }}, {{ formatCoordinate(shop.longitude, 4) }}
                                    </span>
                                    <span v-else class="not-dark:text-gray-400">Not set</span>
                                </td>
                                <td class="px-8 py-6 text-center">
                                    <span v-if="shop.dxf_file_path" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                        <FileText class="w-4 h-4 mr-2" />
                                        Available
                                    </span>
                                    <span v-else class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium not-dark:bg-gray-100 not-dark:text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                        <FileText class="w-4 h-4 mr-2" />
                                        None
                                    </span>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex items-center justify-center gap-2">
                                        <Button variant="ghost" size="sm" @click="openViewModal(shop)" class="h-9 w-9 p-0 hover:bg-blue-50 dark:hover:bg-blue-900/20">
                                            <Eye class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                        </Button>
                                        <Button v-if="canEdit" variant="ghost" size="sm" @click="openEditModal(shop)" class="h-9 w-9 p-0 hover:bg-green-50 dark:hover:bg-green-900/20">
                                            <Edit class="w-4 h-4 text-green-600 dark:text-green-400" />
                                        </Button>
                                        <Button v-if="canEdit" variant="ghost" size="sm" @click="openLayoutEditorModal(shop)" class="h-9 w-9 p-0 hover:bg-purple-50 dark:hover:bg-purple-900/20">
                                            <Map class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                                        </Button>
                                        <Button v-if="canEdit" variant="ghost" size="sm" @click="openDeleteModal(shop)" class="h-9 w-9 p-0 hover:bg-red-50 dark:hover:bg-red-900/20">
                                            <Trash2 class="w-4 h-4 text-red-600 dark:text-red-400" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

                        <!-- Pagination -->
                        <div v-if="pagination.lastPage > 1" class="not-dark:bg-white dark:bg-gray-700 rounded-lg shadow border not-dark:border-gray-200 dark:border-gray-600 p-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm not-dark:text-gray-700 dark:text-gray-300">
                            Showing {{ ((pagination.currentPage - 1) * pagination.perPage) + 1 }} to 
                            {{ Math.min(pagination.currentPage * pagination.perPage, pagination.total) }} of 
                            {{ pagination.total }} results
                        </div>
                        <div class="flex items-center gap-3">
                            <Button 
                                variant="outline" 
                                size="sm" 
                                :disabled="pagination.currentPage <= 1"
                                @click="handlePageChange(pagination.currentPage - 1)"
                                class="px-4 py-2 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                Previous
                            </Button>
                            <span class="px-3 py-1 text-sm not-dark:text-gray-500 dark:text-gray-400">
                                Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
                            </span>
                            <Button 
                                variant="outline" 
                                size="sm" 
                                :disabled="pagination.currentPage >= pagination.lastPage"
                                @click="handlePageChange(pagination.currentPage + 1)"
                                class="px-4 py-2 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                        </div>
                    </div>
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
            :shop="selectedShop as any"
        />
        
        <ShopDeleteModal
            v-model:open="showDeleteModal"
            :shop="selectedShop as any"
            @confirmed="deleteShop"
        />
        
        <ShopLayoutEditorModal
            v-model:open="showLayoutEditorModal"
            :shop="selectedShop as any"
            @saved="onLayoutSaved"
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
