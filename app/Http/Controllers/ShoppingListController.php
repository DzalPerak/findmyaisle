<?php

namespace App\Http\Controllers;

use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShoppingListController extends Controller
{
    public function index()
    {
        $lists = ShoppingList::with('items.product')
            ->where('user_id', Auth::id())
            ->get()
            ->map(function ($list) {
                return [
                    'id' => $list->id,
                    'name' => $list->name,
                    'items' => $list->items->map(function ($item) {
                        return [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'qty' => $item->quantity
                        ];
                    })
                ];
            });
            
        return response()->json($lists);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $list = ShoppingList::create([
            'name' => $validated['name'],
            'user_id' => Auth::id(),
        ]);

        foreach ($validated['items'] as $item) {
            ShoppingListItem::create([
                'shopping_list_id' => $list->id,
                'product_id' => $item['id'],
                'quantity' => $item['qty'],
            ]);
        }

        return response()->json(['success' => true, 'list_id' => $list->id]);
    }

    public function update(Request $request, ShoppingList $shoppingList)
    {
        // Check if user owns the list
        if ($shoppingList->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $shoppingList->update(['name' => $validated['name']]);
        
        // Remove existing items
        $shoppingList->items()->delete();
        
        // Add new items
        foreach ($validated['items'] as $item) {
            ShoppingListItem::create([
                'shopping_list_id' => $shoppingList->id,
                'product_id' => $item['id'],
                'quantity' => $item['qty'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(ShoppingList $shoppingList)
    {
        // Check if user owns the list
        if ($shoppingList->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $shoppingList->delete();
        return response()->json(['success' => true]);
    }

    public function removeItem(Request $request, ShoppingList $shoppingList)
    {
        // Check if user owns the list
        if ($shoppingList->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $shoppingList->items()
            ->where('product_id', $validated['product_id'])
            ->delete();

        return response()->json(['success' => true]);
    }
}
