<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { AlertTriangle, Loader } from 'lucide-vue-next';

interface Shop {
    id: number;
    name: string;
    location: string;
    latitude?: number;
    longitude?: number;
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
    (e: 'confirmed', shop: Shop): void;
}

const props = withDefaults(defineProps<Props>(), {
    shop: null
});

const emit = defineEmits<Emits>();

// State
const loading = ref(false);

// Computed
const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value)
});

// Methods
const handleConfirm = () => {
    if (props.shop) {
        emit('confirmed', props.shop);
    }
};

const handleCancel = () => {
    if (!loading.value) {
        isOpen.value = false;
    }
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-red-600 dark:text-red-400">
                    <AlertTriangle class="w-5 h-5" />
                    Delete Shop
                </DialogTitle>
                <DialogDescription>
                    This action cannot be undone. This will permanently delete the shop and remove all associated data.
                </DialogDescription>
            </DialogHeader>

            <div v-if="shop" class="my-4">
                <div class="p-4 not-dark:bg-gray-50 dark:bg-gray-800 rounded-lg border not-dark:border-gray-200 dark:border-gray-700">
                    <h4 class="font-medium not-dark:text-gray-900 dark:text-white">{{ shop.name }}</h4>
                    <p class="text-sm not-dark:text-gray-600 dark:text-gray-400 mt-1">{{ shop.location }}</p>
                    <div v-if="shop.dxf_file_path" class="text-xs not-dark:text-gray-500 dark:text-gray-400 mt-2">
                        ⚠️ This will also delete the associated layout file
                    </div>
                </div>
            </div>

            <DialogFooter class="flex gap-3">
                <Button 
                    variant="outline" 
                    @click="handleCancel"
                    :disabled="loading"
                >
                    Cancel
                </Button>
                <Button 
                    variant="destructive"
                    @click="handleConfirm"
                    :disabled="loading"
                    class="min-w-24"
                >
                    <Loader v-if="loading" class="w-4 h-4 mr-2 animate-spin" />
                    Delete Shop
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>