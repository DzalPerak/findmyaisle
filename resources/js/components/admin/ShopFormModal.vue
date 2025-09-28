<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { notify } from '@/utils/notifications';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Loader, Upload, X } from 'lucide-vue-next';

interface Shop {
    id?: number;
    name: string;
    location: string;
    latitude?: number | string;
    longitude?: number | string;
    description?: string;
    dxf_file_path?: string;
}

interface Props {
    open: boolean;
    shop?: Shop | null;
    mode: 'add' | 'edit';
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}

const props = withDefaults(defineProps<Props>(), {
    shop: null,
    mode: 'add'
});

const emit = defineEmits<Emits>();

// Form state
const loading = ref(false);
const form = ref<Shop>({
    name: '',
    location: '',
    latitude: undefined,
    longitude: undefined,
    description: '',
});

const selectedFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const errors = ref<Record<string, string[]>>({});

// Computed
const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value)
});

const isEditing = computed(() => props.mode === 'edit');
const title = computed(() => isEditing.value ? 'Edit Shop' : 'Add New Shop');
const submitText = computed(() => isEditing.value ? 'Update Shop' : 'Create Shop');

// Watchers
watch(() => props.open, (open) => {
    if (open) {
        resetForm();
        if (props.shop && isEditing.value) {
            form.value = {
                name: props.shop.name,
                location: props.shop.location,
                latitude: props.shop.latitude,
                longitude: props.shop.longitude,
                description: props.shop.description || '',
            };
        }
    }
});

// Methods
const resetForm = () => {
    form.value = {
        name: '',
        location: '',
        latitude: undefined,
        longitude: undefined,
        description: '',
    };
    selectedFile.value = null;
    errors.value = {};
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    
    if (file) {
        // Validate file type
        if (!file.name.toLowerCase().endsWith('.dxf')) {
            errors.value.dxf_file = ['Please select a valid DXF file.'];
            return;
        }
        
        // Validate file size (10MB max)
        if (file.size > 10 * 1024 * 1024) {
            errors.value.dxf_file = ['File size must be less than 10MB.'];
            return;
        }
        
        selectedFile.value = file;
        errors.value.dxf_file = [];
    }
};

const removeFile = () => {
    selectedFile.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
    errors.value.dxf_file = [];
};

const validateForm = (): boolean => {
    const newErrors: Record<string, string[]> = {};
    
    if (!form.value.name.trim()) {
        newErrors.name = ['Shop name is required.'];
    }
    
    if (!form.value.location.trim()) {
        newErrors.location = ['Location is required.'];
    }
    
    if (form.value.latitude !== undefined) {
        const lat = typeof form.value.latitude === 'string' ? parseFloat(form.value.latitude) : form.value.latitude;
        if (!isNaN(lat) && (lat < -90 || lat > 90)) {
            newErrors.latitude = ['Latitude must be between -90 and 90.'];
        }
    }
    
    if (form.value.longitude !== undefined) {
        const lng = typeof form.value.longitude === 'string' ? parseFloat(form.value.longitude) : form.value.longitude;
        if (!isNaN(lng) && (lng < -180 || lng > 180)) {
            newErrors.longitude = ['Longitude must be between -180 and 180.'];
        }
    }
    
    errors.value = newErrors;
    return Object.keys(newErrors).length === 0;
};

const submitForm = async () => {
    if (!validateForm()) return;
    
    loading.value = true;
    errors.value = {};
    
    try {
        const formData = new FormData();
        formData.append('name', form.value.name);
        formData.append('location', form.value.location);
        
        if (form.value.latitude !== undefined) {
            formData.append('latitude', form.value.latitude.toString());
        }
        
        if (form.value.longitude !== undefined) {
            formData.append('longitude', form.value.longitude.toString());
        }
        
        if (form.value.description) {
            formData.append('description', form.value.description);
        }
        
        if (selectedFile.value) {
            formData.append('dxf_file', selectedFile.value);
        }

        const url = isEditing.value ? `/api/shops/${props.shop!.id}` : '/api/shops';
        
        // For PUT requests with FormData, we need to use POST with _method
        if (isEditing.value) {
            formData.append('_method', 'PUT');
        }

        // Use Inertia router for proper CSRF handling
        router.post(url, formData, {
            forceFormData: true,
            onSuccess: (page) => {
                // Show success notification
                const actionText = isEditing.value ? 'updated' : 'created';
                notify.success(
                    `Shop ${actionText} successfully`,
                    `${form.value.name} has been ${actionText}.`
                );
                emit('saved');
                resetForm();
            },
            onError: (errorData) => {
                if (errorData) {
                    // Convert Inertia errors to our format
                    const formattedErrors: Record<string, string[]> = {};
                    Object.keys(errorData).forEach(key => {
                        const value = errorData[key];
                        formattedErrors[key] = Array.isArray(value) ? value : [value];
                    });
                    errors.value = formattedErrors;
                } else {
                    errors.value = { general: ['An error occurred while saving the shop.'] };
                }
            },
            onFinish: () => {
                loading.value = false;
            }
        });
        
    } catch (error) {
        console.error('Error saving shop:', error);
        errors.value = { general: ['Network error. Please try again.'] };
        loading.value = false;
    }
};

