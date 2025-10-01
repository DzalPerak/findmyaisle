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

    /**
     * Update DXF file for a specific shop.
     */
    public function updateDxf(Request $request, Shop $shop)
    {
        $validator = Validator::make($request->all(), [
            'dxf_file' => 'required|file|max:10240|mimes:dxf', // 10MB max, DXF files only
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Invalid file. Please upload a valid DXF file.');
        }

        try {
            // Delete old DXF file if it exists
            if ($shop->dxf_file_path && Storage::disk('public')->exists($shop->dxf_file_path)) {
                Storage::disk('public')->delete($shop->dxf_file_path);
            }

            // Store new DXF file
            $dxfFile = $request->file('dxf_file');
            $filename = 'shops/' . $shop->id . '/' . time() . '_' . $dxfFile->getClientOriginalName();
            $path = $dxfFile->storeAs('', $filename, 'public');

            // Update shop record
            $shop->update([
                'dxf_file_path' => $path,
            ]);

            return redirect()->back()->with('success', 'DXF file updated successfully.');
            
        } catch (\Exception $e) {
            \Log::error('Error updating DXF file: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update DXF file. Please try again.');
        }
    }

    /**
     * Save layout data for a specific shop by converting back to DXF format.
     */
    public function saveLayout(Request $request, Shop $shop): JsonResponse
    {
        \Log::info('SaveLayout called', [
            'shop_id' => $shop->id,
            'request_data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'lineSegments' => 'required|array',
            'bounds' => 'required|array',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            \Log::error('SaveLayout validation failed', $validator->errors()->toArray());
            return response()->json([
                'message' => 'Invalid layout data',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $lineSegments = $request->input('lineSegments');
            
            // Convert line segments back to DXF format
            $dxfContent = $this->convertToDxf($lineSegments);
            
            // Create new filename for the updated DXF
            $filename = 'shops/' . $shop->id . '/layout_edited_' . time() . '.dxf';
            Storage::disk('public')->put($filename, $dxfContent);

            // Update shop record with new DXF file reference
            $shop->update([
                'dxf_file_path' => $filename,
                'updated_at' => now()
            ]);

            \Log::info('Layout saved as DXF successfully', [
                'shop_id' => $shop->id,
                'filename' => $filename
            ]);

            return response()->json([
                'message' => 'Layout saved successfully',
                'dxf_file' => $filename
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error saving layout: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to save layout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert line segments array back to DXF format
     */
    private function convertToDxf(array $lineSegments): string
    {
        $dxf = "0\nSECTION\n2\nENTITIES\n";
        
        foreach ($lineSegments as $segment) {
            // Handle both data formats: {x1,y1,x2,y2} and {start:{x,y,z}, end:{x,y,z}}
            if (isset($segment['start']) && isset($segment['end'])) {
                $x1 = $segment['start']['x'];
                $y1 = $segment['start']['y'];
                $x2 = $segment['end']['x'];
                $y2 = $segment['end']['y'];
            } else {
                $x1 = $segment['x1'];
                $y1 = $segment['y1'];
                $x2 = $segment['x2'];
                $y2 = $segment['y2'];
            }
            
            // Add LINE entity
            $dxf .= "0\nLINE\n";
            $dxf .= "8\n0\n"; // Layer 0
            $dxf .= "10\n{$x1}\n"; // Start X
            $dxf .= "20\n{$y1}\n"; // Start Y
            $dxf .= "30\n0.0\n";   // Start Z
            $dxf .= "11\n{$x2}\n"; // End X
            $dxf .= "21\n{$y2}\n"; // End Y
            $dxf .= "31\n0.0\n";   // End Z
        }
        
        $dxf .= "0\nENDSEC\n0\nEOF\n";
        
        return $dxf;
    }
}
