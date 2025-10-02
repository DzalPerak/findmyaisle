<script setup lang="ts">
import { onMounted, watch, ref, reactive } from "vue";
import { Modal } from 'flowbite';
import { useAppearance } from '@/composables/useAppearance';
import { loadDxfFile } from "../pathfinder/dxf_loader.js";
import {
    createFinder,
    findPath,
    createGridFromLineSegments,
    applyWallBufferToGrid,
    smoothPathLineOfSight,
} from "../pathfinder/pathfinding.js";

import {
    distanceMatrix,
    tspOptimal,
    calculateTotalDistance,
} from "../pathfinder/tsp_solver.js";

// Props
const props = defineProps({
    shopId: {
        type: Number,
        default: null
    },
    isUserMode: {
        type: Boolean,
        default: false
    }
});

const { appearance } = useAppearance();

let canvas;
let ctx;

// Flowbite modal instance
let loadingModalInstance = null;

let grid = [];
let lineSegments = [];
let bounds = null;
let useOnDemandRendering = false;
let waypoints = []; // Array of {id, name, x, y, selected} objects
let waypointCounter = 1; // Counter for unique waypoint IDs
let selectedWaypoints = new Set(); // Track selected waypoint IDs
const contextMenu = reactive({ visible: false, x: 0, y: 0, waypointId: null });
let finder = createFinder();

// For pathfinding when using on-demand rendering
let pathfindingGrid = null;
let pathfindingScaleFactor = 1;
let wallBuffer = 2; // Default wall buffer size

// Store computed path and waypoint order
let computedPath = [];
let waypointOrder = [];
let pathDistanceInfo = null;

// Viewport state
let viewport = {
    x: 0,
    y: 0,
    zoom: 1,
    minZoom: 0.01,
    maxZoom: 0.5,
};

let isDragging = false;
let isMiddleDragging = false;
let lastMousePos = { x: 0, y: 0 };
let isAddingWaypoints = false;

// Wall editing state
let isEditingMode = false;
let editingTool = 'none'; // 'add', 'remove', 'cut', 'none'
let isDrawingWall = false;
let currentWallStart = null;
let tempWallEnd = null;
let isSelectingWallPortion = false;
let selectionStart = null;
let selectionEnd = null;

// High DPI canvas setup
function setupHighDPICanvas(canvas, ctx) {
    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    
    ctx.scale(dpr, dpr);
    canvas.style.width = rect.width + 'px';
    canvas.style.height = rect.height + 'px';
}

// Function to check if dark mode is active
function isDarkMode() {
    if (appearance.value === 'dark') return true;
    if (appearance.value === 'light') return false;
    // For 'system', check if the dark class is applied to document.documentElement
    return document.documentElement.classList.contains('dark');
}

// Notification system using Flowbite Toast
function showNotification(title, message, type = "info") {
    const container = document.getElementById("toast-container");
    if (!container) return;

    // Create Flowbite toast
    const toast = document.createElement("div");
    toast.className = "flex items-center w-full max-w-xs p-4 text-gray-500 bg-white rounded-lg shadow dark:text-gray-400 dark:bg-gray-800";
    
    // Icon based on type
    let iconClass = "";
    let iconBg = "";
    switch(type) {
        case "success":
            iconClass = "w-3 h-3";
            iconBg = "bg-green-100 text-green-500 dark:bg-green-800 dark:text-green-200";
            break;
        case "error":
            iconClass = "w-3 h-3";
            iconBg = "bg-red-100 text-red-500 dark:bg-red-800 dark:text-red-200";
            break;
        case "warning":
            iconClass = "w-3 h-3";
            iconBg = "bg-orange-100 text-orange-500 dark:bg-orange-700 dark:text-orange-200";
            break;
        default: // info
            iconClass = "w-3 h-3";
            iconBg = "bg-blue-100 text-blue-500 dark:bg-blue-800 dark:text-blue-200";
    }

    toast.innerHTML = `
        <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 ${iconBg} rounded-lg">
            <div class="${iconClass}">
                <span class="sr-only">${type} icon</span>
                <div class="w-3 h-3 bg-current rounded-full"></div>
            </div>
        </div>
        <div class="ms-3 text-sm font-normal">
            <div class="font-semibold">${title}</div>
            <div class="text-xs">${message}</div>
        </div>
        <button type="button" class="ms-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex items-center justify-center h-8 w-8 dark:text-gray-500 dark:hover:text-white dark:bg-gray-800 dark:hover:bg-gray-700">
            <span class="sr-only">Close</span>
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
            </svg>
        </button>
    `;

    // Add close functionality
    const closeButton = toast.querySelector('button');
    closeButton.addEventListener('click', () => {
        toast.remove();
    });

    container.appendChild(toast);

    // Auto-remove after 4 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 4000);
}

// Loading modal system using Flowbite
function showLoadingModal(text = "Loading...") {
    const loadingText = document.getElementById("loadingText");
    
    if (loadingText) {
        loadingText.textContent = text;
    }
    
    if (!loadingModalInstance) {
        const modalElement = document.getElementById("loadingModal");
        if (modalElement) {
            loadingModalInstance = new Modal(modalElement, {
                backdrop: 'static',
                closable: false
            });
        }
    }
    
    if (loadingModalInstance) {
        loadingModalInstance.show();
    }
}

function hideLoadingModal() {
    if (loadingModalInstance) {
        loadingModalInstance.hide();
    }
}

// Function to load DXF file from shop API
async function loadDxfFromShop(shopId) {
    if (!shopId) return;
    
    showLoadingModal("Loading shop floor plan...");
    
    try {
        console.log("Loading DXF for shop ID:", shopId);
        
        // Fetch the DXF file from the shop API with cache-busting
        const response = await fetch(`/api/shops/${shopId}/dxf?t=${Date.now()}`);
        
        if (!response.ok) {
            throw new Error(`Failed to fetch DXF: ${response.statusText}`);
        }
        
        // Convert response to blob and then to file
        const blob = await response.blob();
        const fileName = `shop_${shopId}_layout.dxf`;
        const file = new File([blob], fileName, { type: 'application/octet-stream' });
        
        console.log("Loading DXF file from shop:", fileName);
        const result = await loadDxfFile(file);

        if (result.useOnDemandRendering) {
            lineSegments = result.lineSegments;
            bounds = result.bounds;
            useOnDemandRendering = true;
            grid = [];

            // Create a smaller grid for pathfinding
            const pathfindingResult = createGridFromLineSegments(
                result.lineSegments,
                result.bounds,
                1000,
                wallBuffer
            );
            if (pathfindingResult) {
                pathfindingGrid = pathfindingResult.grid;
                pathfindingScaleFactor = pathfindingResult.scaleFactor;
                console.log(
                    "Created pathfinding grid with scale factor:",
                    pathfindingScaleFactor
                );
            }

            console.log("Using on-demand rendering for large DXF");
        } else {
            grid = result.grid;
            bounds = result.bounds;
            lineSegments = [];
            useOnDemandRendering = false;
            // Apply wall buffer to regular grid for pathfinding
            pathfindingGrid = applyWallBufferToGrid(result.grid, wallBuffer);
            pathfindingScaleFactor = 1;
        }

        console.log(
            "Shop DXF loaded successfully. Grid size:",
            result.width,
            "x",
            result.height
        );

        // Reset viewport to show entire drawing
        resetViewport();

        // Load any saved layout data (waypoints, etc.) after DXF is loaded
        await loadSavedLayoutData(shopId);

        showLoadingModal("Drawing floor plan...");
        setTimeout(() => {
            drawGrid();
            hideLoadingModal();
            showNotification(
                "Floor Plan Loaded!",
                `Shop floor plan loaded successfully. Grid size: ${result.width}x${result.height}`,
                "success"
            );
        }, 100);
        
    } catch (error) {
        console.error("Error loading shop DXF:", error);
        hideLoadingModal();
        showNotification(
            "Error Loading Floor Plan",
            `Failed to load shop floor plan: ${error.message}`,
            "error"
        );
    }
}

async function loadSavedLayoutData(shopId) {
    if (!shopId) return;
    
    try {
        console.log("Loading saved layout data for shop ID:", shopId);
        
        const response = await fetch(`/api/shops/${shopId}/layout`);
        
        if (!response.ok) {
            // If no saved layout exists, that's OK - just continue with DXF only
            if (response.status === 404) {
                console.log("No saved layout data found, using DXF only");
                return;
            }
            throw new Error(`Failed to fetch saved layout: ${response.statusText}`);
        }
        
        const savedData = await response.json();
        console.log("Loaded saved layout data:", savedData);
        
        // Restore waypoints if they exist in saved data (check both locations)
        let waypointsData = null;
        let waypointCounterData = null;
        
        if (savedData.metadata && savedData.metadata.waypoints) {
            // Waypoints stored in metadata (new format)
            waypointsData = savedData.metadata.waypoints;
            waypointCounterData = savedData.metadata.waypointCounter;
        } else if (savedData.waypoints) {
            // Waypoints stored at root level (fallback)
            waypointsData = savedData.waypoints;
            waypointCounterData = savedData.waypointCounter;
        }
        
        if (waypointsData && Array.isArray(waypointsData)) {
            waypoints.length = 0; // Clear existing waypoints
            waypoints.push(...waypointsData);
            
            // Restore waypoint counter
            if (waypointCounterData) {
                waypointCounter = waypointCounterData;
            }
            
            // Clear any selection state from saved waypoints
            selectedWaypoints.clear();
            waypoints.forEach(wp => wp.selected = false);
            
            console.log(`Restored ${waypoints.length} waypoints`);
            
            showNotification(
                "Layout Restored",
                `Loaded ${waypoints.length} saved waypoint${waypoints.length !== 1 ? 's' : ''}`,
                "success"
            );
        }
        
        // If saved layout has modified line segments, we could restore those too
        // but for now we'll keep the DXF as the source of truth for walls
        
    } catch (error) {
        console.error("Error loading saved layout data:", error);
        // Don't show error notification - this is optional data
        // The DXF file is the primary source, saved layout is supplementary
    }
}

