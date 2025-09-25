export function distanceMatrix(grid, waypoints, finder, findPathFunc) {
    const n = waypoints.length;
    const dist = Array.from({ length: n }, () => Array(n).fill(0));
    const paths = Array.from({ length: n }, () => Array(n).fill(null));
    
    console.log('Computing distance matrix for', n, 'waypoints');
    
    for(let i = 0; i < n; i++){
        for(let j = 0; j < n; j++){
            if(i !== j) {
                const path = findPathFunc(grid, waypoints[i], waypoints[j], finder);
                // Calculate actual distance along the path
                let pathDistance = 0;
                if (path && path.length > 1) {
                    for (let k = 0; k < path.length - 1; k++) {
                        const dx = path[k + 1][0] - path[k][0];
                        const dy = path[k + 1][1] - path[k][1];
                        pathDistance += Math.sqrt(dx * dx + dy * dy);
                    }
                }
                
                dist[i][j] = pathDistance;
                paths[i][j] = path;
            }
        }
    }
    
    // Show some sample distances for debugging
    if (n >= 2) {
        console.log(`Sample distance from waypoint 0 to 1: ${dist[0][1].toFixed(2)} units`);
        const straightLineDistance = Math.sqrt(
            Math.pow(waypoints[1][0] - waypoints[0][0], 2) + 
            Math.pow(waypoints[1][1] - waypoints[0][1], 2)
        );
        console.log(`Straight line distance 0 to 1: ${straightLineDistance.toFixed(2)} units`);
    }
    
    return { distances: dist, paths: paths };
}

export function tspNearestNeighbor(distMatrix) {
    const n = distMatrix.length;
    const visited = Array(n).fill(false);
    const order = [0];
    visited[0] = true;
    let current = 0;

    for(let step = 1; step < n; step++){
        let nearest = -1, nearestDist = Infinity;
        for(let i = 0; i < n; i++){
            if(!visited[i] && distMatrix[current][i] < nearestDist){
                nearest = i; 
                nearestDist = distMatrix[current][i];
            }
        }
        order.push(nearest);
        visited[nearest] = true;
        current = nearest;
    }
    return order;
}

// Advanced TSP solver using dynamic programming (Held-Karp algorithm)
// For small instances (up to ~15 waypoints), this gives the optimal solution
export function tspOptimal(distMatrix) {
    const n = distMatrix.length;
    
    // For very small instances, use brute force
    if (n <= 4) {
        return tspBruteForce(distMatrix);
    }
    
    // For medium instances (5-15), use dynamic programming
    if (n <= 15) {
        return tspHeldKarp(distMatrix);
    }
    
    // For larger instances, use advanced heuristics
    return tsp2Opt(distMatrix);
}

// Brute force for very small instances (optimal)
function tspBruteForce(distMatrix) {
    const n = distMatrix.length;
    if (n <= 1) return [0];
    
    const cities = Array.from({length: n - 1}, (_, i) => i + 1);
    let bestDistance = Infinity;
    let bestOrder = [0];
    
    function permute(arr, start = 0) {
        if (start === arr.length - 1) {
            const order = [0, ...arr];
            const distance = calculateTourDistance(order, distMatrix);
            if (distance < bestDistance) {
                bestDistance = distance;
                bestOrder = [...order];
            }
            return;
        }
        
        for (let i = start; i < arr.length; i++) {
            [arr[start], arr[i]] = [arr[i], arr[start]];
            permute(arr, start + 1);
            [arr[start], arr[i]] = [arr[i], arr[start]];
        }
    }
    
    permute(cities);
    return bestOrder;
}

// Held-Karp dynamic programming algorithm (optimal for medium instances)
function tspHeldKarp(distMatrix) {
    const n = distMatrix.length;
    const memo = new Map();
    
    function dp(mask, pos) {
        if (mask === (1 << n) - 1) {
            return { cost: 0, path: [] };
        }
        
        const key = `${mask},${pos}`;
        if (memo.has(key)) {
            return memo.get(key);
        }
        
        let minCost = Infinity;
        let bestNext = -1;
        let bestPath = [];
        
        for (let next = 0; next < n; next++) {
            if (!(mask & (1 << next))) {
                const newMask = mask | (1 << next);
                const result = dp(newMask, next);
                const cost = distMatrix[pos][next] + result.cost;
                
                if (cost < minCost) {
                    minCost = cost;
                    bestNext = next;
                    bestPath = [next, ...result.path];
                }
            }
        }
        
        const result = { cost: minCost, path: bestPath };
        memo.set(key, result);
        return result;
    }
    
    const result = dp(1, 0); // Start from city 0
    return [0, ...result.path];
}

// 2-opt improvement heuristic for larger instances
function tsp2Opt(distMatrix) {
    const n = distMatrix.length;
    
    // Start with nearest neighbor solution
    let order = tspNearestNeighbor(distMatrix);
    let improved = true;
    let iterations = 0;
    const maxIterations = n * n; // Limit iterations
    
    while (improved && iterations < maxIterations) {
        improved = false;
        iterations++;
        
        for (let i = 1; i < n - 1; i++) {
            for (let j = i + 1; j < n; j++) {
                // Try 2-opt swap
                const newOrder = [...order];
                
                // Reverse the segment between i and j
                let left = i;
                let right = j;
                while (left < right) {
                    [newOrder[left], newOrder[right]] = [newOrder[right], newOrder[left]];
                    left++;
                    right--;
                }
                
                const currentDistance = calculateTourDistance(order, distMatrix);
                const newDistance = calculateTourDistance(newOrder, distMatrix);
                
                if (newDistance < currentDistance) {
                    order = newOrder;
                    improved = true;
                }
            }
        }
    }
    
    console.log(`2-opt completed in ${iterations} iterations`);
    return order;
}

// Calculate total distance of a tour
function calculateTourDistance(order, distMatrix) {
    let totalDistance = 0;
    for (let i = 0; i < order.length - 1; i++) {
        totalDistance += distMatrix[order[i]][order[i + 1]];
    }
    return totalDistance;
}

// Calculate the actual distance in centimeters
export function calculateTotalDistance(order, distanceMatrix, pathMatrix) {
    let totalPathLength = 0;
    
    for (let i = 0; i < order.length - 1; i++) {
        const from = order[i];
        const to = order[i + 1];
        
        // Path length (following the actual pathfinding route)
        // distanceMatrix now contains actual euclidean distances, not point counts
        totalPathLength += distanceMatrix[from][to];
    }
    
    return {
        pathLength: totalPathLength, // Grid units (path following walls)
        euclideanDistance: totalPathLength, // Same as path length now
        pathLengthCm: totalPathLength, // 1 unit = 1 cm
        euclideanDistanceCm: totalPathLength // 1 unit = 1 cm
    };
}
