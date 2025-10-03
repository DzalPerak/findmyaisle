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
        
        <div v-else-if="categories.length === 0" class="text-gray-500 text-center py-4">
          No categories available
        </div>
        
        <div v-else class="space-y-2 max-h-60 overflow-y-auto">
          <div 
            v-for="category in categories" 
            :key="category.id"
            class="flex items-center space-x-3 p-2 hover:bg-gray-50 dark:hover:bg-gray-800 rounded"
          >
            <Checkbox
              :id="`category-${category.id}`"
              :checked="selectedCategories.includes(category.id)"
              @update:checked="() => toggleCategory(category.id)"
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
        <Button @click="saveCategories" :disabled="saving">
          <span v-if="saving">Saving...</span>
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

watch(() => props.open, (newValue) => {
  isOpen.value = newValue
  if (newValue && props.waypointId) {
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
    
    // Load waypoint's current categories
    const waypointResponse = await axios.get(`/api/waypoints/${props.waypointId}`, { headers })
    selectedCategories.value = waypointResponse.data.categories.map((cat: Category) => cat.id)
  } catch (error) {
    console.error('Error loading data:', error)
  } finally {
    loading.value = false
  }
}

function toggleCategory(categoryId: number) {
  const index = selectedCategories.value.indexOf(categoryId)
  
  if (index > -1) {
    selectedCategories.value.splice(index, 1)
  } else {
    selectedCategories.value.push(categoryId)
  }
}

function handleCategoryChange(categoryId: number, checked: boolean) {
  console.log('Category change:', categoryId, 'checked:', checked)
  if (checked) {
    if (!selectedCategories.value.includes(categoryId)) {
      selectedCategories.value.push(categoryId)
      console.log('Added category', categoryId, 'selected categories:', selectedCategories.value)
    }
  } else {
    const index = selectedCategories.value.indexOf(categoryId)
    if (index > -1) {
      selectedCategories.value.splice(index, 1)
      console.log('Removed category', categoryId, 'selected categories:', selectedCategories.value)
    }
  }
}

async function saveCategories() {
  if (!props.waypointId) return
  
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
}
</script>