onMounted(() => {
    canvas = document.getElementById("gridCanvas");
    ctx = canvas.getContext("2d");
    
    // Fix canvas resolution for high DPI displays
    setupHighDPICanvas(canvas, ctx);

    // Auto-load DXF if shopId is provided
    if (props.shopId) {
        loadDxfFromShop(props.shopId);
    }

    const dxfFileElement = document.getElementById("dxfFile");
    if (dxfFileElement) {
        dxfFileElement.addEventListener("change", async (e) => {
            const target = e.target as HTMLInputElement;
            const file = target.files?.[0];

        if (!file) {
            return;
        }

        showLoadingModal("Loading DXF file...");

        try {
            console.log("Loading DXF file:", file.name);
            const result = await loadDxfFile(file);

            if (result.useOnDemandRendering) {
                lineSegments = result.lineSegments;
                bounds = result.bounds;
                useOnDemandRendering = true;
                grid = [];

                // Create a smaller grid for pathfinding
                const pathfindingResult = createGridFromLineSegments(
                    result.lineSegments,
                    result.bounds,
                    1000,
                    wallBuffer
                );
                if (pathfindingResult) {
                    pathfindingGrid = pathfindingResult.grid;
                    pathfindingScaleFactor = pathfindingResult.scaleFactor;
                    console.log(
                        "Created pathfinding grid with scale factor:",
                        pathfindingScaleFactor
                    );
                }

                console.log("Using on-demand rendering for large DXF");
            } else {
                grid = result.grid;
                bounds = result.bounds;
                lineSegments = [];
                useOnDemandRendering = false;
                // Apply wall buffer to regular grid for pathfinding
                pathfindingGrid = applyWallBufferToGrid(result.grid, wallBuffer);
                pathfindingScaleFactor = 1;
            }

            console.log(
                "DXF loaded successfully. Grid size:",
                result.width,
                "x",
                result.height
            );

            // Reset viewport to show entire drawing
            resetViewport();

            showLoadingModal("Drawing grid...");
            setTimeout(() => {
                drawGrid();
                hideLoadingModal();
                showNotification(
                    "Success!",
                    `DXF file loaded successfully. Grid size: ${result.width}x${result.height}`,
                    "success"
                );
            }, 100);
        } catch (error) {
            console.error("Error loading DXF file:", error);
            hideLoadingModal();
            showNotification(
                "Error",
                `Failed to load DXF file: ${error.message}`,
                "error"
            );
        }
    });
    }

    const addWpBtn = document.getElementById("addWpBtn");
    if (addWpBtn) {
        addWpBtn.addEventListener("click", () => {
            toggleWaypointMode();
        });
    }

    const clearWpBtn = document.getElementById("clearWpBtn");
    if (clearWpBtn) {
        clearWpBtn.addEventListener("click", () => {
        const waypointCount = waypoints.length;
        waypoints = [];
        waypointCounter = 1;
        selectedWaypoints.clear();
        contextMenu.visible = false;
        computedPath = [];
        waypointOrder = [];
        pathDistanceInfo = null;
        console.log("All waypoints cleared");
        drawGrid();
            showNotification(
                "Waypoints Cleared",
                `Removed ${waypointCount} waypoint${waypointCount !== 1 ? "s" : ""}`,
                "info"
            );
        });
    }

    // Wall buffer control - separate input and change events
    const wallBufferSlider = document.getElementById("wallBufferSlider");
    if (wallBufferSlider) {
        // Update display while dragging (no notification)
        wallBufferSlider.addEventListener("input", (e) => {
        const target = e.target as HTMLInputElement;
        wallBuffer = parseInt(target.value);
        const valueDisplay = document.getElementById("wallBufferValue");
        if (valueDisplay) valueDisplay.textContent = wallBuffer.toString();
    });
    
    // Update pathfinding and show notification only when finished (mouseup/touchend)
    const handleWallBufferChange = () => {
        // Regenerate the pathfinding grid with the new wall buffer
        regeneratePathfindingGrid();
        
        // Clear existing path since pathfinding grid has changed
        computedPath = [];
        waypointOrder = [];
        pathDistanceInfo = null;
        
        // Redraw to update display
        drawGrid();

        showNotification(
            "Wall Distance Updated",
            `Wall buffer set to ${wallBuffer} cells`,
            "info"
        );
    };
    
        wallBufferSlider.addEventListener("mouseup", handleWallBufferChange);
        wallBufferSlider.addEventListener("touchend", handleWallBufferChange);
    }

    // Wall editing controls
    const editModeBtn = document.getElementById("editModeBtn");
    if (editModeBtn) {
        editModeBtn.addEventListener("click", () => {
            toggleEditingMode();
        });
    }

    const addWallBtn = document.getElementById("addWallBtn");
    if (addWallBtn) {
        addWallBtn.addEventListener("click", () => {
            setEditingTool('add');
        });
    }

    const removeWallBtn = document.getElementById("removeWallBtn");
    if (removeWallBtn) {
        removeWallBtn.addEventListener("click", () => {
            setEditingTool('remove');
        });
    }

    // Mouse event handlers
    canvas.addEventListener("mousedown", (e) => {
        if (e.button === 1) {
            // Middle mouse button
            e.preventDefault();
            isMiddleDragging = true;
            lastMousePos = { x: e.clientX, y: e.clientY };
            canvas.style.cursor = "grabbing";
            return;
        }

        if (e.button === 0) {
            // Left mouse button
            if (isAddingWaypoints) {
                // In waypoint mode, don't start dragging
                return;
            }
            
            if (isEditingMode && editingTool === 'add') {
                // Start drawing a wall
                const rect = canvas.getBoundingClientRect();
                const canvasX = e.clientX - rect.left;
                const canvasY = e.clientY - rect.top;
                
                // Account for canvas scaling
                const scaleX = canvas.width / rect.width;
                const scaleY = canvas.height / rect.height;
                const scaledCanvasX = canvasX * scaleX;
                const scaledCanvasY = canvasY * scaleY;
                
                // Convert to world coordinates
                const worldX = (scaledCanvasX - viewport.x) / viewport.zoom;
                const worldY = (scaledCanvasY - viewport.y) / viewport.zoom;
                
                currentWallStart = { x: worldX, y: worldY };
                isDrawingWall = true;
                return;
            }
            
            if (isEditingMode && editingTool === 'remove') {
                // Remove wall at click location
                const rect = canvas.getBoundingClientRect();
                const canvasX = e.clientX - rect.left;
                const canvasY = e.clientY - rect.top;
                
                // Account for canvas scaling
                const scaleX = canvas.width / rect.width;
                const scaleY = canvas.height / rect.height;
                const scaledCanvasX = canvasX * scaleX;
                const scaledCanvasY = canvasY * scaleY;
                
                // Convert to world coordinates
                const worldX = (scaledCanvasX - viewport.x) / viewport.zoom;
                const worldY = (scaledCanvasY - viewport.y) / viewport.zoom;
                
                removeWallNear({ x: worldX, y: worldY });
                drawGrid();
                return;
            }
            
            if (isEditingMode && editingTool === 'cut') {
                // Start selecting wall portion to remove
                const rect = canvas.getBoundingClientRect();
                const canvasX = e.clientX - rect.left;
                const canvasY = e.clientY - rect.top;
                
                // Account for canvas scaling
                const scaleX = canvas.width / rect.width;
                const scaleY = canvas.height / rect.height;
                const scaledCanvasX = canvasX * scaleX;
                const scaledCanvasY = canvasY * scaleY;
                
                // Convert to world coordinates
                const worldX = (scaledCanvasX - viewport.x) / viewport.zoom;
                const worldY = (scaledCanvasY - viewport.y) / viewport.zoom;
                
                isSelectingWallPortion = true;
                selectionStart = { x: worldX, y: worldY };
                selectionEnd = { x: worldX, y: worldY };
                return;
            }

            isDragging = true;
            lastMousePos = { x: e.clientX, y: e.clientY };
            canvas.style.cursor = "grabbing";
        }
    });

    canvas.addEventListener("mousemove", (e) => {
        if (isMiddleDragging) {
            // Middle mouse dragging - always works regardless of mode
            const deltaX = e.clientX - lastMousePos.x;
            const deltaY = e.clientY - lastMousePos.y;

            viewport.x += deltaX;
            viewport.y += deltaY;

            lastMousePos = { x: e.clientX, y: e.clientY };
            drawGrid();
            return;
        }
        
        if (isDrawingWall && currentWallStart) {
            // Update temporary wall end point while drawing
            const rect = canvas.getBoundingClientRect();
            const canvasX = e.clientX - rect.left;
            const canvasY = e.clientY - rect.top;
            
            // Account for canvas scaling
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            const scaledCanvasX = canvasX * scaleX;
            const scaledCanvasY = canvasY * scaleY;
            
            // Convert to world coordinates
            const worldX = (scaledCanvasX - viewport.x) / viewport.zoom;
            const worldY = (scaledCanvasY - viewport.y) / viewport.zoom;
            
            tempWallEnd = { x: worldX, y: worldY };
            drawGrid();
            return;
        }
        
        if (isSelectingWallPortion && selectionStart) {
            // Update selection end position
            const rect = canvas.getBoundingClientRect();
            const canvasX = e.clientX - rect.left;
            const canvasY = e.clientY - rect.top;
            
            // Account for canvas scaling
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            const scaledCanvasX = canvasX * scaleX;
            const scaledCanvasY = canvasY * scaleY;
            
            // Convert to world coordinates
            const worldX = (scaledCanvasX - viewport.x) / viewport.zoom;
            const worldY = (scaledCanvasY - viewport.y) / viewport.zoom;
            
            selectionEnd = { x: worldX, y: worldY };
            drawGrid();
            return;
        }

        if (isDragging && !isAddingWaypoints && !isEditingMode) {
            // Left mouse dragging - only in non-waypoint and non-editing mode
            const deltaX = e.clientX - lastMousePos.x;
            const deltaY = e.clientY - lastMousePos.y;

            viewport.x += deltaX;
            viewport.y += deltaY;

            lastMousePos = { x: e.clientX, y: e.clientY };
            drawGrid();
        }
    });

    canvas.addEventListener("mouseup", (e) => {
        if (e.button === 1) {
            // Middle mouse button
            isMiddleDragging = false;
            const cursor = isAddingWaypoints ? "crosshair" : (isEditingMode ? "crosshair" : "grab");
            canvas.style.cursor = cursor;
            return;
        }

        if (e.button === 0) {
            // Left mouse button
            if (isDrawingWall && currentWallStart && tempWallEnd) {
                // Complete the wall drawing
                addWallSegment(currentWallStart, tempWallEnd);
                
                // Reset drawing state
                isDrawingWall = false;
                currentWallStart = null;
                tempWallEnd = null;
                
                drawGrid();
                return;
            }
            
            if (isSelectingWallPortion && selectionStart && selectionEnd) {
                // Complete wall portion selection and remove
                removeWallPortion(selectionStart, selectionEnd);
                
                // Reset selection state
                isSelectingWallPortion = false;
                selectionStart = null;
                selectionEnd = null;
                
                drawGrid();
                return;
            }
            
            isDragging = false;
            if (!isAddingWaypoints && !isEditingMode) {
                canvas.style.cursor = "grab";
            }
        }
    });

    canvas.addEventListener("mouseleave", () => {
        isDragging = false;
        isMiddleDragging = false;
        if (!isAddingWaypoints) {
            canvas.style.cursor = "default";
        }
    });

    canvas.addEventListener("click", (e) => {
        if (e.button === 0) { // Left click only
            // Hide context menu if visible
            contextMenu.visible = false;
            
            if (isAddingWaypoints) {
                if (props.isUserMode) {
                    // In user mode, select existing waypoints
                    selectWaypointAtPosition(e);
                } else {
                    // In edit mode, add new waypoints
                    addWaypoint(e);
                }
            }
        }
    });

    // Handle right-click context menu
    canvas.addEventListener("contextmenu", (e) => {
        e.preventDefault(); // Prevent default context menu
        
        if (!props.isUserMode) {
            // Show waypoint context menu if right-clicking on a waypoint
            const waypoint = getWaypointAtPosition(e);
            if (waypoint) {
                contextMenu.visible = true;
                contextMenu.x = e.clientX;
                contextMenu.y = e.clientY;
                contextMenu.waypointId = waypoint.id;
            } else {
                contextMenu.visible = false;
            }
        }
    });

    canvas.addEventListener("auxclick", (e) => {
        if (e.button === 1) {
            // Middle mouse button
            e.preventDefault(); // Prevent middle-click default behavior
        }
    });

    // Hide context menu when clicking outside
    document.addEventListener("click", (e) => {
        contextMenu.visible = false;
    });

    // Keyboard event handlers
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            if (isAddingWaypoints) {
                toggleWaypointMode();
            }
            contextMenu.visible = false;
            selectedWaypoints.clear();
            drawGrid();
        }
    });

    // Zoom with mouse wheel
    canvas.addEventListener("wheel", (e) => {
        e.preventDefault();

        const rect = canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;

        // Account for canvas scaling
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        const scaledMouseX = mouseX * scaleX;
        const scaledMouseY = mouseY * scaleY;

        const zoomFactor = e.deltaY > 0 ? 0.9 : 1.1;
        const newZoom = viewport.zoom * zoomFactor;

        if (newZoom >= viewport.minZoom && newZoom <= viewport.maxZoom) {
            // Zoom towards mouse position
            viewport.x = scaledMouseX - (scaledMouseX - viewport.x) * zoomFactor;
            viewport.y = scaledMouseY - (scaledMouseY - viewport.y) * zoomFactor;
            viewport.zoom = newZoom;

            drawGrid();
        }
    });

    const computePathBtn = document.getElementById("computePathBtn");
    if (computePathBtn) {
        computePathBtn.addEventListener("click", () => {
            // In user mode, only use selected waypoints
            const waypointsToUse = props.isUserMode ? waypoints.filter(wp => wp.selected || selectedWaypoints.has(wp.id)) : waypoints;
        
        if (waypointsToUse.length < 2) {
            const modeText = props.isUserMode ? "selected waypoints" : "waypoints";
            showNotification(
                "Insufficient Waypoints",
                `At least 2 ${modeText} are required to compute a path`,
                "error"
            );
            return;
        }

        // Use the appropriate grid for pathfinding
        const gridForPathfinding = pathfindingGrid || grid;
        if (!gridForPathfinding || gridForPathfinding.length === 0) {
            showNotification(
                "No Grid Available",
                "Please load a DXF file first",
                "error"
            );
            return;
        }

        showLoadingModal("Computing optimal path...");

        // Use setTimeout to allow the UI to update before starting heavy computation
        setTimeout(() => {
            // Scale waypoints if using a scaled pathfinding grid
            let scaledWaypoints = waypointsToUse;
            if (useOnDemandRendering && pathfindingScaleFactor !== 1) {
                scaledWaypoints = waypointsToUse.map((wp) => [
                    Math.round(wp.x * pathfindingScaleFactor),
                    Math.round(wp.y * pathfindingScaleFactor),
                ]);
                console.log("Original waypoints:", waypointsToUse);
                console.log("Scaled waypoints for pathfinding:", scaledWaypoints);
                console.log("Pathfinding scale factor:", pathfindingScaleFactor);
            } else {
                // For normal rendering, convert world coordinates to grid coordinates
                scaledWaypoints = waypointsToUse.map((wp) => [
                    Math.round(wp.x - bounds.minX) + 2,
                    Math.round(wp.y - bounds.minY) + 2,
                ]);
                console.log("Original waypoints:", waypointsToUse);
                console.log("Converted waypoints for pathfinding:", scaledWaypoints);
            }

            console.log(
                `Computing optimal path for ${scaledWaypoints.length} waypoints...`
            );
            console.log("Waypoints being used:", scaledWaypoints);

            try {
                // Calculate distance matrix and get paths
                const result = distanceMatrix(
                    gridForPathfinding,
                    scaledWaypoints,
                    finder,
                    findPath
                );
                const distMat = result.distances;
                const pathMat = result.paths;

                // Use optimal TSP solver
                const startTime = performance.now();
                const order = tspOptimal(distMat);
                const tspTime = performance.now() - startTime;

                console.log(
                    `TSP solved in ${tspTime.toFixed(2)}ms for ${
                        scaledWaypoints.length
                    } waypoints`
                );

                // Calculate total distance
                let distanceInfo = calculateTotalDistance(order, distMat, pathMat);

                // Scale distance back to original coordinates if needed
                if (useOnDemandRendering && pathfindingScaleFactor !== 1) {
                    console.log("Scaling distances back from pathfinding scale");
                    console.log("Original distance info:", distanceInfo);
                    console.log("Scale factor:", pathfindingScaleFactor);

                    // Scale all distances back to original coordinate system
                    const scaleFactor = 1 / pathfindingScaleFactor;
                    distanceInfo = {
                        pathLength: distanceInfo.pathLength * scaleFactor,
                        euclideanDistance: distanceInfo.euclideanDistance * scaleFactor,
                        pathLengthCm: distanceInfo.pathLengthCm * scaleFactor,
                        euclideanDistanceCm:
                            distanceInfo.euclideanDistanceCm * scaleFactor,
                    };

                    console.log("Scaled distance info:", distanceInfo);
                }

                // Check if smoothing is enabled
                const smoothingElement = document.getElementById("pathSmoothingCheckbox") as HTMLInputElement;
                const isPathSmoothingEnabled = smoothingElement ? smoothingElement.checked : false;

                let fullPath = [];

                // Build the full path using pre-computed segments
                for (let i = 0; i < order.length - 1; i++) {
                    const from = order[i];
                    const to = order[i + 1];
                    let seg = [...pathMat[from][to]]; // Copy the pre-computed path

                    if (!seg || seg.length === 0) {
                        console.error(
                            `No path found for segment ${i} (${from} -> ${to})`
                        );
                        continue;
                    }

                    if (i > 0) seg.shift(); // Remove first point to avoid duplication

                    // Apply smoothing to this segment if enabled
                    if (isPathSmoothingEnabled && seg.length > 2) {
                        const originalSegLength = seg.length;
                        seg = smoothPathLineOfSight(seg, gridForPathfinding);
                        console.log(
                            `Smoothed segment ${i} from ${originalSegLength} to ${seg.length} points`
                        );
                    }

                    fullPath = fullPath.concat(seg);
                }

                // Convert path back to world coordinates
                if (useOnDemandRendering && pathfindingScaleFactor !== 1) {
                    // Scale back from pathfinding grid to world coordinates
                    fullPath = fullPath.map((point) => [
                        point[0] / pathfindingScaleFactor,
                        point[1] / pathfindingScaleFactor,
                    ]);
                } else {
                    // Convert from grid coordinates back to world coordinates
                    fullPath = fullPath.map((point) => [
                        point[0] - 2 + bounds.minX,
                        point[1] - 2 + bounds.minY,
                    ]);
                }

                console.log("Computed path with", fullPath.length, "points");
                console.log("Distance info:", distanceInfo);

                // Store the computed path, order, and distance info
                computedPath = fullPath;
                waypointOrder = order;
                pathDistanceInfo = distanceInfo;

                hideLoadingModal();

                // Show detailed success notification with distance information
                const pathLengthCm = distanceInfo.pathLengthCm.toFixed(1);
                const pathLengthM = (distanceInfo.pathLengthCm / 100).toFixed(2);

                showNotification(
                    "Optimal Path Computed!",
                    `Path: ${
                        fullPath.length
                    } points | Distance: ${pathLengthCm} cm (${pathLengthM} m) | TSP solved in ${tspTime.toFixed(
                        1
                    )}ms`,
                    "success"
                );

                drawGrid();
            } catch (error) {
                console.error("Pathfinding error:", error);
                hideLoadingModal();
                showNotification(
                    "Pathfinding Error",
                    `Failed to compute path: ${error.message}`,
                    "error"
                );
            }
        }, 50); // Small delay to allow UI to update
        });
    }

    // Zoom and viewport controls
    const resetViewBtn = document.getElementById("resetViewBtn");
    if (resetViewBtn) {
        resetViewBtn.addEventListener("click", () => {
        resetViewport();
        drawGrid();
            showNotification(
                "View Reset",
                "Viewport has been reset to show entire drawing",
                "info"
            );
        });
    }

    // Set initial canvas cursor and viewport info
    canvas.style.cursor = "grab";

    // Update viewport info whenever drawing happens
    const originalDrawGrid = drawGrid;
    (drawGrid as any) = function (...args: any[]) {
        originalDrawGrid.apply(this, args);
        updateViewportInfo();
    };
});

