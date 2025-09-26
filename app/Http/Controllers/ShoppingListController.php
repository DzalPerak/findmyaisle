<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ShoppingListController extends Controller
{
    public function index()
    {
        $list = Session::get('shopping_list', []);
        return response()->json($list);
    }

    public function store(Request $request)
    {
        $product = $request->validate([
            'id' => 'required|integer',
            'name' => 'required|string',
        ]);
        $list = Session::get('shopping_list', []);
        $list[$product['id']] = $product;
        Session::put('shopping_list', $list);
        return response()->json($list);
    }

    public function destroy($id)
    {
        $list = Session::get('shopping_list', []);
        unset($list[$id]);
        Session::put('shopping_list', $list);
        return response()->json($list);
    }
}
