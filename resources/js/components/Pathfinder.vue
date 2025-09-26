<script setup>
import { onMounted } from "vue";
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

let canvas;
let ctx;

let grid = [];
let lineSegments = [];
let bounds = null;
let useOnDemandRendering = false;
let waypoints = [];
let finder = createFinder();

// For pathfinding when using on-demand rendering
let pathfindingGrid = null;
let pathfindingScaleFactor = 1;
let wallBuffer = 2; // Default wall buffer size

// Store computed path and distance info
let computedPath = [];
let pathDistanceInfo = null;

// Viewport state
let viewport = {
    x: 0,
    y: 0,
    zoom: 1,
    minZoom: 0.05,
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

// Button state management
let buttonStates = new Map(); // Store original button classes

// Global selected button styling
const SELECTED_BUTTON_CLASSES = "ring-2 ring-blue-500 ring-offset-2 ring-offset-white dark:ring-offset-gray-800 shadow-lg transform scale-105";

// Button management functions
function storeButtonState(button, isSelected = false) {
    if (!button) return;

    const buttonId = button.id;
    if (!buttonStates.has(buttonId)) {
        // Store original classes
        buttonStates.set(buttonId, {
            originalClasses: button.className,
            isSelected: false
        });
    }

    // Update selection state
    const state = buttonStates.get(buttonId);
    state.isSelected = isSelected;

    if (isSelected) {
        // Apply selected styling
        button.className = state.originalClasses + " " + SELECTED_BUTTON_CLASSES;
    } else {
        // Restore original styling
        button.className = state.originalClasses;
    }
}

function toggleButtonSelection(buttonId, isSelected) {
    const button = document.getElementById(buttonId);
    storeButtonState(button, isSelected);
}

function resetAllButtonSelections() {
    buttonStates.forEach((state, buttonId) => {
        if (state.isSelected) {
            toggleButtonSelection(buttonId, false);
        }
    });
}

// Canvas setup
function setupCanvas(canvas, ctx) {
    // Use fixed canvas dimensions to maintain consistent size
    const fixedWidth = 1200;
    const fixedHeight = 800;
    const dpr = window.devicePixelRatio || 1;

    // Set internal canvas resolution
    canvas.width = fixedWidth * dpr;
    canvas.height = fixedHeight * dpr;

    // Scale context for high DPI displays
    ctx.scale(dpr, dpr);

    // Set CSS size to match the fixed dimensions
    canvas.style.width = fixedWidth + 'px';
    canvas.style.height = fixedHeight + 'px';
}

// Flowbite notification system
function showNotification(title, message, type = "info") {
    let bgColor, borderColor, textColor, icon;

    switch (type) {
        case 'success':
            bgColor = 'bg-green-100 dark:bg-green-800';
            borderColor = 'border-green-500 dark:border-green-700';
            textColor = 'text-green-900 dark:text-green-300';
            icon = `<svg class="flex-shrink-0 w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                    </svg>`;
            break;
        case 'error':
            bgColor = 'bg-red-100 dark:bg-red-800';
            borderColor = 'border-red-500 dark:border-red-700';
            textColor = 'text-red-900 dark:text-red-300';
            icon = `<svg class="flex-shrink-0 w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                    </svg>`;
            break;
        case 'warning':
            bgColor = 'bg-yellow-100 dark:bg-yellow-800';
            borderColor = 'border-yellow-500 dark:border-yellow-700';
            textColor = 'text-yellow-900 dark:text-yellow-300';
            icon = `<svg class="flex-shrink-0 w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                    </svg>`;
            break;
        default:
            bgColor = 'bg-blue-100 dark:bg-blue-800';
            borderColor = 'border-blue-500 dark:border-blue-700';
            textColor = 'text-blue-900 dark:text-blue-300';
            icon = `<svg class="flex-shrink-0 w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                    </svg>`;
    }

    const notificationId = `toast-${Date.now()}`;
    const notification = document.createElement("div");
    notification.id = notificationId;
    notification.className = `fixed top-5 right-5 z-50 flex items-center w-full max-w-xs p-4 mb-4 ${textColor} ${bgColor} rounded-lg shadow-lg`;
    notification.innerHTML = `
        <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 ${textColor} ${bgColor} rounded-lg">
            ${icon}
            <span class="sr-only">${type} icon</span>
        </div>
        <div class="ms-3 text-sm font-normal">
            <div class="font-semibold">${title}</div>
            <div class="text-xs mt-1">${message}</div>
        </div>
        <button type="button" class="ms-auto -mx-1.5 -my-1.5 ${bgColor} ${textColor} hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex items-center justify-center h-8 w-8" onclick="document.getElementById('${notificationId}').remove()">
            <span class="sr-only">Close</span>
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
            </svg>
        </button>
    `;

    document.body.appendChild(notification);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (document.getElementById(notificationId)) {
            document.getElementById(notificationId).remove();
        }
    }, 5000);
}