// Watch for shopId changes and auto-load DXF
watch(() => props.shopId, (newShopId) => {
    if (newShopId) {
        loadDxfFromShop(newShopId);
    }
});

// Waypoint interaction functions
function getWaypointAtPosition(event) {
    const rect = canvas.getBoundingClientRect();
    const canvasX = event.clientX - rect.left;
    const canvasY = event.clientY - rect.top;
    
    // Account for canvas scaling
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    const scaledCanvasX = canvasX * scaleX;
    const scaledCanvasY = canvasY * scaleY;
    
    // Convert canvas coordinates to world coordinates
    const worldX = (scaledCanvasX - viewport.x) / viewport.zoom;
    const worldY = (scaledCanvasY - viewport.y) / viewport.zoom;
    
    // Check if click is near any waypoint (within 20 world units)
    const clickRadius = 20 / viewport.zoom; // Adjust click radius based on zoom
    
    for (const waypoint of waypoints) {
        const distance = Math.sqrt(
            Math.pow(worldX - waypoint.x, 2) + Math.pow(worldY - waypoint.y, 2)
        );
        if (distance <= clickRadius) {
            return waypoint;
        }
    }
    
    return null;
}

function selectWaypointAtPosition(event) {
    const waypoint = getWaypointAtPosition(event);
    if (waypoint) {
        // Toggle selection state
        if (selectedWaypoints.has(waypoint.id)) {
            selectedWaypoints.delete(waypoint.id);
            waypoint.selected = false;
        } else {
            selectedWaypoints.add(waypoint.id);
            waypoint.selected = true;
        }
        drawGrid();
        
        console.log(`Waypoint ${waypoint.id} "${waypoint.name}" ${waypoint.selected ? 'selected' : 'deselected'}`);
        
        const selectedCount = selectedWaypoints.size;
        showNotification(
            "Waypoint Selection",
            `${selectedCount} waypoint${selectedCount !== 1 ? 's' : ''} selected`,
            "info"
        );
    }
}

