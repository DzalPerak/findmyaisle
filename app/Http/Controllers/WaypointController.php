<?php

namespace App\Http\Controllers;

use App\Models\Waypoint;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WaypointController extends Controller
{
    /**
     * Get all categories for admin dropdown
     */
    public function getCategories()
    {
        $categories = Category::orderBy('name')->get();
        return response()->json($categories);
    }

    /**
     * Get waypoint with its assigned categories
     */
    public function show(Waypoint $waypoint)
    {
        $waypoint->load('categories');
        return response()->json($waypoint);
    }

    /**
     * Update waypoint categories
     */
    public function updateCategories(Request $request, Waypoint $waypoint)
    {
        \Log::info('Update categories called', [
            'waypoint_id' => $waypoint->id,
            'request_data' => $request->all()
        ]);

        $validated = $request->validate([
            'category_ids' => 'array',
            'category_ids.*' => 'integer|exists:categories,id'
        ]);

        // If category_ids is not provided, default to empty array
        $categoryIds = $validated['category_ids'] ?? [];

        \Log::info('About to sync categories', [
            'waypoint_id' => $waypoint->id,
            'category_ids' => $categoryIds
        ]);

        // Process each selected category with soft delete logic
        foreach ($categoryIds as $categoryId) {
            $existingRecord = DB::table('waypoint_categories')
                ->where('waypoint_id', $waypoint->id)
                ->where('category_id', $categoryId)
                ->first();

            if ($existingRecord) {
                // Record exists - set it to active
                if (!$existingRecord->active) {
                    DB::table('waypoint_categories')
                        ->where('waypoint_id', $waypoint->id)
                        ->where('category_id', $categoryId)
                        ->update([
                            'active' => true,
                            'updated_at' => now()
                        ]);
                    \Log::info('Reactivated category', ['waypoint_id' => $waypoint->id, 'category_id' => $categoryId]);
                }
            } else {
                // No record exists - create new active record
                DB::table('waypoint_categories')->insert([
                    'waypoint_id' => $waypoint->id,
                    'category_id' => $categoryId,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                \Log::info('Created new category assignment', ['waypoint_id' => $waypoint->id, 'category_id' => $categoryId]);
            }
        }

        // Deactivate categories that are not in the selected list
        $deactivatedCount = DB::table('waypoint_categories')
            ->where('waypoint_id', $waypoint->id)
            ->where('active', true)
            ->whereNotIn('category_id', $categoryIds)
            ->update([
                'active' => false,
                'updated_at' => now()
            ]);

        \Log::info('Deactivated categories', [
            'waypoint_id' => $waypoint->id,
            'deactivated_count' => $deactivatedCount
        ]);

        // Reload to verify - only active categories
        $waypoint->load('categories');
        \Log::info('Waypoint categories after update', [
            'active_categories' => $waypoint->categories->pluck('id')->toArray()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Categories updated successfully'
        ]);
    }

    /**
     * Get waypoints by shopping list categories (for user mode)
     */
    public function getWaypointsByShoppingList(Request $request)
    {
        $validated = $request->validate([
            'shopping_list_id' => 'required|integer|exists:shopping_lists,id',
            'shop_id' => 'required|integer|exists:shops,id'
        ]);

        // Get detailed shopping list items with their categories
        $shoppingListItems = DB::table('shopping_list_items')
            ->join('products', 'shopping_list_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('shopping_lists', 'shopping_list_items.shopping_list_id', '=', 'shopping_lists.id')
            ->where('shopping_lists.id', $validated['shopping_list_id'])
            ->where('shopping_lists.user_id', Auth::id()) // Ensure user owns the shopping list
            ->select(
                'products.name as product_name',
                'products.category_id',
                'categories.name as category_name',
                'shopping_list_items.quantity as qty'
            )
            ->get();

        if ($shoppingListItems->isEmpty()) {
            return response()->json([
                'waypoint_ids' => [],
                'matched_items' => [],
                'unmatched_items' => []
            ]);
        }

        // Get unique category IDs from the shopping list
        $categoryIds = $shoppingListItems->pluck('category_id')->unique()->values();

        // Get categories that have waypoints assigned (active waypoints only)
        $categoriesWithWaypoints = DB::table('waypoint_categories')
            ->where('active', true)
            ->whereIn('category_id', $categoryIds)
            ->pluck('category_id')
            ->unique()
            ->values();

        // Get waypoints that have any of these categories assigned
        $waypointIds = DB::table('waypoint_categories')
            ->where('active', true)
            ->whereIn('category_id', $categoriesWithWaypoints)
            ->pluck('waypoint_id')
            ->unique()
            ->values();

        // Separate matched and unmatched items
        $matchedItems = [];
        $unmatchedItems = [];

        foreach ($shoppingListItems as $item) {
            $itemData = [
                'name' => $item->product_name,
                'category' => $item->category_name,
                'qty' => $item->qty
            ];

            if ($categoriesWithWaypoints->contains($item->category_id)) {
                $matchedItems[] = $itemData;
            } else {
                $unmatchedItems[] = $itemData;
            }
        }

        return response()->json([
            'waypoint_ids' => $waypointIds,
            'matched_items' => $matchedItems,
            'unmatched_items' => $unmatchedItems,
            'categories' => $categoryIds
        ]);
    }
}