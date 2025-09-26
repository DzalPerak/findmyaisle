<script setup lang="ts">
import AdminMenuButton from '@/components/AdminMenuButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Store, Shield, Map, Settings, Users, BarChart3 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useUserRoles } from '@/composables/useUserRoles';

// Get user roles and helper functions
const { user, hasAnyRole, getRoleDisplayNames } = useUserRoles();

// Helper function to check if user has required roles
const hasRequiredRole = (requiredRoles?: string[]) => {
    if (!requiredRoles || requiredRoles.length === 0) return true;
    return hasAnyRole(requiredRoles);
};

// Define all possible admin menu items with role requirements
const allAdminMenuItems = [
    { 
        name: 'Shops', 
        to: '/admin/shops', 
        icon: Store,
        description: 'Manage shop locations and information',
        color: 'bg-blue-500 hover:bg-blue-600',
        requiredRoles: ['admin', 'editor'] // Both admin and editor can manage shops
    },
    { 
        name: 'Roles', 
        to: '/admin/roles', 
        icon: Shield,
        description: 'Configure user roles and permissions',
        color: 'bg-green-500 hover:bg-green-600',
        requiredRoles: ['admin'] // Only admin can manage roles
    },
    { 
        name: 'Map Editor', 
        to: '/admin/map-editor', 
        icon: Map,
        description: 'Edit and customize store layouts',
        color: 'bg-purple-500 hover:bg-purple-600',
        requiredRoles: ['admin', 'editor'] // Both admin and editor can edit maps
    },
    { 
        name: 'Settings', 
        to: '/admin/settings', 
        icon: Settings,
        description: 'System configuration and preferences',
        color: 'bg-gray-500 hover:bg-gray-600',
        requiredRoles: ['admin'] // Only admin can access system settings
    },
    { 
        name: 'Users', 
        to: '/admin/users', 
        icon: Users,
        description: 'Manage user accounts and profiles',
        color: 'bg-orange-500 hover:bg-orange-600',
        requiredRoles: ['admin'] // Only admin can manage users
    },
    { 
        name: 'Analytics', 
        to: '/admin/analytics', 
        icon: BarChart3,
        description: 'View usage statistics and reports',
        color: 'bg-red-500 hover:bg-red-600',
        requiredRoles: ['admin', 'editor'] // Both admin and editor can view analytics
    }
];

// Filter menu items based on user roles
const adminMenuItems = computed(() => {
    return allAdminMenuItems.filter(item => hasRequiredRole(item.requiredRoles));
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin',
        href: "/admin"
    },
];
</script>

<template>

    <Head title="Admin Menu" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen not-dark:bg-gray-50 dark:bg-gray-900 py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="text-center mb-10">
                    <h1 class="text-4xl font-bold not-dark:text-gray-900 dark:text-white mb-4">
                        Admin Dashboard
                    </h1>
                    <p class="text-lg not-dark:text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                        Manage your ShopNavi system with comprehensive administrative tools
                    </p>
                </div>

                <!-- User Role Info -->
                <div v-if="user?.roles?.length" class="mb-8 text-center">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-blue-100 dark:bg-blue-900/30 rounded-full text-sm">
                        <Shield class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                        <span class="not-dark:text-blue-800 dark:text-blue-300">
                            Logged in as: 
                            <span class="font-semibold">
                                {{ getRoleDisplayNames.join(', ') }}
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Admin Menu Grid -->
                <div v-if="adminMenuItems.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <AdminMenuButton 
                        v-for="item in adminMenuItems" 
                        :key="item.name" 
                        :to="item.to" 
                        :icon="item.icon"
                        :name="item.name" 
                        :description="item.description"
                        :color="item.color"
                    />
                </div>

                <!-- No Access Message -->
                <div v-else class="text-center py-12">
                    <div class="max-w-md mx-auto">
                        <Shield class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4" />
                        <h3 class="text-xl font-semibold not-dark:text-gray-900 dark:text-white mb-2">
                            Access Restricted
                        </h3>
                        <p class="not-dark:text-gray-600 dark:text-gray-400 mb-4">
                            You don't have permission to access any admin features.
                        </p>
                        <p class="text-sm not-dark:text-gray-500 dark:text-gray-500">
                            Contact your administrator if you believe this is an error.
                        </p>
                    </div>
                </div>

                <!-- Quick Stats Section -->
                <div class="mt-12 grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="not-dark:bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center">
                        <div class="text-2xl font-bold not-dark:text-gray-900 dark:text-white">12</div>
                        <div class="text-sm not-dark:text-gray-500 dark:text-gray-400">Total Shops</div>
                    </div>
                    <div class="not-dark:bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center">
                        <div class="text-2xl font-bold not-dark:text-gray-900 dark:text-white">324</div>
                        <div class="text-sm not-dark:text-gray-500 dark:text-gray-400">Active Users</div>
                    </div>
                    <div class="not-dark:bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center">
                        <div class="text-2xl font-bold not-dark:text-gray-900 dark:text-white">8</div>
                        <div class="text-sm not-dark:text-gray-500 dark:text-gray-400">Maps Created</div>
                    </div>
                    <div class="not-dark:bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center">
                        <div class="text-2xl font-bold not-dark:text-gray-900 dark:text-white">1.2k</div>
                        <div class="text-sm not-dark:text-gray-500 dark:text-gray-400">Navigations</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

</template>