function renameWaypoint() {
    const waypoint = waypoints.find(wp => wp.id === contextMenu.waypointId);
    if (waypoint) {
        const newName = prompt(`Enter new name for waypoint:`, waypoint.name);
        if (newName && newName.trim()) {
            waypoint.name = newName.trim();
            drawGrid();
            showNotification("Waypoint Renamed", `Renamed to "${waypoint.name}"`, "success");
        }
    }
    contextMenu.visible = false;
}

function deleteWaypoint() {
    const waypointIndex = waypoints.findIndex(wp => wp.id === contextMenu.waypointId);
    if (waypointIndex >= 0) {
        const waypoint = waypoints[waypointIndex];
        if (confirm(`Delete waypoint "${waypoint.name}"?`)) {
            waypoints.splice(waypointIndex, 1);
            selectedWaypoints.delete(waypoint.id);
            
            // Clear any computed paths that might include this waypoint
            computedPath = [];
            waypointOrder = [];
            pathDistanceInfo = null;
            
            drawGrid();
            showNotification("Waypoint Deleted", `"${waypoint.name}" has been deleted`, "success");
        }
    }
    contextMenu.visible = false;
}

// Export current layout data
function exportLayoutData() {
    return {
        lineSegments: lineSegments,
        waypoints: waypoints,
        waypointCounter: waypointCounter,
        bounds: bounds,
        useOnDemandRendering: useOnDemandRendering,
        grid: grid
    };
}

// Save current layout to server
async function saveLayoutToServer() {
    if (!props.shopId) {
        showNotification(
            "Save Error", 
            "No shop selected for saving", 
            "error"
        );
        return false;
    }

    showLoadingModal("Saving layout...");

    try {
        const layoutData = exportLayoutData();
        console.log("Saving layout with waypoints:", layoutData.waypoints);
        console.log("Current waypointCounter:", layoutData.waypointCounter);
        
        // Convert layout data to a format that can be sent to server
        // Include lineSegments and bounds as required by server, plus waypoints
        const saveData = {
            lineSegments: layoutData.lineSegments,
            bounds: layoutData.bounds,
            metadata: {
                useOnDemandRendering: layoutData.useOnDemandRendering,
                wallBuffer: wallBuffer,
                savedAt: new Date().toISOString(),
                // Store waypoint data in metadata
                waypoints: layoutData.waypoints,
                waypointCounter: layoutData.waypointCounter
            }
        };

        const response = await fetch(`/api/shops/${props.shopId}/layout`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify(saveData)
        });
        
        console.log("Sent save data:", saveData);

        if (!response.ok) {
            let errorMessage = response.statusText;
            try {
                const errorData = await response.json();
                
                // Handle CSRF token mismatch specifically
                if (response.status === 419 || (errorData.message && errorData.message.includes('CSRF'))) {
                    errorMessage = 'Session expired. Please refresh the page and try again.';
                } else {
                    errorMessage = errorData.message || errorMessage;
                }
            } catch (e) {
                // If we can't parse the JSON error, use the status text
                if (response.status === 419) {
                    errorMessage = 'Session expired. Please refresh the page and try again.';
                }
            }
            throw new Error(`HTTP ${response.status}: ${errorMessage}`);
        }

        const result = await response.json();
        hideLoadingModal();
        
        const waypointCount = waypoints.length;
        console.log(`Successfully saved layout with ${waypointCount} waypoints`);
        
        showNotification(
            "Layout Saved",
            `Layout saved successfully with ${waypointCount} waypoint${waypointCount !== 1 ? 's' : ''}`,
            "success"
        );
        return true;

    } catch (error) {
        console.error("Error saving layout:", error);
        hideLoadingModal();
        showNotification(
            "Save Failed",
            `Failed to save layout: ${error.message}`,
            "error"
        );
        return false;
    }
}

// Expose functions for external use
(window as any).pathfinderExports = {
    exportLayoutData,
    saveLayoutToServer
};

function regeneratePathfindingGrid() {
    if (!bounds) return;

    console.log(`Regenerating pathfinding grid with wall buffer: ${wallBuffer}`);

    // Always regenerate from current lineSegments to reflect wall edits
    if (lineSegments.length > 0) {
        const pathfindingResult = createGridFromLineSegments(
            lineSegments,
            bounds,
            1000,
            wallBuffer
        );
        if (pathfindingResult) {
            pathfindingGrid = pathfindingResult.grid;
            pathfindingScaleFactor = pathfindingResult.scaleFactor;
            
            // Also update the main grid if we're not using on-demand rendering
            if (!useOnDemandRendering) {
                grid = pathfindingResult.grid;
            }
        }
    } else {
        // No line segments, create empty grid
        pathfindingGrid = [];
        grid = [];
    }

    console.log("Pathfinding grid regenerated");
}

// Wall editing functions
function toggleEditingMode() {
    isEditingMode = !isEditingMode;
    
    if (isEditingMode) {
        // Exit waypoint mode if active
        if (isAddingWaypoints) {
            toggleWaypointMode();
        }
        
        canvas.style.cursor = "crosshair";
        showNotification(
            "Editing Mode Activated",
            "Select a tool to start editing walls",
            "info"
        );
    } else {
        // Reset editing state
        editingTool = 'none';
        isDrawingWall = false;
        currentWallStart = null;
        tempWallEnd = null;
        
        canvas.style.cursor = "grab";
        showNotification(
            "Editing Mode Deactivated",
            "Editing tools are now disabled",
            "info"
        );
        
        // Update button states
        updateEditingButtons();
    }
    
    updateEditingButtons();
    drawGrid();
}

