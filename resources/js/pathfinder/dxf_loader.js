import DxfParser from 'dxf-parser';

export async function loadDxfFile(file, buffer = 5) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = async () => {
            try {
                console.log('Parsing DXF file...');
                const parser = new DxfParser();
                const dxf = parser.parseSync(reader.result);
                
                console.log('DXF parsed. Total entities:', dxf.entities.length);
                
                // Log all entity types found
                const entityTypes = {};
                dxf.entities.forEach(e => {
                    entityTypes[e.type] = (entityTypes[e.type] || 0) + 1;
                });
                console.log('Entity types found:', entityTypes);
                
                // Check for LINE entities first
                let lineEntities = dxf.entities.filter(e => e.type === 'LINE');
                console.log('Total LINE entities found:', lineEntities.length);
                
                // Debug the first few LINE entities to see their structure
                if (lineEntities.length > 0) {
                    console.log('First LINE entity structure:', lineEntities[0]);
                    console.log('LINE entity properties:', Object.keys(lineEntities[0]));
                }
                
                // Filter LINE entities that have start and end points
                let validLineEntities = lineEntities.filter(e => e.start && e.end);
                console.log('Valid LINE entities (with start/end):', validLineEntities.length);
                
                // If no valid LINE entities, try different property names
                if (validLineEntities.length === 0 && lineEntities.length > 0) {
                    console.log('Checking for alternative LINE entity structures...');
                    
                    // Check for vertices property
                    let lineEntitiesWithVertices = lineEntities.filter(e => e.vertices && e.vertices.length >= 2);
                    console.log('LINE entities with vertices:', lineEntitiesWithVertices.length);
                    
                    // Check for startPoint/endPoint
                    let lineEntitiesWithPoints = lineEntities.filter(e => e.startPoint && e.endPoint);
                    console.log('LINE entities with startPoint/endPoint:', lineEntitiesWithPoints.length);
                    
                    // Show detailed structure of first LINE entity
                    if (lineEntities.length > 0) {
                        console.log('Detailed LINE entity structure:');
                        const firstLine = lineEntities[0];
                        for (const [key, value] of Object.entries(firstLine)) {
                            console.log(`  ${key}:`, value);
                        }
                    }
                }
                
                // If no LINE entities, check for LWPOLYLINE entities
                let polylineEntities = [];
                if (validLineEntities.length === 0) {
                    polylineEntities = dxf.entities.filter(e => e.type === 'LWPOLYLINE' && e.vertices && e.vertices.length > 1);
                    console.log('LWPOLYLINE entities found:', polylineEntities.length);
                }
                
                // Check for POLYLINE entities
                let polylineEntities2 = [];
                if (validLineEntities.length === 0 && polylineEntities.length === 0) {
                    polylineEntities2 = dxf.entities.filter(e => e.type === 'POLYLINE' && e.vertices && e.vertices.length > 1);
                    console.log('POLYLINE entities found:', polylineEntities2.length);
                }
                
                // Check for LINE entities with vertices (like in AutoCAD 2018 format)
                let lineEntitiesWithVertices = [];
                if (validLineEntities.length === 0) {
                    lineEntitiesWithVertices = lineEntities.filter(e => e.vertices && e.vertices.length >= 2);
                    console.log('LINE entities with vertices:', lineEntitiesWithVertices.length);
                    
                    if (lineEntitiesWithVertices.length > 0) {
                        console.log('Sample LINE vertices:', lineEntitiesWithVertices[0].vertices);
                    }
                }
                
                // If still no entities, show detailed info about a few entities
                if (validLineEntities.length === 0 && polylineEntities.length === 0 && polylineEntities2.length === 0 && lineEntitiesWithVertices.length === 0) {
                    console.log('Sample entities (first 3):');
                    dxf.entities.slice(0, 3).forEach((e, i) => {
                        console.log(`Entity ${i}:`, e);
                    });
                    reject(new Error(`No drawable entities found. Found entity types: ${Object.keys(entityTypes).join(', ')}`));
                    return;
                }

                // min/max koordináták
                let minX = Infinity, minY = Infinity;
                let maxX = -Infinity, maxY = -Infinity;
                
                // Collect all line segments from different entity types
                let allLineSegments = [];

                // Process LINE entities
                validLineEntities.forEach(e => {
                    const segment = { start: e.start, end: e.end };
                    allLineSegments.push(segment);
                    minX = Math.min(minX, e.start.x, e.end.x);
                    minY = Math.min(minY, e.start.y, e.end.y);
                    maxX = Math.max(maxX, e.start.x, e.end.x);
                    maxY = Math.max(maxY, e.start.y, e.end.y);
                });
                
                // If no valid standard LINE entities, try alternative structures
                if (validLineEntities.length === 0 && lineEntities.length > 0) {
                    lineEntities.forEach(e => {
                        let start = null, end = null;
                        
                        // Try different property names
                        if (e.startPoint && e.endPoint) {
                            start = e.startPoint;
                            end = e.endPoint;
                        } else if (e.vertices && e.vertices.length >= 2) {
                            start = e.vertices[0];
                            end = e.vertices[1];
                        } else if (e.x && e.y && e.x1 !== undefined && e.y1 !== undefined) {
                            start = { x: e.x, y: e.y };
                            end = { x: e.x1, y: e.y1 };
                        }
                        
                        if (start && end && typeof start.x === 'number' && typeof start.y === 'number' && 
                            typeof end.x === 'number' && typeof end.y === 'number') {
                            const segment = { start, end };
                            allLineSegments.push(segment);
                            minX = Math.min(minX, start.x, end.x);
                            minY = Math.min(minY, start.y, end.y);
                            maxX = Math.max(maxX, start.x, end.x);
                            maxY = Math.max(maxY, start.y, end.y);
                        }
                    });
                }
                
                // Process LINE entities with vertices (AutoCAD 2018 format)
                lineEntitiesWithVertices.forEach(e => {
                    if (e.vertices && e.vertices.length >= 2) {
                        const start = e.vertices[0];
                        const end = e.vertices[1];
                        
                        if (start && end && typeof start.x === 'number' && typeof start.y === 'number' && 
                            typeof end.x === 'number' && typeof end.y === 'number') {
                            const segment = { start, end };
                            allLineSegments.push(segment);
                            minX = Math.min(minX, start.x, end.x);
                            minY = Math.min(minY, start.y, end.y);
                            maxX = Math.max(maxX, start.x, end.x);
                            maxY = Math.max(maxY, start.y, end.y);
                        }
                    }
                });
                
                // Process LWPOLYLINE entities
                polylineEntities.forEach(e => {
                    for (let i = 0; i < e.vertices.length - 1; i++) {
                        const start = e.vertices[i];
                        const end = e.vertices[i + 1];
                        const segment = { 
                            start: { x: start.x, y: start.y }, 
                            end: { x: end.x, y: end.y } 
                        };
                        allLineSegments.push(segment);
                        minX = Math.min(minX, start.x, end.x);
                        minY = Math.min(minY, start.y, end.y);
                        maxX = Math.max(maxX, start.x, end.x);
                        maxY = Math.max(maxY, start.y, end.y);
                    }
                    // If closed, connect last to first
                    if (e.closed && e.vertices.length > 2) {
                        const start = e.vertices[e.vertices.length - 1];
                        const end = e.vertices[0];
                        const segment = { 
                            start: { x: start.x, y: start.y }, 
                            end: { x: end.x, y: end.y } 
                        };
                        allLineSegments.push(segment);
                        minX = Math.min(minX, start.x, end.x);
                        minY = Math.min(minY, start.y, end.y);
                        maxX = Math.max(maxX, start.x, end.x);
                        maxY = Math.max(maxY, start.y, end.y);
                    }
                });
                
                // Process POLYLINE entities
                polylineEntities2.forEach(e => {
                    for (let i = 0; i < e.vertices.length - 1; i++) {
                        const start = e.vertices[i];
                        const end = e.vertices[i + 1];
                        const segment = { 
                            start: { x: start.x, y: start.y }, 
                            end: { x: end.x, y: end.y } 
                        };
                        allLineSegments.push(segment);
                        minX = Math.min(minX, start.x, end.x);
                        minY = Math.min(minY, start.y, end.y);
                        maxX = Math.max(maxX, start.x, end.x);
                        maxY = Math.max(maxY, start.y, end.y);
                    }
                });
                
                console.log('Total line segments extracted:', allLineSegments.length);
                
                console.log('DXF bounds:', { minX, minY, maxX, maxY });

                const width = Math.ceil(maxX - minX) + 1 + buffer;
                const height = Math.ceil(maxY - minY) + 1 + buffer;
                
                console.log('Original grid dimensions:', width, 'x', height);
                
                // Store original bounds for viewport calculations
                const bounds = { minX, minY, maxX, maxY, width, height };
                
                // For very large grids, we'll use a different approach
                // Instead of creating a huge array, we'll store the line segments
                // and render them on-demand based on the viewport
                
                if (width * height > 10000000) { // 10 million cells
                    console.log('Grid too large, using on-demand rendering');
                    resolve({ 
                        lineSegments: allLineSegments, 
                        bounds, 
                        width, 
                        height,
                        useOnDemandRendering: true
                    });
                    return;
                }
                
                const grid = Array.from({ length: height }, () => Array(width).fill(0));

                // vonalak rácsra rajzolása with scaling
                console.log('Drawing lines to grid...');
                let processedSegments = 0;
                
                // Process segments in batches to prevent UI freezing
                const batchSize = 25;
                for (let i = 0; i < allLineSegments.length; i += batchSize) {
                    const batch = allLineSegments.slice(i, i + batchSize);
                    
                    batch.forEach(segment => {
                        const x1 = Math.floor((segment.start.x - minX)) + Math.floor(buffer / 2);
                        const y1 = Math.floor((segment.start.y - minY)) + Math.floor(buffer / 2);
                        const x2 = Math.floor((segment.end.x - minX)) + Math.floor(buffer / 2);
                        const y2 = Math.floor((segment.end.y - minY)) + Math.floor(buffer / 2);
                        
                        plotLine(grid, x1, y1, x2, y2);
                        processedSegments++;
                    });
                    
                    // Progress update and yield control
                    if (i % 100 === 0) {
                        console.log(`Processed ${processedSegments}/${allLineSegments.length} segments`);
                        // Yield control to prevent UI freezing
                        await new Promise(resolve => setTimeout(resolve, 0));
                    }
                }
                
                console.log('Finished drawing lines to grid');

                // Count filled cells for verification
                let filledCells = 0;
                for (let y = 0; y < grid.length; y++) {
                    for (let x = 0; x < grid[0].length; x++) {
                        if (grid[y][x] === 1) filledCells++;
                    }
                }
                console.log('Filled cells in grid:', filledCells);

                resolve({ grid, width, height, bounds });
            } catch(err) {
                console.error('Error parsing DXF:', err);
                reject(err);
            }
        };
        
        reader.onerror = () => {
            reject(new Error('Failed to read file'));
        };
        
        reader.readAsText(file);
    });
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
