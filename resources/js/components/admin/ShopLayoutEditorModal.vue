<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
// Removed Label import - using native label element
// Removed tabs import - using custom tab implementation
import { notify } from '@/utils/notifications';
import { router } from '@inertiajs/vue3';
import { Upload, Save, X, Map, FileText } from 'lucide-vue-next';
import Pathfinder from '@/components/Pathfinder.vue';

interface Shop {
    id: number;
    name: string;
    location: string;
    latitude?: number | string | null;
    longitude?: number | string | null;
    description?: string;
    dxf_file_path?: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    open: boolean;
    shop: Shop | null;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// Local state
const loading = ref(false);
const selectedFile = ref<File | null>(null);
const activeTab = ref('editor');

// Computed properties
const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value)
});

// File handling
const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        selectedFile.value = target.files[0];
    }
};

const uploadNewDxf = async () => {
    if (!selectedFile.value || !props.shop) return;
    
    loading.value = true;
    
    try {
        const formData = new FormData();
        formData.append('dxf_file', selectedFile.value);
        formData.append('_method', 'PUT');
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        console.log('CSRF token found:', csrfToken ? 'Yes' : 'No');
        
        const response = await fetch(`/api/shops/${props.shop.id}/dxf`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken || ''
            }
        });
        
        if (!response.ok) {
            const errorData = await response.json();
            
            // Handle CSRF token mismatch specifically
            if (response.status === 419 || (errorData.message && errorData.message.includes('CSRF'))) {
                throw new Error('Session expired. Please refresh the page and try again.');
            }
            
            throw new Error(errorData.message || 'Failed to upload DXF file');
        }
        
        notify.success(
            'Layout Updated',
            `DXF file has been successfully uploaded for ${props.shop.name}`
        );
        
        emit('saved');
        
        // Clean up modal state properly
        selectedFile.value = null;
        activeTab.value = 'editor';
        
        // Ensure modal closes properly with delay to allow success notification
        setTimeout(async () => {
            isOpen.value = false;
            await nextTick();
            
            // Force removal of any lingering overlays
            document.querySelectorAll('[data-radix-popper-content-wrapper]').forEach(el => {
                if (el.parentNode) el.parentNode.removeChild(el);
            });
        }, 500);
        
    } catch (error: any) {
        console.error('Error uploading DXF:', error);
        
        // Clean up on error
        selectedFile.value = null;
        
        notify.error(
            'Upload Failed',
            error.message || 'Failed to upload DXF file. Please try again.'
        );
    } finally {
        loading.value = false;
    }
};

// Save layout from editor
const saveLayoutFromEditor = async () => {
    if (!props.shop) return;
    
    loading.value = true;
    
    try {
        // Access the Pathfinder component's save function
        const pathfinderExports = (window as any).pathfinderExports;
        
        if (pathfinderExports && pathfinderExports.saveLayoutToServer) {
            const success = await pathfinderExports.saveLayoutToServer();
            
            if (success) {
                emit('saved');
                // Don't close the modal automatically, let user continue editing if needed
                notify.success(
                    'Layout Saved',
                    `Layout changes for ${props.shop.name} have been saved successfully`
                );
            }
        } else {
            notify.error(
                'Save Failed',
                'Layout editor is not ready. Please wait for the editor to fully load.'
            );
        }
    } catch (error: any) {
        console.error('Error saving layout:', error);
        notify.error(
            'Save Failed',
            error.message || 'Failed to save layout. Please try again.'
        );
    } finally {
        loading.value = false;
    }
};

const closeModal = async () => {
    // Clean up all modal state
    selectedFile.value = null;
    activeTab.value = 'editor';
    loading.value = false;
    
    // Close modal
    isOpen.value = false;
    
    // Force cleanup of any overlay remnants
    await nextTick();
    document.querySelectorAll('[data-radix-popper-content-wrapper]').forEach(el => {
        if (el.parentNode) el.parentNode.removeChild(el);
    });
};