const handleClose = () => {
    if (!loading.value) {
        isOpen.value = false;
        nextTick(() => resetForm());
    }
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    {{ isEditing ? 'Update the shop information below.' : 'Fill in the details to create a new shop.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitForm" class="space-y-4">
                <!-- General Error -->
                <div v-if="errors.general" class="p-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded-md dark:bg-red-900/30 dark:border-red-800 dark:text-red-400">
                    {{ errors.general[0] }}
                </div>

                <!-- Shop Name -->
                <div class="space-y-2">
                    <Label for="name">Shop Name *</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="Enter shop name"
                        :disabled="loading"
                        :class="{ 'border-red-300 focus:border-red-500': errors.name }"
                    />
                    <p v-if="errors.name" class="text-xs text-red-600 dark:text-red-400">
                        {{ errors.name[0] }}
                    </p>
                </div>

                <!-- Location -->
                <div class="space-y-2">
                    <Label for="location">Location *</Label>
                    <Input
                        id="location"
                        v-model="form.location"
                        placeholder="Enter shop location/address"
                        :disabled="loading"
                        :class="{ 'border-red-300 focus:border-red-500': errors.location }"
                    />
                    <p v-if="errors.location" class="text-xs text-red-600 dark:text-red-400">
                        {{ errors.location[0] }}
                    </p>
                </div>

                <!-- Coordinates -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="latitude">Latitude</Label>
                        <Input
                            id="latitude"
                            v-model.number="form.latitude"
                            type="number"
                            step="0.0000001"
                            placeholder="0.0000000"
                            :disabled="loading"
                            :class="{ 'border-red-300 focus:border-red-500': errors.latitude }"
                        />
                        <p v-if="errors.latitude" class="text-xs text-red-600 dark:text-red-400">
                            {{ errors.latitude[0] }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="longitude">Longitude</Label>
                        <Input
                            id="longitude"
                            v-model.number="form.longitude"
                            type="number"
                            step="0.0000001"
                            placeholder="0.0000000"
                            :disabled="loading"
                            :class="{ 'border-red-300 focus:border-red-500': errors.longitude }"
                        />
                        <p v-if="errors.longitude" class="text-xs text-red-600 dark:text-red-400">
                            {{ errors.longitude[0] }}
                        </p>
                    </div>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Enter shop description (optional)"
                        rows="3"
                        :disabled="loading"
                    />
                </div>

                <!-- DXF File Upload -->
                <div class="space-y-2">
                    <Label for="dxf_file">Layout File (DXF)</Label>
                    <div class="space-y-2">
                        <!-- File Input -->
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept=".dxf"
                            @change="handleFileSelect"
                            :disabled="loading"
                            class="hidden"
                        />
                        
                        <!-- Upload Button -->
                        <Button
                            type="button"
                            variant="outline"
                            @click="fileInputRef?.click()"
                            :disabled="loading"
                            class="w-full justify-center"
                        >
                            <Upload class="w-4 h-4 mr-2" />
                            {{ selectedFile ? 'Change DXF File' : 'Choose DXF File' }}
                        </Button>

                        <!-- Selected File Display -->
                        <div v-if="selectedFile" class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-md dark:bg-green-900/30 dark:border-green-800">
                            <span class="text-sm text-green-700 dark:text-green-300 truncate">
                                {{ selectedFile.name }}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="removeFile"
                                :disabled="loading"
                                class="text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300"
                            >
                                <X class="w-4 h-4" />
                            </Button>
                        </div>

                        <!-- Current File (Edit Mode) -->
                        <div v-if="isEditing && props.shop?.dxf_file_path && !selectedFile" class="p-3 bg-blue-50 border border-blue-200 rounded-md dark:bg-blue-900/30 dark:border-blue-800">
                            <span class="text-sm text-blue-700 dark:text-blue-300">
                                Current file: {{ props.shop.dxf_file_path.split('/').pop() }}
                            </span>
                        </div>
                    </div>
                    
                    <p v-if="errors.dxf_file" class="text-xs text-red-600 dark:text-red-400">
                        {{ errors.dxf_file[0] }}
                    </p>
                    <p class="text-xs not-dark:text-gray-500 dark:text-gray-400">
                        Maximum file size: 10MB. Only .dxf files are supported.
                    </p>
                </div>
            </form>

            <DialogFooter class="flex gap-3">
                <Button 
                    type="button" 
                    variant="outline" 
                    @click="handleClose"
                    :disabled="loading"
                >
                    Cancel
                </Button>
                <Button 
                    @click="submitForm"
                    :disabled="loading"
                    class="min-w-24"
                >
                    <Loader v-if="loading" class="w-4 h-4 mr-2 animate-spin" />
                    {{ submitText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>