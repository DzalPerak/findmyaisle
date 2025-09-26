# Role-Based Admin Menu System

This implementation provides a comprehensive role-based access control system for the ShopNavi admin interface.

## 📋 Overview

The system includes three default roles with different permission levels:
- **User**: Basic access (cannot access admin features)  
- **Editor**: Can manage shops, maps, and view analytics
- **Admin**: Full system access including user management, roles, and settings

## 🔧 System Components

### 1. Database Structure
- `roles` table: Stores role definitions (user, editor, admin)
- `user_roles` table: Many-to-many relationship between users and roles
- Roles are automatically seeded during migration

### 2. Backend Models & Middleware
- `User` model: Includes role relationship methods (`hasRole`, `hasAnyRole`, `getRoleNamesAttribute`)
- `Role` model: Basic role model with user relationship
- `CheckRole` middleware: Protects routes based on required roles
- Routes in `routes/admin.php` are protected with role-specific middleware

### 3. Frontend Implementation
- `useUserRoles` composable: Provides role checking utilities
- Admin menu items are filtered based on user roles
- Visual indicators show current user roles
- Graceful fallback for users without admin access

## 🎯 Menu Visibility Rules

| Menu Item | Admin | Editor | User |
|-----------|-------|--------|------|
| Shops | ✅ | ✅ | ❌ |
| Map Editor | ✅ | ✅ | ❌ |
| Analytics | ✅ | ✅ | ❌ |
| Roles | ✅ | ❌ | ❌ |
| Users | ✅ | ❌ | ❌ |
| Settings | ✅ | ❌ | ❌ |

## 🧪 Test Users

The system includes pre-seeded test users for testing:

```bash
php artisan db:seed --class=UserRoleSeeder
```

- **admin@shopnavi.com** (password: password) - Admin role
- **editor@shopnavi.com** (password: password) - Editor role  
- **user@shopnavi.com** (password: password) - User role

## 🔒 Route Protection

Routes are protected on both frontend and backend:

```php
// Example: Admin-only route
Route::get('/admin/users', function () {
    return Inertia::render('admin/Users');
})->middleware('role:admin');

// Example: Admin or Editor route  
Route::get('/admin/shops', function () {
    return Inertia::render('admin/Shops');
})->middleware('role:admin,editor');
```

## 🎨 Frontend Usage

### Using the composable:
```vue
<script setup>
import { useUserRoles } from '@/composables/useUserRoles';

const { isAdmin, isEditor, canAccessAdmin, hasRole } = useUserRoles();
</script>

<template>
  <div v-if="isAdmin">Admin only content</div>
  <div v-if="canAccessAdmin">Admin or Editor content</div>
  <div v-if="hasRole('editor')">Editor specific content</div>
</template>
```

### Adding new roles:
1. Add the role to the database via migration or seeder
2. Update menu items in `MainAdminMenu.vue` with `requiredRoles` 
3. Add route protection in `routes/admin.php`
4. Optionally add helper methods to `useUserRoles` composable

## 🚀 How It Works

1. **Authentication**: User logs in and roles are loaded via `HandleInertiaRequests`
2. **Frontend Filtering**: Menu items are filtered based on user roles in real-time
3. **Backend Protection**: Routes are protected via `CheckRole` middleware
4. **User Experience**: Clear visual feedback about access levels and permissions

The system ensures users only see and can access features appropriate to their role level!