<?php
/**
 * Cesium Quantized Mesh Terrain Server (PHP)
 * Serves .terrain tiles with proper headers for quantized-mesh-1.0 format
 *
 * Configuration:
 *   Set TERRAIN_BASE_DIR to point to your terrain directory outside www
 *
 * Directory structure:
 *   /var/www/html/terrain              (this file)
 *   /home/user/terrain/                (terrain files, outside www)
 *     ├── project1/
 *     │   ├── layer.json
 *     │   ├── 0/0/0.terrain
 *     │   └── ...
 *     └── project2/
 *         └── ...
 *
 * Usage:
 *   http://your-server.com/terrain/project1/layer.json
 *   http://your-server.com/terrain/project1/0/0/0.terrain
 */

// ======================================
// CONFIGURATION
// ======================================

// Base directory for terrain projects (outside www)
// Change this to your terrain directory path
define('TERRAIN_BASE_DIR', '/home9/terrain');

// Optional: Define project mapping for shorter URLs
// If empty, uses directory names directly
$PROJECT_MAP = [
    // 'alias' => 'actual_directory_name',
    // 'pnoa' => 'MDS05_ETRS89_H30_0064',
];

// Cache settings
define('ENABLE_CACHE', true);
define('CACHE_DURATION', 31536000); // 1 year

// Security: Prevent directory traversal
define('ALLOW_SUBDIRS', true); // Set false to only allow root level projects

// ======================================
// FUNCTIONS
// ======================================

/**
 * Set CORS headers for Cesium
 */
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Accept, Accept-Encoding, Content-Type, Range');
    header('Access-Control-Expose-Headers: Content-Length, Content-Encoding, Content-Type');
}

/**
 * Handle OPTIONS request (CORS preflight)
 */
function handleOptions() {
    setCorsHeaders();
    http_response_code(200);
    exit;
}

/**
 * Sanitize path to prevent directory traversal
 */
function sanitizePath($path) {
    // Remove any ../ or .\ attempts
    $path = str_replace(['../', '..\\', '\\'], '', $path);
    $path = trim($path, '/');
    return $path;
}

/**
 * Get real terrain directory path
 */
function getTerrainPath($project) {
    global $PROJECT_MAP;

    // Check if project uses alias
    if (isset($PROJECT_MAP[$project])) {
        $project = $PROJECT_MAP[$project];
    }

    $project = sanitizePath($project);
    $fullPath = TERRAIN_BASE_DIR . '/' . $project;

    // Security check: ensure path is within TERRAIN_BASE_DIR
    $realBase = realpath(TERRAIN_BASE_DIR);
    $realPath = realpath($fullPath);

    if ($realPath === false) {
        return null;
    }

    if (strpos($realPath, $realBase) !== 0) {
        return null; // Path traversal attempt
    }

    if (!is_dir($realPath)) {
        return null;
    }

    return $realPath;
}

/**
 * List available terrain projects
 */
function listProjects() {
    setCorsHeaders();
    header('Content-Type: application/json');

    $projects = [];

    if (!is_dir(TERRAIN_BASE_DIR)) {
        echo json_encode(['error' => 'Terrain directory not found']);
        exit;
    }

    $dirs = glob(TERRAIN_BASE_DIR . '/*', GLOB_ONLYDIR);

    foreach ($dirs as $dir) {
        $name = basename($dir);
        $layerJson = $dir . '/layer.json';

        $project = [
            'name' => $name,
            'url' => 'terrain/' . $name . '/',
        ];

        if (file_exists($layerJson)) {
            $data = json_decode(file_get_contents($layerJson), true);
            if ($data) {
                $project['bounds'] = $data['bounds'] ?? null;
                $project['maxzoom'] = $data['maxzoom'] ?? null;
            }
        }

        $projects[] = $project;
    }

    echo json_encode([
        'terrain_base' => TERRAIN_BASE_DIR,
        'projects' => $projects
    ], JSON_PRETTY_PRINT);
    exit;
}

/**
 * Serve layer.json metadata
 */
function serveLayerJson($terrainDir) {
    setCorsHeaders();
    header('Content-Type: application/json');

    if (ENABLE_CACHE) {
        header('Cache-Control: public, max-age=' . CACHE_DURATION);
    }

    $layerFile = $terrainDir . '/layer.json';

    if (!file_exists($layerFile)) {
        http_response_code(404);
        echo json_encode(['error' => 'layer.json not found']);
        exit;
    }

    readfile($layerFile);
    exit;
}

/**
 * Serve a terrain tile
 */
function serveTerrainTile($terrainDir, $z, $x, $y) {
    $tilePath = $terrainDir . "/$z/$x/$y.terrain";

    if (!file_exists($tilePath)) {
        http_response_code(404);
        setCorsHeaders();
        header('Content-Type: text/plain');
        echo "Tile not found: $z/$x/$y.terrain";
        exit;
    }

    // Set CORS headers
    setCorsHeaders();

    // Set content type for quantized mesh
    header('Content-Type: application/vnd.quantized-mesh');

    // Important: tiles are already gzipped, so set Content-Encoding
    header('Content-Encoding: gzip');

    // Cache headers
    if (ENABLE_CACHE) {
        header('Cache-Control: public, max-age=' . CACHE_DURATION);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + CACHE_DURATION) . ' GMT');

        // ETag for caching
        $etag = md5_file($tilePath);
        header('ETag: "' . $etag . '"');

        // Check if client has cached version
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
            trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
            http_response_code(304);
            exit;
        }
    }

    // Set content length
    header('Content-Length: ' . filesize($tilePath));

    // Serve the file
    readfile($tilePath);
    exit;
}

/**
 * Parse request and route to appropriate handler
 */
function handleRequest() {
    // Handle OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        handleOptions();
    }

    // Get path info
    $pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    $pathInfo = trim($pathInfo, '/');

    // If no path, list available projects
    if (empty($pathInfo)) {
        listProjects();
    }

    // Parse path: project/[layer.json | z/x/y.terrain]
    $parts = explode('/', $pathInfo);

    if (count($parts) < 1) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }

    // First part is project name
    $project = $parts[0];
    $terrainDir = getTerrainPath($project);

    if ($terrainDir === null) {
        http_response_code(404);
        setCorsHeaders();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Terrain project not found: ' . $project]);
        exit;
    }

    // If only project name, serve layer.json
    if (count($parts) === 1 || (count($parts) === 2 && $parts[1] === 'layer.json')) {
        serveLayerJson($terrainDir);
    }

    // Parse tile request: project/z/x/y.terrain
    if (count($parts) === 4 && substr($parts[3], -8) === '.terrain') {
        $z = (int)$parts[1];
        $x = (int)$parts[2];
        $y = (int)substr($parts[3], 0, -8); // Remove .terrain extension
        serveTerrainTile($terrainDir, $z, $x, $y);
    }

    // Invalid request
    http_response_code(404);
    setCorsHeaders();
    header('Content-Type: text/plain');
    echo "Invalid request format.\n\n";
    echo "Expected:\n";
    echo "  /terrain/                     - List projects\n";
    echo "  /terrain/project/             - Get layer.json\n";
    echo "  /terrain/project/layer.json   - Get layer.json\n";
    echo "  /terrain/project/z/x/y.terrain - Get tile\n";
    exit;
}

// Main execution
handleRequest();