function setEditingTool(tool) {
    if (!isEditingMode) {
        showNotification(
            "Editing Mode Required",
            "Please activate editing mode first",
            "error"
        );
        return;
    }
    
    // Reset current drawing state when switching tools
    isDrawingWall = false;
    currentWallStart = null;
    tempWallEnd = null;
    isSelectingWallPortion = false;
    selectionStart = null;
    selectionEnd = null;
    
    editingTool = tool;
    
    if (tool === 'add') {
        canvas.style.cursor = "crosshair";
        showNotification(
            "Add Wall Tool",
            "Click and drag to draw new walls",
            "info"
        );
    } else if (tool === 'remove') {
        canvas.style.cursor = "crosshair";
        showNotification(
            "Remove Wall Tool",
            "Click on walls to remove them",
            "info"
        );
    } else if (tool === 'cut') {
        canvas.style.cursor = "crosshair";
        showNotification(
            "Select & Remove Tool",
            "Click and drag to select a portion of wall to remove",
            "info"
        );
    }
    
    updateEditingButtons();
    drawGrid();
}

function updateEditingButtons() {
    // Base class for all buttons - keeping the original nice styling
    const baseClass = "py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border not-dark:border-gray-200 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700";
    
    // Update editing mode button
    const editBtn = document.getElementById("editModeBtn");
    if (editBtn) {
        if (isEditingMode) {
            editBtn.textContent = "✋ Stop Editing";
            editBtn.className = "py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border-2 border-orange-500 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-orange-500 dark:hover:text-white dark:hover:bg-gray-700";
        } else {
            editBtn.textContent = "✏️ Edit Walls";
            editBtn.className = baseClass;
        }
    }
    
    // Update tool buttons with colored borders for selection
    const addWallBtn = document.getElementById("addWallBtn");
    const removeWallBtn = document.getElementById("removeWallBtn");
    const cutWallBtn = document.getElementById("cutWallBtn");
    
    if (addWallBtn) {
        if (editingTool === 'add') {
            addWallBtn.className = "py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border-2 border-green-500 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-green-500 dark:hover:text-white dark:hover:bg-gray-700";
        } else {
            addWallBtn.className = baseClass;
        }
    }
    
    if (removeWallBtn) {
        if (editingTool === 'remove') {
            removeWallBtn.className = "py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border-2 border-red-500 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-red-500 dark:hover:text-white dark:hover:bg-gray-700";
        } else {
            removeWallBtn.className = baseClass;
        }
    }
    
    if (cutWallBtn) {
        if (editingTool === 'cut') {
            cutWallBtn.className = "py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border-2 border-yellow-500 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-yellow-500 dark:hover:text-white dark:hover:bg-gray-700";
        } else {
            cutWallBtn.className = baseClass;
        }
    }
}

function addWallSegment(start, end) {
    // Convert normalized coordinates back to absolute coordinates for storage
    // since the rendering system normalizes by subtracting bounds.minX/minY
    const absoluteStart = {
        x: start.x + (bounds ? bounds.minX : 0),
        y: start.y + (bounds ? bounds.minY : 0)
    };
    const absoluteEnd = {
        x: end.x + (bounds ? bounds.minX : 0),
        y: end.y + (bounds ? bounds.minY : 0)
    };
    
    const newSegment = {
        start: absoluteStart,
        end: absoluteEnd
    };
    
    lineSegments.push(newSegment);
    
    // Update bounds if necessary
    if (bounds) {
        bounds.minX = Math.min(bounds.minX, absoluteStart.x, absoluteEnd.x);
        bounds.minY = Math.min(bounds.minY, absoluteStart.y, absoluteEnd.y);
        bounds.maxX = Math.max(bounds.maxX, absoluteStart.x, absoluteEnd.x);
        bounds.maxY = Math.max(bounds.maxY, absoluteStart.y, absoluteEnd.y);
        bounds.width = bounds.maxX - bounds.minX;
        bounds.height = bounds.maxY - bounds.minY;
    }
    
    // Regenerate pathfinding grid
    regeneratePathfindingGrid();
    
    showNotification(
        "Wall Added",
        `New wall segment added from (${start.x.toFixed(1)}, ${start.y.toFixed(1)}) to (${end.x.toFixed(1)}, ${end.y.toFixed(1)})`,
        "success"
    );
}

function removeWallNear(worldPos, tolerance = 5) {
    const toleranceSquared = tolerance * tolerance;
    let removedCount = 0;
    
    // Convert click position to absolute coordinates for comparison with stored segments
    const absoluteClickPos = {
        x: worldPos.x + (bounds ? bounds.minX : 0),
        y: worldPos.y + (bounds ? bounds.minY : 0)
    };
    
    // Remove line segments that are close to the click point
    lineSegments = lineSegments.filter(segment => {
        const distToStart = Math.pow(segment.start.x - absoluteClickPos.x, 2) + Math.pow(segment.start.y - absoluteClickPos.y, 2);
        const distToEnd = Math.pow(segment.end.x - absoluteClickPos.x, 2) + Math.pow(segment.end.y - absoluteClickPos.y, 2);
        
        // Also check distance to the line segment itself
        const distToLine = pointToLineDistance(absoluteClickPos, segment.start, segment.end);
        
        if (distToStart <= toleranceSquared || distToEnd <= toleranceSquared || distToLine <= tolerance) {
            removedCount++;
            return false; // Remove this segment
        }
        return true; // Keep this segment
    });
    
    if (removedCount > 0) {
        // Regenerate pathfinding grid
        regeneratePathfindingGrid();
        
        showNotification(
            "Wall Removed",
            `Removed ${removedCount} wall segment${removedCount !== 1 ? 's' : ''}`,
            "success"
        );
    } else {
        showNotification(
            "No Wall Found",
            "No wall segments found near the click location",
            "info"
        );
    }
}

function cutWallAt(worldPos, tolerance = 5) {
    // Convert click position to absolute coordinates for comparison with stored segments
    const absoluteClickPos = {
        x: worldPos.x + (bounds ? bounds.minX : 0),
        y: worldPos.y + (bounds ? bounds.minY : 0)
    };
    
    let cutCount = 0;
    const newSegments = [];
    
    lineSegments.forEach(segment => {
        // Check if click is close to this line segment
        const distToLine = pointToLineDistance(absoluteClickPos, segment.start, segment.end);
        
        if (distToLine <= tolerance) {
            // Find the closest point on the line segment to the click position
            const cutPoint = getClosestPointOnLine(absoluteClickPos, segment.start, segment.end);
            
            // Check if the cut point is actually on the line segment (not just the extended line)
            const distToStart = Math.sqrt(Math.pow(cutPoint.x - segment.start.x, 2) + Math.pow(cutPoint.y - segment.start.y, 2));
            const distToEnd = Math.sqrt(Math.pow(cutPoint.x - segment.end.x, 2) + Math.pow(cutPoint.y - segment.end.y, 2));
            const totalLength = Math.sqrt(Math.pow(segment.end.x - segment.start.x, 2) + Math.pow(segment.end.y - segment.start.y, 2));
            
            // Only cut if the point is actually on the segment (with a small tolerance for floating point errors)
            if (Math.abs(distToStart + distToEnd - totalLength) < 0.1 && distToStart > 1 && distToEnd > 1) {
                // Create two new segments
                const segment1 = {
                    start: { x: segment.start.x, y: segment.start.y },
                    end: { x: cutPoint.x, y: cutPoint.y }
                };
                const segment2 = {
                    start: { x: cutPoint.x, y: cutPoint.y },
                    end: { x: segment.end.x, y: segment.end.y }
                };
                
                newSegments.push(segment1, segment2);
                cutCount++;
            } else {
                // Keep the original segment if cut point is too close to endpoints
                newSegments.push(segment);
            }
        } else {
            // Keep segments that aren't close to the click
            newSegments.push(segment);
        }
    });
    
    if (cutCount > 0) {
        lineSegments = newSegments;
        
        // Regenerate pathfinding grid
        regeneratePathfindingGrid();
        
        showNotification(
            "Wall Cut",
            `Cut ${cutCount} wall segment${cutCount !== 1 ? 's' : ''} at click location`,
            "success"
        );
    } else {
        showNotification(
            "No Wall Found",
            "No wall segments found near the click location to cut",
            "info"
        );
    }
}

function getClosestPointOnLine(point, lineStart, lineEnd) {
    const A = point.x - lineStart.x;
    const B = point.y - lineStart.y;
    const C = lineEnd.x - lineStart.x;
    const D = lineEnd.y - lineStart.y;
    
    const dot = A * C + B * D;
    const lenSq = C * C + D * D;
    
    if (lenSq === 0) {
        // Line start and end are the same point
        return { x: lineStart.x, y: lineStart.y };
    }
    
    let param = dot / lenSq;
    
    // Clamp parameter to line segment
    param = Math.max(0, Math.min(1, param));
    
    return {
        x: lineStart.x + param * C,
        y: lineStart.y + param * D
    };
}

function pointToLineDistance(point, lineStart, lineEnd) {
    const A = point.x - lineStart.x;
    const B = point.y - lineStart.y;
    const C = lineEnd.x - lineStart.x;
    const D = lineEnd.y - lineStart.y;
    
    const dot = A * C + B * D;
    const lenSq = C * C + D * D;
    
    if (lenSq === 0) {
        // Line start and end are the same point
        return Math.sqrt(A * A + B * B);
    }
    
    let param = dot / lenSq;
    
    let xx, yy;
    
    if (param < 0) {
        xx = lineStart.x;
        yy = lineStart.y;
    } else if (param > 1) {
        xx = lineEnd.x;
        yy = lineEnd.y;
    } else {
        xx = lineStart.x + param * C;
        yy = lineStart.y + param * D;
    }
    
    const dx = point.x - xx;
    const dy = point.y - yy;
    
    return Math.sqrt(dx * dx + dy * dy);
}

