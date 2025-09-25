import PF from 'pathfinding';

export function createFinder() {
    return new PF.AStarFinder({ allowDiagonal: true, dontCrossCorners: true });
}

export function findPath(grid, start, end, finder) {
    if (!grid || !Array.isArray(grid) || grid.length === 0) {
        console.error('Invalid grid for pathfinding: grid is null, not an array, or empty');
        return [];
    }
    
    if (!Array.isArray(grid[0]) || grid[0].length === 0) {
        console.error('Invalid grid for pathfinding: grid rows are not arrays or empty');
        return [];
    }
    
    // Check if start and end points are within bounds
    if (!start || !end || start.length !== 2 || end.length !== 2) {
        console.error('Invalid start or end points for pathfinding');
        return [];
    }
    
    const [startX, startY] = start;
    const [endX, endY] = end;
    
    if (startX < 0 || startX >= grid[0].length || startY < 0 || startY >= grid.length ||
        endX < 0 || endX >= grid[0].length || endY < 0 || endY >= grid.length) {
        console.error(`Pathfinding coordinates out of bounds: start(${startX},${startY}) end(${endX},${endY}) grid(${grid[0].length}x${grid.length})`);
        return [];
    }
    
    try {
        const pfGrid = new PF.Grid(grid);
        const path = finder.findPath(startX, startY, endX, endY, pfGrid);
        return path || [];
    } catch (error) {
        console.error('Pathfinding error:', error);
        return [];
    }
}

// Function to apply wall buffer - expands walls to create safety margins
function applyWallBuffer(grid, bufferSize) {
    if (bufferSize <= 0) return;
    
    const height = grid.length;
    const width = grid[0].length;
    
    // Create a copy of the original grid to read from
    const originalGrid = grid.map(row => [...row]);
    
    // For each wall cell, mark surrounding cells as walls too
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (originalGrid[y][x] === 1) { // If this is a wall
                // Apply buffer in all directions
                for (let dy = -bufferSize; dy <= bufferSize; dy++) {
                    for (let dx = -bufferSize; dx <= bufferSize; dx++) {
                        const newY = y + dy;
                        const newX = x + dx;
                        
                        // Check if the new position is within grid bounds
                        if (newY >= 0 && newY < height && newX >= 0 && newX < width) {
                            // Use distance-based buffer (circle instead of square)
                            const distance = Math.sqrt(dx * dx + dy * dy);
                            if (distance <= bufferSize) {
                                grid[newY][newX] = 1;
                            }
                        }
                    }
                }
            }
        }
    }
    
    console.log(`Applied wall buffer of ${bufferSize} cells`);
}

// Enhanced grid creation that includes wall buffering for regular grids
export function applyWallBufferToGrid(grid, bufferSize = 2) {
    if (!grid || grid.length === 0 || bufferSize <= 0) return grid;
    
    // Create a copy to avoid modifying the original
    const bufferedGrid = grid.map(row => [...row]);
    applyWallBuffer(bufferedGrid, bufferSize);
    return bufferedGrid;
}

// Function to create a grid from line segments for pathfinding
export function createGridFromLineSegments(lineSegments, bounds, maxSize = 1000, wallBuffer = 2) {
    if (!lineSegments || !bounds) {
        console.error('Invalid line segments or bounds');
        return null;
    }
    
    const { minX, minY, width, height } = bounds;
    
    // Scale down if too large
    let scaleFactor = 1;
    let gridWidth = width;
    let gridHeight = height;
    
    if (width > maxSize || height > maxSize) {
        const scaleX = maxSize / width;
        const scaleY = maxSize / height;
        scaleFactor = Math.min(scaleX, scaleY);
        gridWidth = Math.floor(width * scaleFactor);
        gridHeight = Math.floor(height * scaleFactor);
    }
    
    console.log(`Creating pathfinding grid: ${gridWidth}x${gridHeight} (scale: ${scaleFactor.toFixed(3)}, buffer: ${wallBuffer})`);
    
    const grid = Array.from({ length: gridHeight }, () => Array(gridWidth).fill(0));
    
    // Draw line segments to grid
    lineSegments.forEach(segment => {
        const x1 = Math.floor((segment.start.x - minX) * scaleFactor);
        const y1 = Math.floor((segment.start.y - minY) * scaleFactor);
        const x2 = Math.floor((segment.end.x - minX) * scaleFactor);
        const y2 = Math.floor((segment.end.y - minY) * scaleFactor);
        
        plotLine(grid, x1, y1, x2, y2);
    });
    
    // Apply wall buffer to keep paths away from walls
    if (wallBuffer > 0) {
        applyWallBuffer(grid, wallBuffer);
    }

    return { grid, scaleFactor };
}

