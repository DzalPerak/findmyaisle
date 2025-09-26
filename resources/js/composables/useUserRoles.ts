import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { User, Role } from '@/types';

export function useUserRoles() {
    const page = usePage();
    
    const user = computed<User | null>(() => page.props.auth.user);
    
    const userRoles = computed<string[]>(() => {
        return user.value?.roles?.map(role => role.name) ?? [];
    });
    
    const hasRole = (roleName: string): boolean => {
        return userRoles.value.includes(roleName);
    };
    
    const hasAnyRole = (roleNames: string[]): boolean => {
        return roleNames.some(role => userRoles.value.includes(role));
    };
    
    const hasAllRoles = (roleNames: string[]): boolean => {
        return roleNames.every(role => userRoles.value.includes(role));
    };
    
    const isAdmin = computed(() => hasRole('admin'));
    const isEditor = computed(() => hasRole('editor'));
    const isUser = computed(() => hasRole('user'));
    
    const canAccessAdmin = computed(() => hasAnyRole(['admin', 'editor']));
    
    const getRoleDisplayNames = computed(() => {
        return userRoles.value.map(role => 
            role.charAt(0).toUpperCase() + role.slice(1)
        );
    });
    
    return {
        user,
        userRoles,
        hasRole,
        hasAnyRole,
        hasAllRoles,
        isAdmin,
        isEditor,  
        isUser,
        canAccessAdmin,
        getRoleDisplayNames
    };
}