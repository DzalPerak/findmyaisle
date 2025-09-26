<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';

// Breadcrumbs  
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Route Planning', href: '/plan-route' }
];
</script>

<template>
    <Head title="Plan Route" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <!-- Page Header -->
            <div class="bg-white not-dark:bg-white dark:bg-gray-900 shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h1 class="text-2xl font-bold not-dark:text-gray-900 dark:text-white">
                        Plan Your Shopping Route
                    </h1>
                    <p class="mt-2 not-dark:text-gray-600 dark:text-gray-400">
                        Select a shop and plan your optimal route through the store by adding waypoints to visit.
                    </p>
                </div>
            </div>

            <!-- Redirect Notice -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                <div class="text-center">
                    <h2 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">
                        Route Planning Has Moved!
                    </h2>
                    <p class="text-blue-700 dark:text-blue-300 mb-4">
                        The route planning feature is now integrated with shop selection. 
                        Please visit the Shops page to browse available shops and plan your route.
                    </p>
                    <a 
                        href="/shops" 
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors"
                    >
                        Browse Shops & Plan Routes
                    </a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>