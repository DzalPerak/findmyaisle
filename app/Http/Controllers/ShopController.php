<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Waypoint;
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
     * Get saved layout data for a specific shop.
     */
    public function getLayout(Shop $shop): JsonResponse
    {
        // Check if the shop has a layout file with metadata
        $layoutPath = 'shops/' . $shop->id . '/layout.json';
        
        if (!Storage::disk('public')->exists($layoutPath)) {
            return response()->json([
                'message' => 'No saved layout data found'
            ], 404);
        }
        
        try {
            $layoutData = json_decode(Storage::disk('public')->get($layoutPath), true);
            
            // Load waypoints from database and merge with layout data
            $waypoints = $shop->waypoints()->get(['id', 'name', 'x', 'y', 'description'])->toArray();
            $layoutData['waypoints'] = $waypoints;
            
            return response()->json($layoutData);
        } catch (\Exception $e) {
            \Log::error('Error loading layout data: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load layout data'
            ], 500);
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
            
            \Log::info('Processing layout save request', [
                'shop_id' => $shop->id,
                'line_segments_count' => count($lineSegments),
                'waypoints_count' => $request->has('waypoints') ? count($request->input('waypoints')) : 0,
                'memory_usage_before' => memory_get_usage(true) / 1024 / 1024 . ' MB'
            ]);
            
            // Convert line segments back to DXF format
            $dxfContent = $this->convertToDxf($lineSegments);
            
            \Log::info('DXF conversion completed', [
                'memory_usage_after_dxf' => memory_get_usage(true) / 1024 / 1024 . ' MB'
            ]);
            
            // Create new filename for the updated DXF
            $filename = 'shops/' . $shop->id . '/layout_edited_' . time() . '.dxf';
            Storage::disk('public')->put($filename, $dxfContent);

            // Save waypoints to database
            if ($request->has('waypoints')) {
                $this->saveWaypointsToDatabase($shop, $request->input('waypoints'));
            }

            // Save layout metadata (waypoints, etc.) as JSON file
            $layoutJsonPath = 'shops/' . $shop->id . '/layout.json';
            $layoutMetadata = $request->all(); // Save full request data including metadata
            Storage::disk('public')->put($layoutJsonPath, json_encode($layoutMetadata, JSON_PRETTY_PRINT));

            // Update shop record with new DXF file reference
            $shop->update([
                'dxf_file_path' => $filename,
                'updated_at' => now()
            ]);

            \Log::info('Layout saved as DXF successfully', [
                'shop_id' => $shop->id,
                'filename' => $filename,
                'layout_json' => $layoutJsonPath
            ]);

            return response()->json([
                'message' => 'Layout saved successfully',
                'dxf_file' => $filename,
                'layout_data' => $layoutJsonPath
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error saving layout: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'shop_id' => $shop->id,
                'line_segments_count' => count($request->input('lineSegments', [])),
                'waypoints_count' => count($request->input('waypoints', []))
            ]);
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
        \Log::info('Converting to DXF', ['line_segment_count' => count($lineSegments)]);
        
        $dxf = "0\nSECTION\n2\nENTITIES\n";
        
        foreach ($lineSegments as $segment) {
            // Handle both data formats: {x1,y1,x2,y2} and {start:{x,y,z}, end:{x,y,z}}
            if (isset($segment['start']) && isset($segment['end'])) {
                $transformedX1 = $segment['start']['x'];
                $transformedY1 = $segment['start']['y'];
                $transformedX2 = $segment['end']['x'];
                $transformedY2 = $segment['end']['y'];
            } else {
                $transformedX1 = $segment['x1'];
                $transformedY1 = $segment['y1'];
                $transformedX2 = $segment['x2'];
                $transformedY2 = $segment['y2'];
            }
            
            // Apply inverse transformation to restore original DXF coordinates
            // Frontend applies: (x,y) → (-y, -x)
            // So we need inverse: (x,y) → (-y, -x) (same transformation, it's self-inverse)
            $x1 = -$transformedY1;
            $y1 = -$transformedX1;
            $x2 = -$transformedY2;
            $y2 = -$transformedX2;
            
            \Log::debug('DXF Coordinate transformation', [
                'original' => ['x1' => $transformedX1, 'y1' => $transformedY1, 'x2' => $transformedX2, 'y2' => $transformedY2],
                'restored' => ['x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2]
            ]);
            
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

    /**
     * Save waypoints to database
     */
    private function saveWaypointsToDatabase(Shop $shop, array $waypoints): void
    {
        \Log::info('Saving waypoints to database', [
            'shop_id' => $shop->id,
            'waypoints_count' => count($waypoints),
            'sample_waypoint' => $waypoints[0] ?? null
        ]);
        
        // Get existing waypoints with their categories and start/end status
        $existingWaypoints = $shop->waypoints()->with('categories')->get()->keyBy('name');
        
        \Log::info('Existing waypoints found', [
            'existing_count' => $existingWaypoints->count(),
            'existing_names' => $existingWaypoints->keys()->toArray()
        ]);
        
        // Clear existing waypoints for this shop
        $shop->waypoints()->delete();
        
        // Save new waypoints and restore category associations and start/end status
        foreach ($waypoints as $waypoint) {
            $waypointName = $waypoint['name'] ?? 'Waypoint';
            
            // Create the new waypoint with default values
            $waypointData = [
                'name' => $waypointName,
                'x' => $waypoint['x'],
                'y' => $waypoint['y'],
                'description' => $waypoint['description'] ?? null,
                'is_start_point' => false,
                'is_end_point' => false
            ];
            
            // If this waypoint existed before, restore its start/end point status
            if ($existingWaypoints->has($waypointName)) {
                $existingWaypoint = $existingWaypoints->get($waypointName);
                $waypointData['is_start_point'] = $existingWaypoint->is_start_point ?? false;
                $waypointData['is_end_point'] = $existingWaypoint->is_end_point ?? false;
                
                \Log::info('Restoring waypoint status', [
                    'waypoint_name' => $waypointName,
                    'is_start_point' => $waypointData['is_start_point'],
                    'is_end_point' => $waypointData['is_end_point']
                ]);
            }
            
            // Create the new waypoint
            $newWaypoint = $shop->waypoints()->create($waypointData);
            
            // If this waypoint existed before, restore its category associations
            if ($existingWaypoints->has($waypointName)) {
                $existingWaypoint = $existingWaypoints->get($waypointName);
                $categoryIds = $existingWaypoint->categories->pluck('id')->toArray();
                
                if (!empty($categoryIds)) {
                    $newWaypoint->categories()->sync($categoryIds);
                    \Log::info('Restored categories for waypoint', [
                        'waypoint_name' => $waypointName,
                        'category_ids' => $categoryIds
                    ]);
                }
            }
        }
    }
}
