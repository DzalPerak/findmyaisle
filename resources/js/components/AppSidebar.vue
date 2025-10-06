<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { dashboard, admin } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Settings, Users, BarChart3 } from 'lucide-vue-next';
import { useUserRoles } from '@/composables/useUserRoles';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    // {
    //     title: 'Dashboard',
    //     href: dashboard(),
    //     icon: LayoutGrid,
    // },    
    // {
    //     title: 'Navi',
    //     href: "/navi",
    //     icon: LayoutGrid,
    // },    
    {
        title: 'Shopping Lists',
        href: "/shoppinglist",
        icon: LayoutGrid,
    },
    {
        title: 'Shops',
        href: "/shops",
        icon: LayoutGrid,
    },
];

// Get user role information
const { canAccessAdmin, isAdmin } = useUserRoles();

// Base footer items available to all users
const baseFooterItems: NavItem[] = [
    // {
    //     title: 'Github Repo',
    //     href: 'https://github.com/DzalPerak/findmyaisle',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#vue',
    //     icon: BookOpen,
    // },
];

// Admin-only footer items
const adminFooterItems: NavItem[] = [
    // {
    //     title: 'Admin Panel',
    //     href: '/admin',
    //     icon: Settings,
    // }
];

// Admin-only items (admin role required, not just editor)
const superAdminItems: NavItem[] = [
    {
        title: 'Admin Panel',
        href: '/admin',
        icon: Settings,
    }
];

// Combine footer items based on user roles
const footerNavItems = computed((): NavItem[] => {
    const items = [...baseFooterItems];
    
    if (canAccessAdmin.value) {
        items.unshift(...adminFooterItems);
    }
    
    if (isAdmin.value) {
        items.splice(-2, 0, ...superAdminItems); // Insert before the last 2 items
    }
    
    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