function removeWallPortion(start, end) {
    // Convert selection coordinates to absolute coordinates
    const absoluteStart = {
        x: start.x + (bounds ? bounds.minX : 0),
        y: start.y + (bounds ? bounds.minY : 0)
    };
    const absoluteEnd = {
        x: end.x + (bounds ? bounds.minX : 0),
        y: end.y + (bounds ? bounds.minY : 0)
    };
    
    let removedCount = 0;
    const newSegments = [];
    
    lineSegments.forEach((segment, index) => {
        // Check if any part of this wall segment intersects with the selection area
        const intersects = lineIntersectsSelection(segment, absoluteStart, absoluteEnd);
        
        if (intersects) {
            // Remove the intersecting portion and keep the remaining parts
            const remainingParts = splitSegmentBySelection(segment, absoluteStart, absoluteEnd);
            newSegments.push(...remainingParts);
            if (remainingParts.length < 1) {
                removedCount++;
            } else if (remainingParts.length === 1 && remainingParts[0] !== segment) {
                // Part of the segment was removed
                removedCount++;
            }
        } else {
            // Keep segments that don't intersect
            newSegments.push(segment);
        }
    });
    
    lineSegments = newSegments;
    
    // Regenerate pathfinding grid
    regeneratePathfindingGrid();
    
    if (removedCount > 0) {
        showNotification(
            "Wall Portion Removed",
            `Removed portion of ${removedCount} wall segment${removedCount !== 1 ? 's' : ''}`,
            "success"
        );
    } else {
        showNotification(
            "No Wall Selected",
            "No wall portions found in the selected area",
            "warning"
        );
    }
}

function lineIntersectsSelection(segment, selStart, selEnd) {
    // Create a rectangular selection area
    const minX = Math.min(selStart.x, selEnd.x);
    const maxX = Math.max(selStart.x, selEnd.x);
    const minY = Math.min(selStart.y, selEnd.y);
    const maxY = Math.max(selStart.y, selEnd.y);
    
    // Check if line segment intersects with the selection rectangle
    return lineIntersectsRect(segment.start, segment.end, minX, minY, maxX, maxY);
}

function lineIntersectsRect(lineStart, lineEnd, rectX1, rectY1, rectX2, rectY2) {
    // Check if either endpoint is inside the rectangle
    if (pointInRect(lineStart, rectX1, rectY1, rectX2, rectY2) ||
        pointInRect(lineEnd, rectX1, rectY1, rectX2, rectY2)) {
        return true;
    }
    
    // Check if line intersects any of the rectangle edges
    const rectEdges = [
        { start: { x: rectX1, y: rectY1 }, end: { x: rectX2, y: rectY1 } }, // top
        { start: { x: rectX2, y: rectY1 }, end: { x: rectX2, y: rectY2 } }, // right
        { start: { x: rectX2, y: rectY2 }, end: { x: rectX1, y: rectY2 } }, // bottom
        { start: { x: rectX1, y: rectY2 }, end: { x: rectX1, y: rectY1 } }  // left
    ];
    
    return rectEdges.some(edge => 
        linesIntersect(lineStart, lineEnd, edge.start, edge.end)
    );
}

function pointInRect(point, x1, y1, x2, y2) {
    return point.x >= Math.min(x1, x2) && point.x <= Math.max(x1, x2) &&
           point.y >= Math.min(y1, y2) && point.y <= Math.max(y1, y2);
}

function linesIntersect(p1, p2, p3, p4) {
    const denom = (p1.x - p2.x) * (p3.y - p4.y) - (p1.y - p2.y) * (p3.x - p4.x);
    if (Math.abs(denom) < 0.0001) return false; // parallel lines
    
    const t = ((p1.x - p3.x) * (p3.y - p4.y) - (p1.y - p3.y) * (p3.x - p4.x)) / denom;
    const u = -((p1.x - p2.x) * (p1.y - p3.y) - (p1.y - p2.y) * (p1.x - p3.x)) / denom;
    
    return t >= 0 && t <= 1 && u >= 0 && u <= 1;
}

function splitSegmentBySelection(segment, selStart, selEnd) {
    // Create rectangular selection area
    const minX = Math.min(selStart.x, selEnd.x);
    const maxX = Math.max(selStart.x, selEnd.x);
    const minY = Math.min(selStart.y, selEnd.y);
    const maxY = Math.max(selStart.y, selEnd.y);
    
    // Find intersection points with selection rectangle
    const intersections = [];
    const rectEdges = [
        { start: { x: minX, y: minY }, end: { x: maxX, y: minY } }, // top
        { start: { x: maxX, y: minY }, end: { x: maxX, y: maxY } }, // right
        { start: { x: maxX, y: maxY }, end: { x: minX, y: maxY } }, // bottom
        { start: { x: minX, y: maxY }, end: { x: minX, y: minY } }  // left
    ];
    
    rectEdges.forEach(edge => {
        const intersection = getLineIntersection(segment.start, segment.end, edge.start, edge.end);
        if (intersection) {
            intersections.push(intersection);
        }
    });
    
    // Remove duplicates and sort by distance from segment start
    const uniqueIntersections = intersections.filter((point, index, arr) => {
        return index === arr.findIndex(p => 
            Math.abs(p.x - point.x) < 0.1 && Math.abs(p.y - point.y) < 0.1
        );
    }).sort((a, b) => {
        const distA = Math.sqrt(Math.pow(a.x - segment.start.x, 2) + Math.pow(a.y - segment.start.y, 2));
        const distB = Math.sqrt(Math.pow(b.x - segment.start.x, 2) + Math.pow(b.y - segment.start.y, 2));
        return distA - distB;
    });
    
    if (uniqueIntersections.length === 0) {
        // No intersections, check if entire segment is inside selection
        if (pointInRect(segment.start, minX, minY, maxX, maxY) && 
            pointInRect(segment.end, minX, minY, maxX, maxY)) {
            return []; // Remove entire segment
        }
        return [segment]; // Keep entire segment
    }
    
    // Create segments from the parts outside the selection
    const parts = [];
    
    // Part before first intersection
    if (uniqueIntersections.length > 0) {
        const distToFirst = Math.sqrt(
            Math.pow(uniqueIntersections[0].x - segment.start.x, 2) + 
            Math.pow(uniqueIntersections[0].y - segment.start.y, 2)
        );
        if (distToFirst > 1) { // Only keep if significant length
            parts.push({
                start: { x: segment.start.x, y: segment.start.y },
                end: { x: uniqueIntersections[0].x, y: uniqueIntersections[0].y }
            });
        }
    }
    
    // Part after last intersection
    if (uniqueIntersections.length > 0) {
        const lastIndex = uniqueIntersections.length - 1;
        const distToLast = Math.sqrt(
            Math.pow(segment.end.x - uniqueIntersections[lastIndex].x, 2) + 
            Math.pow(segment.end.y - uniqueIntersections[lastIndex].y, 2)
        );
        if (distToLast > 1) { // Only keep if significant length
            parts.push({
                start: { x: uniqueIntersections[lastIndex].x, y: uniqueIntersections[lastIndex].y },
                end: { x: segment.end.x, y: segment.end.y }
            });
        }
    }
    
    return parts;
}

function getLineIntersection(p1, p2, p3, p4) {
    const denom = (p1.x - p2.x) * (p3.y - p4.y) - (p1.y - p2.y) * (p3.x - p4.x);
    if (Math.abs(denom) < 0.0001) return null; // parallel lines
    
    const t = ((p1.x - p3.x) * (p3.y - p4.y) - (p1.y - p3.y) * (p3.x - p4.x)) / denom;
    const u = -((p1.x - p2.x) * (p1.y - p3.y) - (p1.y - p2.y) * (p1.x - p3.x)) / denom;
    
    if (t >= 0 && t <= 1 && u >= 0 && u <= 1) {
        return {
            x: p1.x + t * (p2.x - p1.x),
            y: p1.y + t * (p2.y - p1.y)
        };
    }
    
    return null;
}

function toggleWaypointMode() {
    isAddingWaypoints = !isAddingWaypoints;
    const btn = document.getElementById("addWpBtn");

    if (isAddingWaypoints) {
        // Exit editing mode if active
        if (isEditingMode) {
            toggleEditingMode();
        }
        
        btn.textContent = "✋ Stop Adding";
        btn.className = "py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border-2 border-blue-500 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-blue-500 dark:hover:text-white dark:hover:bg-gray-700";
        canvas.style.cursor = "crosshair";
        const logMessage = props.isUserMode 
            ? "Selection mode activated - click on waypoints to select them"
            : "Waypoint mode activated - click on the canvas to add waypoints";
        console.log(logMessage);
        
        const notificationText = props.isUserMode
            ? "Click on waypoints to select them. Press ESC to exit."
            : "Click on the canvas to add waypoints. Press ESC to exit.";
        showNotification(
            props.isUserMode ? "Selection Mode" : "Waypoint Mode",
            notificationText,
            "info"
        );
    } else {
        // Change text based on user mode
        const buttonText = props.isUserMode ? "📍 Select Waypoints" : "📍 Add Waypoint";
        btn.textContent = buttonText;
        btn.className = "py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border not-dark:border-gray-200 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700";
        canvas.style.cursor = "grab";
        console.log("Waypoint mode deactivated");
        const notificationText = props.isUserMode ? "Selection mode disabled" : "Waypoint adding mode disabled";
        showNotification(props.isUserMode ? "Selection Mode" : "Waypoint Mode", notificationText, "info");
    }

    updateViewportInfo();
}

