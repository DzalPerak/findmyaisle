<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import Pathfinder from '@/components/Pathfinder.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Navi',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Navi" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <PlaceholderPattern class="absolute inset-0 -z-10 h-full w-full" />
            <Pathfinder />
        </div>
    </AppLayout>
</template>