// Flowbite loading modal system
function showLoadingModal(text = "Loading...") {
    const modal = document.getElementById("loadingModal");
    const overlay = document.getElementById("modalBackdrop");
    const loadingText = document.getElementById("loadingText");

    if (loadingText) loadingText.textContent = text;
    if (overlay) overlay.classList.remove("hidden");
    if (modal) {
        modal.classList.remove("hidden");
        modal.classList.add("flex");
    }
}

function hideLoadingModal() {
    const modal = document.getElementById("loadingModal");
    const overlay = document.getElementById("modalBackdrop");

    if (modal) {
        modal.classList.add("hidden");
        modal.classList.remove("flex");
    }
    if (overlay) overlay.classList.add("hidden");
}

onMounted(() => {
    canvas = document.getElementById("gridCanvas");
    ctx = canvas.getContext("2d");

    setupCanvas(canvas, ctx);

    // Initialize button states - store original classes for all interactive buttons
    const interactiveButtons = [
        "addWpBtn", "clearWpBtn", "computePathBtn", "resetViewBtn",
        "editModeBtn", "addWallBtn", "removeWallBtn", "cutWallBtn"
    ];

    interactiveButtons.forEach(buttonId => {
        const button = document.getElementById(buttonId);
        if (button) {
            storeButtonState(button, false);
        }
    });

    document.getElementById("dxfFile").addEventListener("change", async (e) => {
        const file = e.target.files[0];

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

    document.getElementById("addWpBtn").addEventListener("click", () => {
        toggleWaypointMode();
    });

    document.getElementById("clearWpBtn").addEventListener("click", () => {
        const waypointCount = waypoints.length;
        waypoints = [];
        computedPath = [];
        pathDistanceInfo = null;
        drawGrid();
        showNotification(
            "Waypoints Cleared",
            `Removed ${waypointCount} waypoint${waypointCount !== 1 ? "s" : ""}`,
            "info"
        );
    });

    // Wall buffer control
    document.getElementById("wallBufferSlider").addEventListener("input", (e) => {
        wallBuffer = parseInt(e.target.value);
        document.getElementById("wallBufferValue").textContent = wallBuffer;

        // Regenerate the pathfinding grid with the new wall buffer
        regeneratePathfindingGrid();

        // Clear existing path since pathfinding grid has changed
        computedPath = [];
        pathDistanceInfo = null;

        // Redraw to update display
        drawGrid();

        showNotification(
            "Wall Distance Updated",
            `Wall buffer set to ${wallBuffer} cells`,
            "info"
        );
    });

    // Wall editing controls
    document.getElementById("editModeBtn").addEventListener("click", () => {
        toggleEditingMode();
    });

    document.getElementById("addWallBtn").addEventListener("click", () => {
        setEditingTool('add');
    });

    document.getElementById("removeWallBtn").addEventListener("click", () => {
        setEditingTool('remove');
    });

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
        if (isAddingWaypoints && e.button === 0) {
            // Only left click for waypoints
            addWaypoint(e);
        }
    });

    // Prevent middle mouse button default behavior
    canvas.addEventListener("contextmenu", (e) => {
        e.preventDefault(); // Prevent right-click context menu
    });

    canvas.addEventListener("auxclick", (e) => {
        if (e.button === 1) {
            // Middle mouse button
            e.preventDefault(); // Prevent middle-click default behavior
        }
    });

    // Keyboard event handlers
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            if (isAddingWaypoints) {
                toggleWaypointMode();
            }
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
        let newZoom = viewport.zoom * zoomFactor;

        // Clamp zoom to min/max
        newZoom = Math.max(viewport.minZoom, Math.min(viewport.maxZoom, newZoom));

        if (newZoom !== viewport.zoom) {
            // Zoom towards mouse position
            viewport.x = scaledMouseX - (scaledMouseX - viewport.x) * (newZoom / viewport.zoom);
            viewport.y = scaledMouseY - (scaledMouseY - viewport.y) * (newZoom / viewport.zoom);
            viewport.zoom = newZoom;

            drawGrid();
        }
    });

    document.getElementById("computePathBtn").addEventListener("click", () => {
        if (waypoints.length < 2) {
            showNotification(
                "Insufficient Waypoints",
                "At least 2 waypoints are required to compute a path",
                "error"
            );
            return;
        }

        // Use the correct grid for pathfinding
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
            let scaledWaypoints = waypoints;
            if (useOnDemandRendering && pathfindingScaleFactor !== 1) {
                scaledWaypoints = waypoints.map((wp) => [
                    Math.round(wp[0] * pathfindingScaleFactor),
                    Math.round(wp[1] * pathfindingScaleFactor),
                ]);
                console.log("Original waypoints:", waypoints);
                console.log("Scaled waypoints for pathfinding:", scaledWaypoints);
                console.log("Pathfinding scale factor:", pathfindingScaleFactor);
            } else {
                // For normal rendering, convert world coordinates to grid coordinates
                scaledWaypoints = waypoints.map((wp) => [
                    Math.round(wp[0] - bounds.minX) + 2,
                    Math.round(wp[1] - bounds.minY) + 2,
                ]);
                console.log("Original waypoints:", waypoints);
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
                    `TSP solved in ${tspTime.toFixed(2)}ms for ${scaledWaypoints.length
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
                const isPathSmoothingEnabled = document.getElementById(
                    "pathSmoothingCheckbox"
                ).checked;

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

                // Store the computed path and distance info
                computedPath = fullPath;
                pathDistanceInfo = distanceInfo;

                hideLoadingModal();

                // Show detailed success notification with distance information
                const pathLengthCm = distanceInfo.pathLengthCm.toFixed(1);
                const pathLengthM = (distanceInfo.pathLengthCm / 100).toFixed(2);

                showNotification(
                    "Optimal Path Computed!",
                    `Path: ${fullPath.length
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

    // Zoom and viewport controls
    document.getElementById("resetViewBtn").addEventListener("click", () => {
        resetViewport();
        drawGrid();
        showNotification(
            "View Reset",
            "Viewport has been reset to show entire drawing",
            "info"
        );
    });

    // Set initial canvas cursor and viewport info
    canvas.style.cursor = "grab";

    // Update viewport info whenever drawing happens
    const originalDrawGrid = drawGrid;
    drawGrid = function (...args) {
        originalDrawGrid.apply(this, args);
        updateViewportInfo();
    };
});

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
    // Update editing mode button text content only
    const editBtn = document.getElementById("editModeBtn");
    if (editBtn) {
        if (isEditingMode) {
            editBtn.textContent = "✋ Stop Editing";
        } else {
            editBtn.textContent = "✏️ Edit Walls";
        }
        // Apply selection state using the new system
        toggleButtonSelection("editModeBtn", isEditingMode);
    }

    // Update tool buttons - reset all first, then set the active one
    const toolButtons = ["addWallBtn", "removeWallBtn", "cutWallBtn"];

    toolButtons.forEach(buttonId => {
        toggleButtonSelection(buttonId, false);
    });

    // Set the active tool button as selected
    if (editingTool === 'add') {
        toggleButtonSelection("addWallBtn", true);
    } else if (editingTool === 'remove') {
        toggleButtonSelection("removeWallBtn", true);
    } else if (editingTool === 'cut') {
        toggleButtonSelection("cutWallBtn", true);
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
        canvas.style.cursor = "crosshair";
        showNotification(
            "Waypoint Mode",
            "Click on the canvas to add waypoints. Press ESC to exit.",
            "info"
        );
    } else {
        btn.textContent = "📍 Add Waypoint";
        canvas.style.cursor = "grab";
        showNotification("Waypoint Mode", "Waypoint adding mode disabled", "info");
    }

    // Apply selection state using the new system
    toggleButtonSelection("addWpBtn", isAddingWaypoints);
    updateViewportInfo();
}

function addWaypoint(e) {
    if (!isAddingWaypoints) return;

    const rect = canvas.getBoundingClientRect();
    const canvasX = e.clientX - rect.left;
    const canvasY = e.clientY - rect.top;

    // Account for canvas scaling if the displayed size differs from internal size
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    const scaledCanvasX = canvasX * scaleX;
    const scaledCanvasY = canvasY * scaleY;

    // Convert canvas coordinates to world coordinates
    const worldX = (scaledCanvasX - viewport.x) / viewport.zoom;
    const worldY = (scaledCanvasY - viewport.y) / viewport.zoom;

    // Snap to grid (round to nearest integer)
    const snappedX = Math.round(worldX);
    const snappedY = Math.round(worldY);

    waypoints.push([snappedX, snappedY]);
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
    document.getElementById("waypointCount").textContent = waypoints.length;

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
        modeIndicator.textContent =
            "WAYPOINT MODE: Click to add waypoints, ESC to exit, Middle-click to pan";
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

    // Set background - check for dark mode
    const isDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
    ctx.fillStyle = isDarkMode ? "#1f2937" : "white"; // Use gray-800 for dark mode
    ctx.strokeStyle = isDarkMode ? "#e5e7eb" : "black"; // Use gray-200 for dark mode
    ctx.fillRect(0, 0, canvas.width, canvas.height);


    // Calculate visible area in world coordinates
    const topLeft = canvasToWorld(0, 0);
    const bottomRight = canvasToWorld(canvas.width, canvas.height);

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

    // Calculate which part of the grid is visible
    const topLeft = canvasToWorld(0, 0);
    const bottomRight = canvasToWorld(canvas.width, canvas.height);

    const startX = Math.max(0, Math.floor(topLeft.x));
    const endX = Math.min(grid[0].length, Math.ceil(bottomRight.x));
    const startY = Math.max(0, Math.floor(topLeft.y));
    const endY = Math.min(grid.length, Math.ceil(bottomRight.y));

    // Only draw visible cells
    for (let y = startY; y < endY; y++) {
        for (let x = startX; x < endX; x++) {
            if (grid[y][x] === 1) {
                const canvasPos = worldToCanvas(x, y);
                ctx.fillStyle = "black";
                ctx.fillRect(canvasPos.x, canvasPos.y, viewport.zoom, viewport.zoom);
            }
        }
    }

    drawWaypointsAndPath();
}

function drawWaypointsAndPath() {
    // Draw waypoints
    waypoints.forEach((wp, i) => {
        const canvasPos = worldToCanvas(wp[0], wp[1]);
        // Waypoint size that scales reasonably with zoom
        const baseSize = 8;
        const minSize = 4;
        const maxSize = 16;
        const size = Math.max(minSize, Math.min(maxSize, baseSize / Math.sqrt(viewport.zoom * 0.5)));

        ctx.fillStyle = "blue";
        ctx.fillRect(canvasPos.x - size / 2, canvasPos.y - size / 2, size, size);

        ctx.fillStyle = "black";
        const fontSize = Math.max(8, Math.min(14, 12 / Math.sqrt(viewport.zoom * 0.5)));
        ctx.font = `${fontSize}px Arial`;
        ctx.fillText(i, canvasPos.x + size / 2, canvasPos.y - size / 2);
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
        <div class="col-span-3 dark:bg-gray-800 rounded overflow-hidden relative">
            <!-- Overlay Info Panel -->
            <div
                class="absolute top-2 left-2 z-10 bg-white/80 dark:bg-gray-900/80 rounded-lg shadow px-4 py-2 flex flex-col gap-1 pointer-events-none">
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


        <!-- Controls Section -->
        <div class="col-span-1 gap-1 p-1 dark:bg-gray-800 rounded">
            <div class="p-2">
                <!-- File Upload -->
                <div class="mb-5">
                    <label for="dxfFile" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                        Upload DXF File
                    </label>
                    <input type="file" id="dxfFile" accept=".dxf"
                        class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" />
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-300" id="file_input_help">DXF files are
                        supported.</p>
                </div>

                <!-- Action Buttons -->
                <div class="grid grid-cols-2 mb-5">
                    <p class="block text-sm font-medium text-gray-900 dark:text-white col-span-2">Waypoint Control</p>
                    <hr class="mb-2 border-gray-300 dark:border-gray-600 col-span-2" />
                    
                    
                    <button id="addWpBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        � Add Waypoint
                    </button>

                    <button id="clearWpBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        �️ Clear Waypoints
                    </button>
                </div>


                <div class="grid grid-cols-1 mb-5">
                    <p class="block text-sm font-medium text-gray-900 dark:text-white">Pathfinding & view control</p>
                    <hr class="mb-2 border-gray-300 dark:border-gray-600" />
                    
                    
                    <button id="computePathBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        � Compute Path
                    </button>

                    <button id="resetViewBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        🔄 Reset View
                    </button>
                </div>

                <div class="grid grid-cols-2 mb-5">
                    <p class="block text-sm font-medium text-gray-900 dark:text-white col-span-2">Editor</p>
                    <hr class="mb-2 border-gray-300 dark:border-gray-600 col-span-2" />

                    <!-- Wall Editing Controls -->
                    <button id="editModeBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        ✏️ Edit Walls
                    </button>

                    <button id="cutWallBtn" type="button" @click="setEditingTool('cut')"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        ✂️ Select & Remove
                    </button>

                    <button id="addWallBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        ➕ Add Wall
                    </button>

                    <button id="removeWallBtn" type="button"
                        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">
                        ➖ Remove Wall
                    </button>
                </div>

                <div class="grid grid-cols-1 mb-5">
                    <p class="block text-sm font-medium text-gray-900 dark:text-white">Pathfinding Settings</p>
                    <hr class="mb-2 border-gray-300 dark:border-gray-600" />
                    <!-- Wall Distance Control -->
                    <div>
                        <label for="wallBufferSlider"
                            class="block text-sm font-medium text-gray-900 dark:text-white">
                            Wall Distance: <span id="wallBufferValue">2</span>
                        </label>
                        <input id="wallBufferSlider" type="range" min="0" max="5" value="2"
                            class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700" />
                    </div>

                    <!-- Path Smoothing -->
                    <div>
                        <input id="pathSmoothingCheckbox" type="checkbox" checked
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" />
                        <label for="pathSmoothingCheckbox"
                            class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">
                            Enable Path Smoothing
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flowbite Loading Modal -->
        <div id="modalBackdrop" class="hidden fixed inset-0 z-40 bg-gray-900 bg-opacity-50 dark:bg-opacity-80"></div>

        <div id="loadingModal" class="hidden fixed inset-0 z-50 w-full h-full items-center justify-center">
            <div class="relative w-full max-w-md max-h-full">
                <!-- Modal content -->
                <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
                    <!-- Modal body -->
                    <div class="p-6 text-center">
                        <div role="status" class="flex justify-center mb-4">
                            <svg aria-hidden="true"
                                class="w-8 h-8 text-gray-200 animate-spin dark:text-gray-600 fill-blue-600"
                                viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z"
                                    fill="currentColor" />
                                <path
                                    d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z"
                                    fill="currentFill" />
                            </svg>
                            <span class="sr-only">Loading...</span>
                        </div>
                        <h3 id="loadingText">Loading...</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>