function addWaypoint(e) {
    if (!isAddingWaypoints) return;

    const rect = canvas.getBoundingClientRect();
    const canvasX = e.clientX - rect.left;
    const canvasY = e.clientY - rect.top;

    // Debug: log canvas dimensions and mouse position
    console.log("Canvas rect:", rect);
    console.log("Canvas internal size:", canvas.width, "x", canvas.height);
    console.log("Mouse position:", e.clientX, e.clientY);
    console.log("Canvas relative position:", canvasX, canvasY);

    // Account for canvas scaling if the displayed size differs from internal size
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    const scaledCanvasX = canvasX * scaleX;
    const scaledCanvasY = canvasY * scaleY;

    console.log("Scale factors:", scaleX, scaleY);
    console.log("Scaled canvas position:", scaledCanvasX, scaledCanvasY);

    // Convert canvas coordinates to world coordinates
    const worldX = (scaledCanvasX - viewport.x) / viewport.zoom;
    const worldY = (scaledCanvasY - viewport.y) / viewport.zoom;

    // Snap to grid (round to nearest integer)
    const snappedX = Math.round(worldX);
    const snappedY = Math.round(worldY);

    // Create waypoint object with unique ID and default name
    const waypoint = {
        id: waypointCounter++,
        name: `Waypoint ${waypointCounter - 1}`,
        x: snappedX,
        y: snappedY,
        selected: false
    };
    
    waypoints.push(waypoint);
    console.log(
        `Added waypoint ${waypoint.id} "${waypoint.name}" at (${snappedX}, ${snappedY}) [snapped from (${worldX.toFixed(
            2
        )}, ${worldY.toFixed(2)})]`
    );
    drawGrid();
    showNotification(
        "Waypoint Added",
        `Waypoint ${waypoints.length} placed at (${snappedX}, ${snappedY})`,
        "success"
    );
}

// Viewport functions
function resetViewport() {
    if (!bounds) return;

    // Expand bounds by 5% on each side for better waypoint placement
    const expandedWidth = bounds.width * 1.1; // 10% total (5% on each side)
    const expandedHeight = bounds.height * 1.1; // 10% total (5% on each side)
    
    const scaleX = canvas.width / expandedWidth;
    const scaleY = canvas.height / expandedHeight;
    const scale = Math.min(scaleX, scaleY);

    viewport.zoom = scale;
    // Center the expanded area, which centers the original bounds with 5% margin
    viewport.x = (canvas.width - expandedWidth * scale) / 2;
    viewport.y = (canvas.height - expandedHeight * scale) / 2;

    console.log("Reset viewport:", viewport);
}

function worldToCanvas(worldX, worldY) {
    return {
        x: worldX * viewport.zoom + viewport.x,
        y: worldY * viewport.zoom + viewport.y,
    };
}

function canvasToWorld(canvasX, canvasY) {
    return {
        x: (canvasX - viewport.x) / viewport.zoom,
        y: (canvasY - viewport.y) / viewport.zoom,
    };
}

function updateViewportInfo() {
    document.getElementById("zoomLevel").textContent = viewport.zoom.toFixed(2);
    document.getElementById("waypointCount")!.textContent = waypoints.length.toString();

    // Update distance display
    const distanceElement = document.getElementById("pathDistance");
    if (pathDistanceInfo && computedPath.length > 0) {
        const distanceCm = pathDistanceInfo.pathLengthCm.toFixed(1);
        const distanceM = (pathDistanceInfo.pathLengthCm / 100).toFixed(2);
        distanceElement.textContent = `${distanceCm} cm (${distanceM} m)`;
        distanceElement.style.color = "#4CAF50";
        distanceElement.style.fontWeight = "bold";
    } else {
        distanceElement.textContent = "-";
        distanceElement.style.color = "#666";
        distanceElement.style.fontWeight = "normal";
    }

    const modeIndicator = document.getElementById("modeIndicator");
    if (isAddingWaypoints) {
        const modeText = props.isUserMode 
            ? "SELECTION MODE: Click on waypoints to select them, ESC to exit, Middle-click to pan"
            : "WAYPOINT MODE: Click to add waypoints, ESC to exit, Middle-click to pan";
        modeIndicator.textContent = modeText;
        modeIndicator.style.color = "#f44336";
        modeIndicator.style.fontWeight = "bold";
    } else if (isEditingMode) {
        let modeText = "EDITING MODE: ";
        if (editingTool === 'add') {
            modeText += "Click and drag to add walls";
        } else if (editingTool === 'remove') {
            modeText += "Click on walls to remove them";
        } else if (editingTool === 'cut') {
            modeText += "Click and drag to select wall portion to remove";
        } else {
            modeText += "Select a tool to start editing";
        }
        modeText += ", Middle-click to pan";
        
        modeIndicator.textContent = modeText;
        modeIndicator.style.color = "#ff9800";
        modeIndicator.style.fontWeight = "bold";
    } else {
        modeIndicator.textContent =
            "Left-click to pan, Middle-click to pan, Mouse wheel to zoom";
        modeIndicator.style.color = "#666";
        modeIndicator.style.fontWeight = "normal";
    }
}

function drawGrid() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    if (useOnDemandRendering) {
        drawLinesOnDemand();
    } else if (grid && grid.length > 0) {
        drawGridArray();
    }
}

function drawLinesOnDemand() {
    if (!lineSegments || !bounds) return;

    // Set background based on theme
    const darkMode = isDarkMode();
    ctx.fillStyle = darkMode ? "#1f2937" : "#e5e7eb"; // bg-gray-800 : bg-gray-200
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Calculate visible area in world coordinates
    const topLeft = canvasToWorld(0, 0);
    const bottomRight = canvasToWorld(canvas.width, canvas.height);

    // Draw line segments with theme-aware colors
    ctx.strokeStyle = darkMode ? "#e5e7eb" : "#1f2937"; // bg-gray-200 : bg-gray-800
    // Use fixed line width that doesn't scale with zoom, but has a minimum/maximum
    const baseLineWidth = 1;
    const minLineWidth = 0.5;
    const maxLineWidth = 2;
    ctx.lineWidth = Math.max(
        minLineWidth,
        Math.min(maxLineWidth, baseLineWidth / viewport.zoom)
    );
    ctx.beginPath();

    let drawnLines = 0;
    lineSegments.forEach((segment) => {
        const x1 = segment.start.x - bounds.minX;
        const y1 = segment.start.y - bounds.minY;
        const x2 = segment.end.x - bounds.minX;
        const y2 = segment.end.y - bounds.minY;

        // Simple culling - only draw if line might be visible
        if (
            (x1 >= topLeft.x - 10 && x1 <= bottomRight.x + 10) ||
            (x2 >= topLeft.x - 10 && x2 <= bottomRight.x + 10) ||
            (y1 >= topLeft.y - 10 && y1 <= bottomRight.y + 10) ||
            (y2 >= topLeft.y - 10 && y2 <= bottomRight.y + 10)
        ) {
            const canvas1 = worldToCanvas(x1, y1);
            const canvas2 = worldToCanvas(x2, y2);

            ctx.moveTo(canvas1.x, canvas1.y);
            ctx.lineTo(canvas2.x, canvas2.y);
            drawnLines++;
        }
    });

    ctx.stroke();
    console.log(`Drew ${drawnLines}/${lineSegments.length} line segments`);

    drawWaypointsAndPath();
}

function drawGridArray() {
    if (!grid || grid.length === 0) return;

    // Set background based on theme
    const darkMode = isDarkMode();
    ctx.fillStyle = darkMode ? "#1f2937" : "#e5e7eb"; // bg-gray-800 : bg-gray-200
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Calculate which part of the grid is visible
    const topLeft = canvasToWorld(0, 0);
    const bottomRight = canvasToWorld(canvas.width, canvas.height);

    const startX = Math.max(0, Math.floor(topLeft.x));
    const endX = Math.min(grid[0].length, Math.ceil(bottomRight.x));
    const startY = Math.max(0, Math.floor(topLeft.y));
    const endY = Math.min(grid.length, Math.ceil(bottomRight.y));

    // Only draw visible cells with theme-aware colors
    for (let y = startY; y < endY; y++) {
        for (let x = startX; x < endX; x++) {
            if (grid[y][x] === 1) {
                const canvasPos = worldToCanvas(x, y);
                ctx.fillStyle = darkMode ? "#e5e7eb" : "#1f2937"; // bg-gray-200 : bg-gray-800
                ctx.fillRect(canvasPos.x, canvasPos.y, viewport.zoom, viewport.zoom);
            }
        }
    }

    drawWaypointsAndPath();
}

