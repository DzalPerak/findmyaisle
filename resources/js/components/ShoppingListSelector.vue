<template>
  <div v-if="isUserMode" class="mb-6 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
      Auto-Select Waypoints by Shopping List
    </h3>
    
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center space-x-2 text-gray-600 dark:text-gray-400">
      <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600"></div>
      <span class="text-sm">Loading shopping lists...</span>
    </div>
    
    <!-- No Shopping Lists -->
    <div v-else-if="shoppingLists.length === 0" class="text-gray-500 dark:text-gray-400 text-sm">
      <p>No shopping lists found. 
        <Link :href="shoppinglists()" class="text-blue-600 dark:text-blue-400 hover:underline">
          Create one here
        </Link>
      </p>
    </div>
    
    <!-- Shopping List Dropdown -->
    <div v-else class="space-y-4">
      <div class="flex items-center space-x-4">
        <select 
          v-model="selectedListId"
          class="w-72 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        >
          <option value="">Select a shopping list</option>
          <option 
            v-for="list in shoppingLists" 
            :key="list.id" 
            :value="list.id.toString()"
          >
            {{ list.name }} ({{ list.items.length }} items)
          </option>
        </select>
        
        <Button 
          @click="autoSelectWaypoints" 
          :disabled="!selectedListId || autoSelecting"
          class="px-6"
        >
          <span v-if="autoSelecting">Selecting...</span>
          <span v-else>Auto-Select Waypoints</span>
        </Button>
      </div>
      
      <!-- Selected List Preview -->
      <div v-if="selectedList" class="bg-white dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
        <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ selectedList.name }}</h4>
        <div class="flex flex-wrap gap-2">
          <span 
            v-for="item in selectedList.items" 
            :key="item.id"
            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
          >
            {{ item.name }}
            <span v-if="item.qty > 1" class="ml-1 text-blue-600 dark:text-blue-300">×{{ item.qty }}</span>
          </span>
        </div>
      </div>
      
      <!-- Results -->
      <div v-if="lastSelectionResult" class="text-sm space-y-2">
        <div v-if="lastSelectionResult.waypointIds.length > 0">
          <p class="text-green-600 dark:text-green-400">
            ✅ Auto-selected {{ lastSelectionResult.waypointIds.length }} waypoint(s) based on your shopping list categories
          </p>
          
          <!-- Show matched items -->
          <div v-if="lastSelectionResult.matchedItems && lastSelectionResult.matchedItems.length > 0" class="mt-1">
            <p class="text-xs text-gray-500 dark:text-gray-400">
              Matched items ({{ lastSelectionResult.matchedItems.length }}):
              <span class="text-green-600 dark:text-green-400">
                {{ lastSelectionResult.matchedItems.map(item => `${item.name}${item.qty > 1 ? ` (×${item.qty})` : ''}`).join(', ') }}
              </span>
            </p>
          </div>
        </div>
        
        <!-- Show unmatched items -->
        <div v-if="lastSelectionResult.unmatchedItems && lastSelectionResult.unmatchedItems.length > 0">
          <p class="text-amber-600 dark:text-amber-400">
            ⚠️ No waypoints found for {{ lastSelectionResult.unmatchedItems.length }} item(s):
            <span class="font-medium">
              {{ lastSelectionResult.unmatchedItems.map(item => `${item.name}${item.qty > 1 ? ` (×${item.qty})` : ''}`).join(', ') }}
            </span>
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            These items don't have assigned waypoint categories in this store.
          </p>
        </div>
        
        <div v-if="lastSelectionResult.waypointIds.length === 0 && (!lastSelectionResult.unmatchedItems || lastSelectionResult.unmatchedItems.length === 0)">
          <p class="text-yellow-600 dark:text-yellow-400">
            ⚠️ No waypoints found matching your shopping list categories
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Link } from '@inertiajs/vue3'

import { Button } from '@/components/ui/button'
import { shoppinglists } from '@/routes'
import axios from 'axios'

interface ShoppingListItem {
  id: number
  name: string
  qty: number
}

interface ShoppingList {
  id: number
  name: string
  items: ShoppingListItem[]
}

interface Props {
  isUserMode: boolean
  shopId: number | null
}

interface Emits {
  (e: 'waypointsSelected', waypointIds: number[]): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const shoppingLists = ref<ShoppingList[]>([])
const selectedListId = ref<string>('')
const loading = ref(false)
const autoSelecting = ref(false)
interface SelectionResult {
  waypointIds: number[]
  matchedItems?: Array<{ name: string; category: string; qty: number }>
  unmatchedItems?: Array<{ name: string; category: string; qty: number }>
}

const lastSelectionResult = ref<SelectionResult | null>(null)

const selectedList = computed(() => {
  if (!selectedListId.value) return null
  return shoppingLists.value.find(list => list.id.toString() === selectedListId.value)
})

onMounted(() => {
  if (props.isUserMode) {
    loadShoppingLists()
  }
})

watch(() => props.isUserMode, (newValue) => {
  if (newValue) {
    loadShoppingLists()
  }
})

async function loadShoppingLists() {
  loading.value = true
  try {
    const response = await axios.get('/api/shopping-lists')
    shoppingLists.value = response.data
  } catch (error) {
    console.error('Error loading shopping lists:', error)
  } finally {
    loading.value = false
  }
}

async function autoSelectWaypoints() {
  if (!selectedListId.value || !props.shopId) return
  
  autoSelecting.value = true
  try {
    const response = await axios.post('/api/waypoints/by-shopping-list', {
      shopping_list_id: parseInt(selectedListId.value),
      shop_id: props.shopId
    })
    
    lastSelectionResult.value = {
      waypointIds: response.data.waypoint_ids,
      matchedItems: response.data.matched_items || [],
      unmatchedItems: response.data.unmatched_items || []
    }
    
    // Emit the waypoint IDs to parent component for selection
    emit('waypointsSelected', response.data.waypoint_ids)
    
  } catch (error) {
    console.error('Error auto-selecting waypoints:', error)
    lastSelectionResult.value = { 
      waypointIds: [], 
      matchedItems: [], 
      unmatchedItems: [] 
    }
  } finally {
    autoSelecting.value = false
  }
}
</script>