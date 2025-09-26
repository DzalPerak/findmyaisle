<script setup lang="ts">
import { defineProps } from 'vue';
import type { Component } from 'vue';

const props = defineProps<{
    to: string;
    icon: Component;
    name: string;
    description?: string;
    color?: string;
}>();
</script>

<template>
    <a
        :href="props.to"
        class="group relative overflow-hidden not-dark:bg-white dark:bg-gray-800 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 border not-dark:border-gray-200 dark:border-gray-700"
    >
        <!-- Background Gradient -->
        <div class="absolute inset-0 bg-gradient-to-br from-transparent to-gray-50 dark:to-gray-800/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
        
        <!-- Content -->
        <div class="relative p-6">
            <!-- Icon Container -->
            <div :class="[
                'w-12 h-12 rounded-lg flex items-center justify-center mb-4 transition-all duration-300',
                props.color || 'bg-blue-500 hover:bg-blue-600'
            ]">
                <component 
                    :is="props.icon" 
                    class="w-6 h-6 text-white"
                />
            </div>
            
            <!-- Title -->
            <h3 class="text-xl font-semibold not-dark:text-gray-900 dark:text-white mb-2 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                {{ props.name }}
            </h3>
            
            <!-- Description -->
            <p v-if="props.description" class="not-dark:text-gray-600 dark:text-gray-300 text-sm leading-relaxed">
                {{ props.description }}
            </p>
            
            <!-- Arrow Icon -->
            <div class="absolute top-4 right-4 w-8 h-8 rounded-full not-dark:bg-gray-100 dark:bg-gray-700 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-x-2 group-hover:translate-x-0">
                <svg class="w-4 h-4 not-dark:text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </div>
        </div>
        
        <!-- Bottom Border Accent -->
        <div :class="[
            'absolute bottom-0 left-0 right-0 h-1 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300',
            props.color || 'bg-blue-500'
        ]"></div>
    </a>
</template>