// Reset file selection when modal closes
watch(isOpen, async (newValue) => {
    if (!newValue) {
        selectedFile.value = null;
        activeTab.value = 'editor';
        loading.value = false;
        
        // Force DOM update to ensure overlay is removed
        await nextTick();
    }
});
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent 
            class="!max-w-none !w-[95vw] !h-[95vh] !max-h-none overflow-auto p-6"
            :style="{ 
                width: '95vw !important', 
                height: '95vh !important', 
                maxWidth: '95vw !important', 
                maxHeight: '95vh !important',
                left: '2.5vw',
                top: '2.5vh',
                transform: 'none'
            }"
        >
            <DialogHeader class="mb-4 flex-shrink-0">
                <DialogTitle class="flex items-center gap-2 text-xl">
                    <Map class="w-6 h-6 text-purple-600" />
                    Edit Layout - {{ shop?.name }}
                </DialogTitle>
            </DialogHeader>

            <div class="flex flex-col h-full">
                <!-- Custom Tab Navigation -->
                <div class="flex w-full mb-6 bg-gray-100 dark:bg-gray-700 rounded-xl p-2 flex-shrink-0">
                    <button
                        @click="activeTab = 'editor'"
                        :class="[
                            'flex-1 flex items-center justify-center gap-3 py-4 px-6 rounded-lg font-semibold text-base transition-colors',
                            activeTab === 'editor'
                                ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-md'
                                : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
                        ]"
                    >
                        <Map class="w-5 h-5" />
                        Visual Editor
                    </button>
                    <button
                        @click="activeTab = 'upload'"
                        :class="[
                            'flex-1 flex items-center justify-center gap-3 py-4 px-6 rounded-lg font-semibold text-base transition-colors',
                            activeTab === 'upload'
                                ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-md'
                                : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
                        ]"
                    >
                        <Upload class="w-5 h-5" />
                        Upload New File
                    </button>
                </div>

                <!-- Visual Editor Tab Content -->
                <div v-if="activeTab === 'editor'" class="flex flex-col flex-1 space-y-4 overflow-y-auto">
                        <div class="flex items-center justify-between p-6 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800 shadow-sm">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <Map class="w-6 h-6 text-white" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-blue-900 dark:text-blue-100">Visual Layout Editor</h3>
                                    <p class="text-sm text-blue-700 dark:text-blue-300">
                                        Edit your shop layout using the interactive visual editor below. Changes are saved automatically.
                                    </p>
                                </div>
                            </div>
                            <Button 
                                @click="saveLayoutFromEditor" 
                                :disabled="loading"
                                class="bg-blue-600 hover:bg-blue-700 px-6 py-3 text-base font-medium"
                            >
                                <Save class="w-5 h-5 mr-2" />
                                {{ loading ? 'Saving...' : 'Save Layout' }}
                            </Button>
                        </div>

                        <!-- Pathfinder Component - Full Height -->
                        <div class="flex-1 min-h-0 border border-gray-300 dark:border-gray-600 rounded-xl overflow-hidden shadow-lg">
                            <Pathfinder 
                                v-if="shop?.id" 
                                :key="shop.id" 
                                :shop-id="shop.id" 
                                :is-user-mode="false"
                                class="w-full h-full"
                                style="min-height: 500px;"
                            />
                        </div>
                </div>

                <!-- File Upload Tab Content -->
                <div v-if="activeTab === 'upload'" class="flex flex-col flex-1 overflow-hidden">
                    <div class="flex-1 overflow-y-auto p-2 pb-4 space-y-8">
                        <div class="p-8 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center gap-4 mb-6">
                                <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <Upload class="w-6 h-6 text-white" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Upload New DXF File</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Replace the current layout with a new DXF file. Supported format: .dxf files up to 10MB
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <div>
                                    <label for="dxf-file" class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-3">Select DXF File</label>
                                    <Input
                                        id="dxf-file"
                                        type="file"
                                        accept=".dxf"
                                        @change="handleFileChange"
                                        class="text-base p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl hover:border-green-400 dark:hover:border-green-500 transition-colors"
                                    />
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        Only .dxf files are supported. Maximum file size: 10MB
                                    </p>
                                </div>

                                <div v-if="selectedFile" class="flex items-center gap-2 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                                    <FileText class="w-4 h-4 text-green-600 dark:text-green-400" />
                                    <span class="text-sm text-green-800 dark:text-green-200">
                                        Selected: {{ selectedFile.name }} ({{ (selectedFile.size / 1024).toFixed(1) }} KB)
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Current Layout Info -->
                        <div v-if="shop?.dxf_file_path" class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                            <div class="flex items-center gap-2 mb-2">
                                <FileText class="w-4 h-4 text-yellow-600 dark:text-yellow-400" />
                                <span class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Current Layout</span>
                            </div>
                            <p class="text-sm text-yellow-700 dark:text-yellow-300">
                                This shop already has a layout file. Uploading a new file will replace the existing one.
                            </p>
                        </div>
                        <div v-else class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-2 mb-2">
                                <FileText class="w-4 h-4 text-gray-400" />
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">No Layout</span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                This shop doesn't have a layout file yet.
                            </p>
                        </div>

                    </div>
                    
                    <!-- Upload Actions - Fixed at bottom -->
                    <div class="flex-shrink-0 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                        <div class="flex justify-end gap-4">
                            <Button variant="outline" @click="closeModal" :disabled="loading" class="px-6 py-3 text-base">
                                <X class="w-5 h-5 mr-2" />
                                Cancel
                            </Button>
                            <Button 
                                @click="uploadNewDxf" 
                                :disabled="!selectedFile || loading"
                                class="bg-green-600 hover:bg-green-700 px-8 py-3 text-base font-medium"
                            >
                                <Upload class="w-5 h-5 mr-2" />
                                {{ loading ? 'Uploading...' : 'Upload & Replace' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/* Force full-screen modal styling */
:deep([data-slot="dialog-content"]) {
    width: 95vw !important;
    height: 95vh !important;
    max-width: 95vw !important;
    max-height: 95vh !important;
    left: 2.5vw !important;
    top: 2.5vh !important;
    transform: none !important;
    position: fixed !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
}

/* Style scrollable areas */
.overflow-y-auto {
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 0, 0, 0.3) rgba(0, 0, 0, 0.1);
}

.overflow-y-auto::-webkit-scrollbar {
    width: 8px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.5);
}

/* Ensure the modal content fills the space properly */
:deep(.flex.flex-col.h-full) {
    height: 100% !important;
}
</style>