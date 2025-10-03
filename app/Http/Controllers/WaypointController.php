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

        $waypoint->categories()->sync($categoryIds);
        
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

        // Get categories from shopping list items
        $categoryIds = DB::table('shopping_list_items')
            ->join('products', 'shopping_list_items.product_id', '=', 'products.id')
            ->join('shopping_lists', 'shopping_list_items.shopping_list_id', '=', 'shopping_lists.id')
            ->where('shopping_lists.id', $validated['shopping_list_id'])
            ->where('shopping_lists.user_id', Auth::id()) // Ensure user owns the shopping list
            ->pluck('products.category_id')
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            return response()->json([
                'waypoint_ids' => []
            ]);
        }

        // Get waypoints that have any of these categories assigned
        $waypointIds = DB::table('waypoint_categories')
            ->whereIn('category_id', $categoryIds)
            ->pluck('waypoint_id')
            ->unique()
            ->values();

        return response()->json([
            'waypoint_ids' => $waypointIds,
            'categories' => $categoryIds
        ]);
    }
}