function plotLine(grid, x0, y0, x1, y1) {
    let dx = Math.abs(x1 - x0), sx = x0 < x1 ? 1 : -1;
    let dy = -Math.abs(y1 - y0), sy = y0 < y1 ? 1 : -1;
    let err = dx + dy;

    while(true) {
        if (x0 >= 0 && x0 < grid[0].length && y0 >= 0 && y0 < grid.length) grid[y0][x0] = 1;
        if (x0 === x1 && y0 === y1) break;
        let e2 = 2 * err;
        if (e2 >= dy) { err += dy; x0 += sx; }
        if (e2 <= dx) { err += dx; y0 += sy; }
    }
}

function clearLine(grid, x0, y0, x1, y1, width = 1) {
    let dx = Math.abs(x1 - x0), sx = x0 < x1 ? 1 : -1;
    let dy = -Math.abs(y1 - y0), sy = y0 < y1 ? 1 : -1;
    let err = dx + dy;

    while(true) {
        // Clear a wider area around the line
        for (let dy = -width; dy <= width; dy++) {
            for (let dx = -width; dx <= width; dx++) {
                const newX = x0 + dx;
                const newY = y0 + dy;
                if (newX >= 0 && newX < grid[0].length && newY >= 0 && newY < grid.length) {
                    grid[newY][newX] = 0; // Make passable
                }
            }
        }
        
        if (x0 === x1 && y0 === y1) break;
        let e2 = 2 * err;
        if (e2 >= dy) { err += dy; x0 += sx; }
        if (e2 <= dx) { err += dx; y0 += sy; }
    }
}

// Line-of-sight path smoothing - removes unnecessary waypoints
export function smoothPathLineOfSight(path, grid) {
    if (!path || path.length < 3 || !grid) return path;
    
    const smoothedPath = [path[0]]; // Start with first point
    let currentIndex = 0;
    
    while (currentIndex < path.length - 1) {
        let furthestReachable = currentIndex + 1;
        
        // Find the furthest point we can reach in a straight line
        for (let i = currentIndex + 2; i < path.length; i++) {
            if (hasLineOfSight(path[currentIndex], path[i], grid)) {
                furthestReachable = i;
            } else {
                break; // Stop at first unreachable point
            }
        }
        
        // Add the furthest reachable point
        smoothedPath.push(path[furthestReachable]);
        currentIndex = furthestReachable;
    }
    
    console.log(`Line-of-sight smoothing: ${path.length} -> ${smoothedPath.length} points`);
    return smoothedPath;
}

// Check if there's a clear line of sight between two points
function hasLineOfSight(start, end, grid) {
    const x0 = start[0];
    const y0 = start[1];
    const x1 = end[0];
    const y1 = end[1];
    
    // Bresenham's line algorithm to check each cell along the line
    const dx = Math.abs(x1 - x0);
    const dy = Math.abs(y1 - y0);
    const sx = x0 < x1 ? 1 : -1;
    const sy = y0 < y1 ? 1 : -1;
    let err = dx - dy;
    
    let x = x0;
    let y = y0;
    
    while (true) {
        // Check if current cell is walkable
        if (y >= 0 && y < grid.length && x >= 0 && x < grid[0].length) {
            if (grid[y][x] === 1) { // Hit a wall
                return false;
            }
        } else {
            return false; // Out of bounds
        }
        
        // Reached the end point
        if (x === x1 && y === y1) {
            break;
        }
        
        // Bresenham step
        const e2 = 2 * err;
        if (e2 > -dy) {
            err -= dy;
            x += sx;
        }
        if (e2 < dx) {
            err += dx;
            y += sy;
        }
    }
    
    return true;
}