function drawWaypointsAndPath() {
    // Draw waypoints
    const darkMode = isDarkMode();
    waypoints.forEach((wp, i) => {
        const canvasPos = worldToCanvas(wp.x, wp.y);
        
        // Waypoint size that scales reasonably with zoom
        const baseSize = 8;
        const minSize = 6;
        const maxSize = 16;
        const size = Math.max(minSize, Math.min(maxSize, baseSize / Math.sqrt(viewport.zoom * 0.5)));
        
        // Draw waypoint circle with selection state
        ctx.beginPath();
        ctx.arc(canvasPos.x, canvasPos.y, size / 2, 0, 2 * Math.PI);
        
        // Fill color based on selection state
        if (wp.selected || selectedWaypoints.has(wp.id)) {
            ctx.fillStyle = "#10b981"; // green-500 for selected
        } else {
            ctx.fillStyle = "#3b82f6"; // blue-500 for normal
        }
        ctx.fill();
        
        // Add border
        ctx.strokeStyle = darkMode ? "#ffffff" : "#000000";
        ctx.lineWidth = 2;
        ctx.stroke();
        
        // Draw waypoint name below the circle
        ctx.fillStyle = darkMode ? "#f9fafb" : "#111827";
        const fontSize = Math.max(8, Math.min(12, 10 / Math.sqrt(viewport.zoom * 0.5)));
        ctx.font = `${fontSize}px Arial`;
        ctx.textAlign = "center";
        
        // Draw name with background for better visibility
        const textMetrics = ctx.measureText(wp.name);
        const textWidth = textMetrics.width;
        const textHeight = fontSize;
        const textX = canvasPos.x;
        const textY = canvasPos.y + size / 2 + textHeight + 4;
        
        // Draw text background
        ctx.fillStyle = darkMode ? "rgba(0, 0, 0, 0.7)" : "rgba(255, 255, 255, 0.8)";
        ctx.fillRect(textX - textWidth / 2 - 2, textY - textHeight, textWidth + 4, textHeight + 2);
        
        // Draw text
        ctx.fillStyle = darkMode ? "#f9fafb" : "#111827";
        ctx.fillText(wp.name, textX, textY);
        
        // Reset text alignment
        ctx.textAlign = "start";
    });

    // Draw path
    if (computedPath.length > 0) {
        drawPath(computedPath);
    }
    
    // Draw temporary wall being drawn
    if (isDrawingWall && currentWallStart && tempWallEnd) {
        const startPos = worldToCanvas(currentWallStart.x, currentWallStart.y);
        const endPos = worldToCanvas(tempWallEnd.x, tempWallEnd.y);
        
        ctx.strokeStyle = "orange";
        ctx.lineWidth = 3;
        ctx.setLineDash([5, 5]); // Dashed line for temporary wall
        ctx.beginPath();
        ctx.moveTo(startPos.x, startPos.y);
        ctx.lineTo(endPos.x, endPos.y);
        ctx.stroke();
        ctx.setLineDash([]); // Reset line dash
    }
    
    // Draw selection rectangle for wall portion removal
    if (isSelectingWallPortion && selectionStart && selectionEnd) {
        const startPos = worldToCanvas(selectionStart.x, selectionStart.y);
        const endPos = worldToCanvas(selectionEnd.x, selectionEnd.y);
        
        const x = Math.min(startPos.x, endPos.x);
        const y = Math.min(startPos.y, endPos.y);
        const width = Math.abs(endPos.x - startPos.x);
        const height = Math.abs(endPos.y - startPos.y);
        
        // Draw selection rectangle with semi-transparent fill
        ctx.fillStyle = "rgba(255, 0, 0, 0.2)";
        ctx.fillRect(x, y, width, height);
        
        // Draw selection rectangle border
        ctx.strokeStyle = "red";
        ctx.lineWidth = 2;
        ctx.setLineDash([3, 3]); // Dashed line for selection
        ctx.strokeRect(x, y, width, height);
        ctx.setLineDash([]); // Reset line dash
    }
}
function drawPath(path) {
    if (path.length === 0) return;

    ctx.strokeStyle = "red";
    // Path should be more visible but not too thick when zoomed out
    const basePathWidth = 3;
    const minPathWidth = 1;
    const maxPathWidth = 4;
    ctx.lineWidth = Math.max(
        minPathWidth,
        Math.min(maxPathWidth, basePathWidth / Math.sqrt(viewport.zoom))
    );

    ctx.beginPath();
    const firstPos = worldToCanvas(path[0][0], path[0][1]);
    ctx.moveTo(firstPos.x, firstPos.y);

    for (let i = 1; i < path.length; i++) {
        const pos = worldToCanvas(path[i][0], path[i][1]);
        ctx.lineTo(pos.x, pos.y);
    }
    ctx.stroke();
}

</script>

<template>
    <div class="pathfinder-container grid grid-cols-4 gap-1 p-1">
        <!-- Canvas -->
        <div class="col-span-3 not-dark:bg-gray-200 dark:bg-gray-800 rounded overflow-hidden relative">
            <!-- Overlay Info Panel -->
            <div
                class="absolute top-2 left-2 z-10 not-dark:bg-white/80 dark:bg-gray-900/80 rounded-lg shadow px-4 py-2 flex flex-col gap-1 pointer-events-none">
                <div class="flex gap-2 items-center">
                    <span>Zoom:</span><span id="zoomLevel">1.00</span>
                </div>
                <div class="flex gap-2 items-center">
                    <span>Waypoint Count:</span><span id="waypointCount">0</span>
                </div>
                <div class="flex gap-2 items-center">
                    <span>Path Distance:</span><span id="pathDistance">-</span>
                </div>
            </div>
            <canvas id="gridCanvas" width="1200" height="800"
                class="block w-full h-auto max-h-[70vh] object-contain"></canvas>
            <span id="modeIndicator" class="block mt-2 px-2 py-1 text-sm">
                Left-click to pan, Middle-click to pan, Mouse wheel to zoom
            </span>
        </div>

        <!-- Waypoint Context Menu -->
        <div v-if="contextMenu.visible" 
             :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
             class="fixed z-50 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg py-2 min-w-[120px]"
             @click.stop>
            <button @click="renameWaypoint" 
                    class="w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 text-sm">
                Rename
            </button>
            <button @click="deleteWaypoint" 
                    class="w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 text-sm text-red-600 dark:text-red-400">
                Delete
            </button>
        </div>

        <!-- Controls Section -->
        <div class="col-span-1 gap-1 p-1 not-dark:bg-gray-200 dark:bg-gray-800 rounded">
            <div class="p-2">
                <!-- File Upload -->
                <div v-if="!isUserMode" class="mb-5">
                    <label for="dxfFile" class="block mb-2 text-sm font-medium not-dark:text-gray-900 dark:text-white">
                        Upload DXF File
                    </label>
                    <input type="file" id="dxfFile" accept=".dxf"
                        class="block w-full text-sm not-dark:text-gray-900 border not-dark:border-gray-300 rounded-lg cursor-pointer not-dark:bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" />
                    <p class="mt-1 text-sm not-dark:text-gray-500 dark:text-gray-300" id="file_input_help">DXF files are
                        supported.</p>
                </div>

                <!-- Action Buttons -->
                <div class="grid grid-cols-2 mb-5">
                    <p class="block text-sm font-medium not-dark:text-gray-900 dark:text-white col-span-2">Waypoint Control</p>
                    <hr class="mb-2 not-dark:border-gray-300 dark:border-gray-600 col-span-2" />
                    
                    
                    <button id="addWpBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border not-dark:border-gray-200 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        {{ isUserMode ? '📍 Select Waypoints' : '📍 Add Waypoint' }}
                    </button>

                    <button id="clearWpBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border not-dark:border-gray-200 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        �️ Clear Waypoints
                    </button>
                </div>


                <div class="grid grid-cols-1 mb-5">
                    <p class="block text-sm font-medium not-dark:text-gray-900 dark:text-white">Pathfinding & view control</p>
                    <hr class="mb-2 not-dark:border-gray-300 dark:border-gray-600" />
                    
                    
                    <button id="computePathBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border not-dark:border-gray-200 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        � Compute Path
                    </button>

                    <button id="resetViewBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border not-dark:border-gray-200 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        🔄 Reset View
                    </button>
                </div>

                <div v-if="!isUserMode" class="grid grid-cols-2 mb-5">
                    <p class="block text-sm font-medium not-dark:text-gray-900 dark:text-white col-span-2">Editor</p>
                    <hr class="mb-2 not-dark:border-gray-300 dark:border-gray-600 col-span-2" />

                    <!-- Wall Editing Controls -->
                    <button id="editModeBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border not-dark:border-gray-200 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        ✏️ Edit Walls
                    </button>

                    <button id="cutWallBtn" type="button" @click="setEditingTool('cut')"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border not-dark:border-gray-200 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        ✂️ Select & Remove
                    </button>

                    <button id="addWallBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border not-dark:border-gray-200 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        ➕ Add Wall
                    </button>

                    <button id="removeWallBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium not-dark:text-gray-900 focus:outline-none not-dark:bg-white rounded-lg border not-dark:border-gray-200 not-dark:hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 not-dark:focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        ➖ Remove Wall
                    </button>
                </div>

                <div class="grid grid-cols-1 mb-5">
                    <p class="block text-sm font-medium not-dark:text-gray-900 dark:text-white">Pathfinding Settings</p>
                    <hr class="mb-2 not-dark:border-gray-300 dark:border-gray-600" />
                    <!-- Wall Distance Control -->
                    <div>
                        <label for="wallBufferSlider"
                            class="block text-sm font-medium not-dark:text-gray-900 dark:text-white">
                            Wall Distance: <span id="wallBufferValue">2</span>
                        </label>
                        <input id="wallBufferSlider" type="range" min="0" max="5" value="2"
                            class="w-full h-2 not-dark:bg-gray-800 rounded-lg appearance-none cursor-pointer dark:bg-gray-700" />
                    </div>

                    <!-- Path Smoothing -->
                    <div>
                        <input id="pathSmoothingCheckbox" type="checkbox" checked
                            class="w-4 h-4 text-blue-600 not-dark:bg-gray-100 not-dark:border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" />
                        <label for="pathSmoothingCheckbox"
                            class="ms-2 text-sm font-medium not-dark:text-gray-900 dark:text-gray-300">
                            Enable Path Smoothing
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <div class="canvas-container">
            <!-- Loading Modal using Flowbite -->
            <div id="loadingModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
                <div class="relative p-4 w-full max-w-md max-h-full">
                    <!-- Modal content -->
                    <div class="relative not-dark:bg-white rounded-lg shadow dark:bg-gray-700">
                        <!-- Modal body -->
                        <div class="p-6 text-center">
                            <!-- Loading spinner -->
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 not-dark:border-gray-900 dark:border-white mb-4"></div>
                            <p id="loadingText" class="text-lg font-normal not-dark:text-gray-500 dark:text-gray-400">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Flowbite Toast Container -->
        <div id="toast-container" class="fixed top-5 right-5 z-50 space-y-4"></div>
    </div>
</template>