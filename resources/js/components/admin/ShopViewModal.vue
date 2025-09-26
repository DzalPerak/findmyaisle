<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { MapPin, FileText, Calendar, Store, ExternalLink } from 'lucide-vue-next';

interface Shop {
    id: number;
    name: string;
    location: string;
    latitude?: number | string;
    longitude?: number | string;
    description?: string;
    dxf_file_path?: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    open: boolean;
    shop?: Shop | null;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
}

const props = withDefaults(defineProps<Props>(), {
    shop: null
});

const emit = defineEmits<Emits>();

// Computed
const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value)
});

const formattedCreatedDate = computed(() => {
    if (!props.shop?.created_at) return '';
    return new Date(props.shop.created_at).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
});

const googleMapsUrl = computed(() => {
    if (!props.shop?.latitude || !props.shop?.longitude) return '';
    return `https://www.google.com/maps?q=${props.shop.latitude},${props.shop.longitude}`;
});

const hasCoordinates = computed(() => {
    return props.shop?.latitude !== undefined && props.shop?.longitude !== undefined;
});

const formattedLatitude = computed(() => {
    if (!props.shop?.latitude) return '';
    const lat = typeof props.shop.latitude === 'string' ? parseFloat(props.shop.latitude) : props.shop.latitude;
    return lat.toFixed(6);
});

const formattedLongitude = computed(() => {
    if (!props.shop?.longitude) return '';
    const lng = typeof props.shop.longitude === 'string' ? parseFloat(props.shop.longitude) : props.shop.longitude;
    return lng.toFixed(6);
});
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Store class="w-5 h-5 text-blue-600" />
                    {{ shop?.name }}
                </DialogTitle>
                <DialogDescription>
                    Shop details and information
                </DialogDescription>
            </DialogHeader>

            <div v-if="shop" class="space-y-6">
                <!-- Basic Information -->
                <div class="space-y-4">
                    <div>
                        <h4 class="text-sm font-medium not-dark:text-gray-900 dark:text-white mb-2">Location</h4>
                        <div class="flex items-start gap-2">
                            <MapPin class="w-4 h-4 not-dark:text-gray-400 dark:text-gray-500 mt-0.5 flex-shrink-0" />
                            <span class="text-sm not-dark:text-gray-600 dark:text-gray-400">{{ shop.location }}</span>
                        </div>
                    </div>

                    <div v-if="shop.description">
                        <h4 class="text-sm font-medium not-dark:text-gray-900 dark:text-white mb-2">Description</h4>
                        <p class="text-sm not-dark:text-gray-600 dark:text-gray-400 leading-relaxed">
                            {{ shop.description }}
                        </p>
                    </div>

                    <!-- Coordinates -->
                    <div v-if="hasCoordinates">
                        <h4 class="text-sm font-medium not-dark:text-gray-900 dark:text-white mb-2">Coordinates</h4>
                        <div class="flex items-center justify-between">
                            <div class="text-sm not-dark:text-gray-600 dark:text-gray-400">
                                Lat: {{ formattedLatitude }}, Lng: {{ formattedLongitude }}
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                as-child
                                class="text-xs"
                            >
                                <a :href="googleMapsUrl" target="_blank" rel="noopener noreferrer">
                                    <ExternalLink class="w-3 h-3 mr-1" />
                                    Open in Maps
                                </a>
                            </Button>
                        </div>
                    </div>

                    <!-- Layout File -->
                    <div v-if="shop.dxf_file_path">
                        <h4 class="text-sm font-medium not-dark:text-gray-900 dark:text-white mb-2">Layout File</h4>
                        <div class="flex items-center gap-2 p-3 not-dark:bg-green-50 dark:bg-green-900/30 border not-dark:border-green-200 dark:border-green-800 rounded-lg">
                            <FileText class="w-4 h-4 text-green-600 dark:text-green-400" />
                            <span class="text-sm text-green-700 dark:text-green-300 truncate">
                                {{ shop.dxf_file_path.split('/').pop() }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Metadata -->
                <div class="pt-4 border-t not-dark:border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2 text-xs not-dark:text-gray-500 dark:text-gray-400">
                        <Calendar class="w-3 h-3" />
                        <span>Created {{ formattedCreatedDate }}</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end">
                    <Button @click="isOpen = false">
                        Close
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>