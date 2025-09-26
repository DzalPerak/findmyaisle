<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Search, MapPin, Navigation, Building2 } from 'lucide-vue-next';

interface Shop {
    id: number;
    name: string;
    location: string;
    dxf_file_path?: string;
}

// Reactive data
const shops = ref<Shop[]>([]);
const isLoading = ref(false);
const searchQuery = ref('');
const viewMode = ref<'grid' | 'list'>('grid');

// Computed properties
const filteredShops = computed(() => {
    if (!searchQuery.value) return shops.value;
    
    const query = searchQuery.value.toLowerCase();
    return shops.value.filter(shop => 
        shop.name.toLowerCase().includes(query) || 
        shop.location.toLowerCase().includes(query)
    );
});

// Methods
const loadShops = async () => {
    isLoading.value = true;
    try {
        const response = await fetch('/api/shops/available');
        if (response.ok) {
            shops.value = await response.json();
        } else {
            console.error('Failed to load shops');
        }
    } catch (error) {
        console.error('Error loading shops:', error);
    } finally {
        isLoading.value = false;
    }
};

const selectShop = (shop: Shop) => {
    // Navigate to pathfinder with shop pre-selected
    router.visit(`/pathfinder?shop=${shop.id}`);
};

onMounted(() => {
    loadShops();
});
</script>

<template>
    <AppLayout title="Select Shop">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Select Shop for Route Planning
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white not-dark:bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 lg:p-8">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-8">
                            <div class="flex items-center space-x-3">
                                <Building2 class="h-8 w-8 text-blue-600" />
                                <div>
                                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Choose Your Store</h1>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Select a store to plan your shopping route</p>
                                </div>
                            </div>
                        </div>

                        <!-- Search and View Controls -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 space-y-4 sm:space-y-0">
                            <!-- Search Bar -->
                            <div class="relative flex-1 max-w-md">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <Search class="h-5 w-5 text-gray-400" />
                                </div>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search shops..."
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                />
                            </div>

                            <!-- View Mode Toggle -->
                            <div class="flex rounded-lg border border-gray-200 dark:border-gray-600">
                                <button
                                    @click="viewMode = 'grid'"
                                    :class="[
                                        'px-4 py-2 text-sm font-medium rounded-l-lg transition-colors',
                                        viewMode === 'grid'
                                            ? 'bg-blue-600 text-white'
                                            : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300'
                                    ]"
                                >
                                    Grid
                                </button>
                                <button
                                    @click="viewMode = 'list'"
                                    :class="[
                                        'px-4 py-2 text-sm font-medium rounded-r-lg border-l transition-colors',
                                        viewMode === 'list'
                                            ? 'bg-blue-600 text-white border-blue-600'
                                            : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600'
                                    ]"
                                >
                                    List
                                </button>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div v-if="isLoading" class="text-center py-12">
                            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                            <p class="text-gray-600 dark:text-gray-400">Loading available shops...</p>
                        </div>

                        <!-- No Results -->
                        <div v-else-if="filteredShops.length === 0" class="text-center py-12">
                            <Building2 class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                                {{ searchQuery ? 'No matching shops found' : 'No shops available' }}
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400">
                                {{ searchQuery ? 'Try adjusting your search terms' : 'Please check back later' }}
                            </p>
                        </div>

                        <!-- Grid View -->
                        <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div
                                v-for="shop in filteredShops"
                                :key="shop.id"
                                @click="selectShop(shop)"
                                class="bg-white not-dark:bg-white dark:bg-gray-700 rounded-lg shadow-md hover:shadow-lg transition-shadow cursor-pointer border border-gray-200 dark:border-gray-600 p-6"
                            >
                                <div class="flex items-center space-x-3 mb-4">
                                    <div class="bg-blue-100 dark:bg-blue-900 rounded-full p-2">
                                        <Building2 class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ shop.name }}</h3>
                                </div>
                                
                                <div class="flex items-start space-x-2 mb-4">
                                    <MapPin class="h-4 w-4 text-gray-400 mt-0.5 flex-shrink-0" />
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ shop.location }}</p>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        {{ shop.dxf_file_path ? 'Map Available' : 'Map Unavailable' }}
                                    </span>
                                    <Navigation class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                </div>
                            </div>
                        </div>

                        <!-- List View -->
                        <div v-else class="bg-white not-dark:bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-md">
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                <li
                                    v-for="shop in filteredShops"
                                    :key="shop.id"
                                    @click="selectShop(shop)"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                                >
                                    <div class="px-6 py-4">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-4">
                                                <div class="bg-blue-100 dark:bg-blue-900 rounded-full p-2">
                                                    <Building2 class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                                </div>
                                                <div>
                                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ shop.name }}</h3>
                                                    <div class="flex items-center space-x-2 mt-1">
                                                        <MapPin class="h-4 w-4 text-gray-400" />
                                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ shop.location }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center space-x-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    {{ shop.dxf_file_path ? 'Map Available' : 'Map Unavailable' }}
                                                </span>
                                                <Navigation class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
