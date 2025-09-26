<script setup lang="ts">
import { onMounted, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PathfinderComponent from '@/components/Pathfinder.vue';
import { usePage } from '@inertiajs/vue3';

interface Shop {
    id: number;
    name: string;
    location: string;
    dxf_file_path?: string;
}

// Get shop ID from URL parameters if coming from shop selection
const page = usePage();
const urlParams = new URLSearchParams(window.location.search);
const shopId = ref<number | null>(null);
const selectedShop = ref<Shop | null>(null);
const isUserMode = ref(false);

// Check if we have a shop parameter (coming from shop selection)
onMounted(async () => {
    const shopParam = urlParams.get('shop');
    if (shopParam) {
        shopId.value = parseInt(shopParam);
        isUserMode.value = true;
        
        // Load shop details
        try {
            const response = await fetch(`/api/shops/${shopId.value}`);
            if (response.ok) {
                selectedShop.value = await response.json();
            }
        } catch (error) {
            console.error('Failed to load shop details:', error);
        }
    }
});
</script>

<template>
    <AppLayout title="Pathfinder">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ selectedShop ? `Route Planning - ${selectedShop.name}` : 'Pathfinder' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Shop info banner when in user mode -->
                <div v-if="selectedShop" class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex items-center space-x-3">
                        <div class="bg-blue-100 dark:bg-blue-900 rounded-full p-2">
                            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-2 0H3m2 0h2M9 7h6m-6 4h6m-6 4h6"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100">{{ selectedShop.name }}</h3>
                            <p class="text-sm text-blue-700 dark:text-blue-300">{{ selectedShop.location }}</p>
                        </div>
                    </div>
                </div>

                <!-- Pathfinder Component -->
                <PathfinderComponent 
                    :shop-id="shopId"
                    :is-user-mode="isUserMode"
                />
            </div>
        </div>
    </AppLayout>
</template>