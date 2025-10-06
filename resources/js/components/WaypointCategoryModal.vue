<template>
  <Dialog v-model:open="isOpen">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Assign Categories to Waypoint</DialogTitle>
        <DialogDescription>
          Select which categories this waypoint should contain.
        </DialogDescription>
      </DialogHeader>
      
      <div class="space-y-4">
        <div v-if="loading" class="flex justify-center py-4">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-gray-900"></div>
        </div>
        
        <div v-else-if="!isWaypointSaved" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
          <div class="flex items-center space-x-2">
            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
            <div>
              <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Waypoint Not Saved</p>
              <p class="text-sm text-amber-700 dark:text-amber-300">Please save the layout first before assigning categories to this waypoint.</p>
            </div>
          </div>
        </div>
        
        <div v-else-if="categories.length === 0" class="text-gray-500 text-center py-4">
          No categories available
        </div>
        
        <div v-else class="space-y-2 max-h-60 overflow-y-auto">
          <div 
            v-for="category in categories" 
            :key="category.id"
            class="flex items-center space-x-3 p-2 hover:bg-gray-50 dark:hover:bg-gray-800 rounded"
          >
            <input 
              type="checkbox"
              :id="`category-${category.id}`"
              :checked="selectedCategories.includes(category.id)"
              @change="(event) => handleCategoryChangeSimple(category.id, (event.target as HTMLInputElement).checked)"
              class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
            />
            <label 
              :for="`category-${category.id}`"
              class="flex-1 cursor-pointer flex items-center space-x-2"
            >
              <div 
                class="w-4 h-4 rounded-full"
                :style="{ backgroundColor: category.color }"
              ></div>
              <span class="text-sm font-medium">{{ category.name }}</span>
            </label>
          </div>
        </div>
      </div>

      <DialogFooter class="gap-2">
        <Button variant="outline" @click="close">Cancel</Button>
        <Button 
          @click="saveCategories" 
          :disabled="saving || !isWaypointSaved"
          :class="{ 'opacity-50': !isWaypointSaved }"
        >
          <span v-if="saving">Saving...</span>
          <span v-else-if="!isWaypointSaved">Save Layout First</span>
          <span v-else>Save</span>
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import axios from 'axios'

interface Category {
  id: number
  name: string
  color: string
  description: string | null
}

interface Props {
  open: boolean
  waypointId: number | null
}

interface Emits {
  (e: 'update:open', value: boolean): void
  (e: 'saved'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const isOpen = ref(false)
const categories = ref<Category[]>([])
const selectedCategories = ref<number[]>([])
const loading = ref(false)
const saving = ref(false)
const isWaypointSaved = ref(true) // Tracks if waypoint exists in database

watch(() => props.open, (newValue) => {
  isOpen.value = newValue
  if (newValue && props.waypointId) {
    // Reset state when opening modal
    isWaypointSaved.value = true
    selectedCategories.value = []
    loadData()
  }
})

watch(isOpen, (newValue) => {
  if (!newValue) {
    emit('update:open', false)
  }
})

async function loadData() {
  if (!props.waypointId) return
  
  loading.value = true
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    const headers = {
      'X-CSRF-TOKEN': csrfToken,
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    }
    
    // Load all categories
    const categoriesResponse = await axios.get('/api/categories', { headers })
    categories.value = categoriesResponse.data
    
    // Load waypoint's current categories - check if waypoint exists first
    try {
      const waypointResponse = await axios.get(`/api/waypoints/${props.waypointId}`, { headers })
      selectedCategories.value = waypointResponse.data.categories.map((cat: Category) => cat.id)
      isWaypointSaved.value = true
    } catch (error: any) {
      if (error.response?.status === 404) {
        // Waypoint doesn't exist in database yet (unsaved waypoint)
        selectedCategories.value = []
        isWaypointSaved.value = false
      } else {
        throw error // Re-throw other errors
      }
    }
  } catch (error) {
    console.error('Error loading data:', error)
  } finally {
    loading.value = false
  }
}

function handleCategoryChangeSimple(categoryId: number, checked: boolean) {
  console.log('handleCategoryChangeSimple called:', { categoryId, checked, selectedBefore: [...selectedCategories.value] })
  
  if (checked) {
    if (!selectedCategories.value.includes(categoryId)) {
      selectedCategories.value.push(categoryId)
    }
  } else {
    const index = selectedCategories.value.indexOf(categoryId)
    if (index > -1) {
      selectedCategories.value.splice(index, 1)
    }
  }
  
  console.log('selectedCategories after simple change:', [...selectedCategories.value])
}

function toggleCategoryManual(categoryId: number) {
  const isCurrentlySelected = selectedCategories.value.includes(categoryId)
  console.log('toggleCategoryManual called:', { categoryId, isCurrentlySelected })
  
  if (isCurrentlySelected) {
    const index = selectedCategories.value.indexOf(categoryId)
    selectedCategories.value.splice(index, 1)
  } else {
    selectedCategories.value.push(categoryId)
  }
  
  console.log('selectedCategories after manual toggle:', [...selectedCategories.value])
}

function handleCategoryChange(categoryId: number, checked: boolean) {
  console.log('handleCategoryChange called:', { categoryId, checked, selectedBefore: [...selectedCategories.value] })
  
  if (checked) {
    if (!selectedCategories.value.includes(categoryId)) {
      selectedCategories.value.push(categoryId)
    }
  } else {
    const index = selectedCategories.value.indexOf(categoryId)
    if (index > -1) {
      selectedCategories.value.splice(index, 1)
    }
  }
  
  console.log('selectedCategories after change:', [...selectedCategories.value])
}

async function saveCategories() {
  if (!props.waypointId) return
  
  // Check if waypoint is saved to database
  if (!isWaypointSaved.value) {
    // Show error message for unsaved waypoint
    console.error('Cannot assign categories to unsaved waypoint')
    return
  }
  
  console.log('About to save categories:', selectedCategories.value)
  saving.value = true
  
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    
    await axios.put(`/api/waypoints/${props.waypointId}/categories`, {
      category_ids: selectedCategories.value
    }, {
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    })
    
    emit('saved')
    close()
  } catch (error) {
    console.error('Error saving categories:', error)
  } finally {
    saving.value = false
  }
}

function close() {
  isOpen.value = false
  // Reset state
  isWaypointSaved.value = true
  selectedCategories.value = []
  categories.value = []
}
</script>