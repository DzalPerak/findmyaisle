<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShopController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        
        $query = Shop::query();
        
        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }
        
        $shops = $query->orderBy('created_at', 'desc')
                      ->paginate($perPage);
        
        return response()->json($shops);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:shops,name',
            'location' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'description' => 'nullable|string|max:1000',
            'dxf_file' => 'nullable|file|max:10240', // 10MB max, accept any file type for now
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $shopData = $validator->validated();
        
        // Handle DXF file upload
        if ($request->hasFile('dxf_file')) {
            $file = $request->file('dxf_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('shop-layouts', $fileName, 'public');
            $shopData['dxf_file_path'] = $filePath;
        }

        $shop = Shop::create($shopData);

        return redirect()->back()->with('success', 'Shop created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Shop $shop): JsonResponse
    {
        return response()->json($shop);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Shop $shop)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:shops,name,' . $shop->id,
            'location' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'description' => 'nullable|string|max:1000',
            'dxf_file' => 'nullable|file|max:10240', // 10MB max, accept any file type for now
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $shopData = $validator->validated();
        
        // Handle DXF file upload
        if ($request->hasFile('dxf_file')) {
            // Delete old file if exists
            if ($shop->dxf_file_path && Storage::disk('public')->exists($shop->dxf_file_path)) {
                Storage::disk('public')->delete($shop->dxf_file_path);
            }
            
            $file = $request->file('dxf_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('shop-layouts', $fileName, 'public');
            $shopData['dxf_file_path'] = $filePath;
        }

        $shop->update($shopData);

        return redirect()->back()->with('success', 'Shop updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Shop $shop)
    {
        // Delete associated DXF file if exists
        if ($shop->dxf_file_path && Storage::disk('public')->exists($shop->dxf_file_path)) {
            Storage::disk('public')->delete($shop->dxf_file_path);
        }

        $shop->delete();

        return redirect()->back()->with('success', 'Shop deleted successfully');
    }

    /**
     * Get available shops for route planning (shops with DXF files).
     */
    public function getAvailableShops(): JsonResponse
    {
        $shops = Shop::whereNotNull('dxf_file_path')
                    ->select(['id', 'name', 'location', 'dxf_file_path'])
                    ->orderBy('name')
                    ->get();

        return response()->json($shops);
    }

    /**
     * Get DXF file for a specific shop.
     */
    public function getDxfFile(Shop $shop): BinaryFileResponse
    {
        if (!$shop->dxf_file_path || !Storage::disk('public')->exists($shop->dxf_file_path)) {
            abort(404, 'DXF file not found');
        }

        $filePath = Storage::disk('public')->path($shop->dxf_file_path);
        
        return response()->file($filePath, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . basename($shop->dxf_file_path) . '"'
        ]);
    }
}
