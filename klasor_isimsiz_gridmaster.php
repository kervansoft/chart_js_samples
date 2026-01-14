<?php
/**
 * GridMaster - CSV to Map Visualizer (Optimized for Large Datasets)
 * 
 * Requirements: PHP (No ZipArchive needed).
 * 
 * KMZ Optimizations:
 * - SharedStyle (6 colors = 6 styles vs thousands)
 * - 3 export modes: Name→Range, Range only, Name only
 * - Coordinate precision: 6 decimals (±11cm accuracy)
 * - Optimized folder hierarchy
 */

// Increase memory limit for large files
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$uploadError = '';
$processError = '';
$rawDataPoints = null;
$stats = [];
$uniqueNames = [];

// Check if POST request but no files (likely exceeded post_max_size)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $uploadError = 'File size exceeds PHP post_max_size limit.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
        ];
        $uploadError = isset($errorMessages[$file['error']])
            ? $errorMessages[$file['error']]
            : 'Unknown upload error code: ' . $file['error'];

    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $uploadError = 'Please upload a valid .csv file.';
    } else {
        try {
            $data = parseCSV($file['tmp_name']);
            if (empty($data)) {
                $processError = 'No valid data found in the CSV file. Please ensure columns "Lat", "Lon", "Value", "Name" exist.';
            } else {
                $rawDataPoints = $data;
                $stats['total_points'] = count($data);
                
                // Extract unique names for UI
                foreach ($data as $point) {
                    $name = $point[3];
                    if (!in_array($name, $uniqueNames)) {
                        $uniqueNames[] = $name;
                    }
                }
                sort($uniqueNames);
            }
        } catch (Exception $e) {
            $processError = 'Error processing file: ' . $e->getMessage();
        }
    }
}

/**
 * Custom Simple CSV Parser
 */
function parseCSV($filePath)
{
    $handle = fopen($filePath, "r");
    if ($handle === FALSE) {
        throw new Exception("Unable to open CSV file.");
    }

    // Detect BOM and remove it if present
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    // Read first line to detect delimiter
    $firstLine = fgets($handle);
    rewind($handle);
    if ($bom === "\xEF\xBB\xBF") {
        fread($handle, 3); // Skip BOM again
    }

    $delimiters = [",", ";", "\t", "|"];
    $delimiter = ",";
    $maxCols = 0;

    foreach ($delimiters as $d) {
        $cols = count(str_getcsv($firstLine, $d));
        if ($cols > $maxCols) {
            $maxCols = $cols;
            $delimiter = $d;
        }
    }

    $parsedData = [];
    $headers = [];
    $rowIndex = 0;

    while (($row = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
        if (array_filter($row) === [])
            continue;

        if (empty($headers)) {
            $headers = array_map('trim', $row);
        } else {
            $item = processRow($row, $headers);
            if ($item) {
                $parsedData[] = [$item['lat'], $item['lon'], $item['value'], $item['name'], $item['count']];
            }
        }
        $rowIndex++;
    }
    fclose($handle);

    return $parsedData;
}

function processRow($row, $headers)
{
    $latIdx = $lonIdx = $valIdx = $nameIdx = $countIdx = -1;

    foreach ($headers as $idx => $name) {
        $n = strtolower($name);
        if ($n === 'lat' || $n === 'latitude') $latIdx = $idx;
        if ($n === 'lon' || $n === 'long' || $n === 'longitude') $lonIdx = $idx;
        if ($n === 'value' || $n === 'val' || $n === 'signal' || $n === 'değer' || $n === 'deger') $valIdx = $idx;
        if ($n === 'name' || $n === 'ad' || $n === 'isim') $nameIdx = $idx;
        if ($n === 'count' || $n === 'adet' || $n === 'sayı' || $n === 'sayi') $countIdx = $idx;
    }

    if ($latIdx === -1 || $lonIdx === -1 || $valIdx === -1)
        return null;

    $lat = isset($row[$latIdx]) ? (float) $row[$latIdx] : 0;
    $lon = isset($row[$lonIdx]) ? (float) $row[$lonIdx] : 0;
    $val = isset($row[$valIdx]) ? (float) $row[$valIdx] : 0;
    $name = ($nameIdx !== -1 && isset($row[$nameIdx])) ? trim($row[$nameIdx]) : 'Unknown';
    $count = ($countIdx !== -1 && isset($row[$countIdx])) ? (int) $row[$countIdx] : 1;

    if ($lat == 0 && $lon == 0)
        return null;

    return ['lat' => $lat, 'lon' => $lon, 'value' => $val, 'name' => $name, 'count' => $count];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GridMaster Pro</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script src="https://unpkg.com/jszip@3.10.1/dist/jszip.min.js"></script>

    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            display: flex;
            flex-direction: column;
            background: #f0f2f5;
        }

        .header {
            background: white;
            padding: 10px 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
        }

        .header h1 {
            margin: 0;
            color: #1a73e8;
            font-size: 20px;
        }

        .main-container {
            flex: 1;
            display: flex;
            position: relative;
            overflow: hidden;
        }

        #map {
            flex: 1;
            height: 100%;
            background: #222;
        }

        .sidebar {
            width: 340px;
            background: white;
            border-left: 1px solid #ddd;
            display: flex;
            flex-direction: column;
            box-shadow: -2px 0 5px rgba(0, 0, 0, 0.05);
            z-index: 1001;
        }

        .sidebar-content {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .upload-box {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            background: #fafafa;
            transition: 0.3s;
        }

        .upload-box:hover {
            border-color: #1a73e8;
            background: #f0f7ff;
        }

        .btn {
            background: #1a73e8;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            font-weight: 500;
            font-size: 13px;
            transition: 0.2s;
        }

        .btn:hover {
            background: #1557b0;
        }

        .btn-outline {
            background: white;
            color: #1a73e8;
            border: 1px solid #1a73e8;
            margin: 5px 0;
        }

        .btn-outline:hover {
            background: #f0f7ff;
        }

        .btn-small {
            padding: 6px 8px;
            font-size: 12px;
            margin: 4px 0;
        }

        .error-msg {
            background: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 13px;
        }

        .section-title {
            font-size: 14px;
            text-transform: uppercase;
            color: #888;
            margin-top: 20px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            font-size: 13px;
            cursor: pointer;
            user-select: none;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            margin-right: 8px;
            border-radius: 3px;
            border: 1px solid #ddd;
        }

        .legend-count {
            background: #eee;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
            margin-left: auto;
            color: #555;
        }

        .export-section {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 12px;
            background: #fafafa;
            margin-bottom: 15px;
        }

        .export-section h4 {
            margin: 0 0 10px 0;
            font-size: 13px;
            color: #333;
        }

        #loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #1a73e8;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body>

    <div id="loading">
        <div class="spinner"></div>
        <p style="margin-top:10px; font-weight:500; color:#555;">Processing Data...</p>
    </div>

    <div class="header">
        <h1>GridMaster <span style="font-weight:normal; font-size:0.8em; opacity:0.7;">Pro</span></h1>
        <?php if ($rawDataPoints): ?>
            <div style="font-size:13px; color:#555;">
                <i class="fas fa-database"></i>
                <strong><?php echo number_format($stats['total_points']); ?></strong> Points
            </div>
        <?php endif; ?>
    </div>

    <div class="main-container">
        <div id="map"></div>

        <div class="sidebar">
            <div class="sidebar-content">
                <?php if ($uploadError): ?>
                    <div class="error-msg"><?php echo htmlspecialchars($uploadError); ?></div>
                <?php endif; ?>
                <?php if ($processError): ?>
                    <div class="error-msg"><?php echo htmlspecialchars($processError); ?></div>
                <?php endif; ?>

                <div style="margin-bottom: 20px;">
                    <h3 class="section-title">Dataset</h3>
                    <form action="" method="post" enctype="multipart/form-data"
                        onsubmit="document.getElementById('loading').style.display='flex'">
                        <div class="upload-box">
                            <input type="file" name="csv_file" accept=".csv" required
                                style="width:100%; font-size:12px;">
                            <button type="submit" class="btn"><i class="fas fa-cloud-upload-alt"></i> Upload CSV</button>
                        </div>
                    </form>
                </div>

                <?php if ($rawDataPoints): ?>
                    <div style="margin-bottom: 20px;">
                        <h3 class="section-title">Filters by Value Range</h3>
                        <div id="legend-container"></div>
                    </div>

                    <div>
                        <h3 class="section-title">Export Options</h3>
                        
                        <div class="export-section">
                            <h4><i class="fas fa-folder-tree"></i> Mode 1: Name → Range</h4>
                            <p style="font-size:12px; color:#666; margin:5px 0;">Grouped by Location Name, then Value Range</p>
                            <button class="btn btn-outline btn-small" onclick="showLoading(); exportKMZ('name_range')">
                                <i class="fas fa-file-archive"></i> KMZ (Nested)
                            </button>
                        </div>

                        <div class="export-section">
                            <h4><i class="fas fa-layer-group"></i> Mode 2: Value Range Only</h4>
                            <p style="font-size:12px; color:#666; margin:5px 0;">Grouped by Value Range only</p>
                            <button class="btn btn-outline btn-small" onclick="showLoading(); exportKMZ('range')">
                                <i class="fas fa-file-archive"></i> KMZ (Range)
                            </button>
                        </div>

                        <div class="export-section">
                            <h4><i class="fas fa-map-marker"></i> Mode 3: Name Only</h4>
                            <p style="font-size:12px; color:#666; margin:5px 0;">Grouped by Location Name only</p>
                            <button class="btn btn-outline btn-small" onclick="showLoading(); exportKMZ('name')">
                                <i class="fas fa-file-archive"></i> KMZ (Name)
                            </button>
                        </div>

                        <div style="border-top:1px solid #ddd; padding-top:10px; margin-top:10px;">
                            <button class="btn btn-outline btn-small" onclick="exportCSVSummary()">
                                <i class="fas fa-file-csv"></i> Summary CSV
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="color:#666; font-size:13px; text-align:center; padding:20px;">
                        Upload a CSV file with <b>Lat, Lon, Value, Name, Count</b> columns.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <script>
        const SQUARE_SIZE_METERS = 28.6;
        const HALF_SIDE_DEG = 14.3 / 111111;
        const MIN_ZOOM = 13;
        const COORD_PRECISION = 6; // 6 decimals = ±11cm accuracy

        const CATEGORIES = [
            { id: 'g1', label: '> -70', color: '#006400', min: -70, max: Infinity },
            { id: 'g2', label: '-70 to -80', color: '#90EE90', min: -80, max: -70 },
            { id: 'g3', label: '-80 to -95', color: '#FFFF00', min: -95, max: -80 },
            { id: 'g4', label: '-95 to -110', color: '#FFA500', min: -110, max: -95 },
            { id: 'g5', label: '-110 to -125', color: '#FF0000', min: -125, max: -110 },
            { id: 'g6', label: '<= -125', color: '#8B0000', min: -Infinity, max: -125 }
        ];

        var map = L.map('map', { preferCanvas: true }).setView([39, 35], 6);

        var osmLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '© OpenStreetMap'
        });

        var satLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles © Esri'
        });

        satLayer.addTo(map);

        var baseMaps = { "Satellite": satLayer, "OpenStreetMap": osmLayer };
        var overlayMaps = {};
        L.control.layers(baseMaps, overlayMaps).addTo(map);

        <?php if ($rawDataPoints): ?>
            const rawData = <?php echo json_encode($rawDataPoints); ?>;
            const uniqueNames = <?php echo json_encode($uniqueNames); ?>;

            const groups = {};
            CATEGORIES.forEach(c => groups[c.id] = []);

            rawData.forEach(pt => {
                let lat = pt[0], lon = pt[1], val = pt[2], name = pt[3], count = pt[4];
                let catId = 'g6';
                if (val > -70) catId = 'g1';
                else if (val > -80) catId = 'g2';
                else if (val > -95) catId = 'g3';
                else if (val > -110) catId = 'g4';
                else if (val > -125) catId = 'g5';

                groups[catId].push({ lat, lon, val, name, count });
            });

            const bounds = L.latLngBounds([]);
            const layerGroups = {};

            CATEGORIES.forEach(cat => {
                const points = groups[cat.id];
                if (points.length === 0) return;

                const layerGroup = L.featureGroup();

                points.forEach(pt => {
                    let dLon = HALF_SIDE_DEG / Math.cos(pt.lat * Math.PI / 180);
                    let boundsArr = [
                        [pt.lat - HALF_SIDE_DEG, pt.lon - dLon],
                        [pt.lat + HALF_SIDE_DEG, pt.lon + dLon]
                    ];

                    L.rectangle(boundsArr, {
                        stroke: false,
                        fillColor: cat.color,
                        fillOpacity: 0.7,
                        interactive: true
                    })
                    .bindPopup(`<div style="font-size:13px"><b>Name:</b> ${pt.name}<br><b>Value:</b> ${pt.val}<br><b>Count:</b> ${pt.count}</div>`)
                    .addTo(layerGroup);

                    bounds.extend(boundsArr);
                });

                layerGroups[cat.id] = layerGroup;

                const container = document.getElementById('legend-container');
                const item = document.createElement('div');
                item.className = 'legend-item';
                let percent = (points.length / rawData.length * 100).toFixed(1);

                item.innerHTML = `
                    <input type="checkbox" checked style="margin-right:10px;">
                    <div class="legend-color" style="background:${cat.color}"></div>
                    <span>${cat.label}</span>
                    <span class="legend-count">${points.length} (${percent}%)</span>
                `;

                item.querySelector('input').addEventListener('change', function (e) {
                    if (e.target.checked) {
                        map.addLayer(layerGroups[cat.id]);
                    } else {
                        map.removeLayer(layerGroups[cat.id]);
                    }
                });

                container.appendChild(item);
            });

            function updateLayerVisibility() {
                const currentZoom = map.getZoom();
                const container = document.getElementById('legend-container');

                if (currentZoom < MIN_ZOOM) {
                    CATEGORIES.forEach(cat => {
                        if (layerGroups[cat.id] && map.hasLayer(layerGroups[cat.id])) {
                            map.removeLayer(layerGroups[cat.id]);
                        }
                    });

                    if (!document.getElementById('zoom-warning')) {
                        let warn = document.createElement('div');
                        warn.id = 'zoom-warning';
                        warn.style.cssText = 'position:absolute; top:80px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.7); color:white; padding:10px 20px; border-radius:30px; z-index:2000; font-weight:bold; font-size:14px; pointer-events:none;';
                        warn.innerText = `Zoom in to view data (Level ${currentZoom}/${MIN_ZOOM})`;
                        document.body.appendChild(warn);
                    }
                } else {
                    if (document.getElementById('zoom-warning')) {
                        document.getElementById('zoom-warning').style.display = 'none';
                    }

                    const inputs = container.querySelectorAll('input[type="checkbox"]');
                    inputs.forEach((input, index) => {
                        let cat = CATEGORIES[index];
                        if (input.checked && layerGroups[cat.id]) {
                            if (!map.hasLayer(layerGroups[cat.id])) {
                                map.addLayer(layerGroups[cat.id]);
                            }
                        }
                    });
                }
            }

            updateLayerVisibility();
            map.on('zoomend', updateLayerVisibility);

            function showLoading() {
                document.getElementById('loading').style.display = 'flex';
            }

            function hideLoading() {
                document.getElementById('loading').style.display = 'none';
            }

            /**
             * KML Style Generator (SharedStyle)
             */
            function getKMLStyles() {
                let styles = '';
                CATEGORIES.forEach(cat => {
                    let hex = cat.color.replace('#', '');
                    let r = hex.substring(0, 2);
                    let g = hex.substring(2, 4);
                    let b = hex.substring(4, 6);
                    let kmlColor = 'B0' + b + g + r; // AABBGGRR format

                    styles += `<Style id="style_${cat.id}">
                        <LineStyle><color>00ffffff</color><width>0</width></LineStyle>
                        <PolyStyle><color>${kmlColor}</color><fill>1</fill></PolyStyle>
                    </Style>\n`;
                });
                return styles;
            }

            /**
             * Folder visibility helper
             */
            function getFolderVisibility(isVisible = false) {
                return isVisible ? '1' : '0';
            }

            /**
             * Generate coordinates with precision
             */
            function getSquareCoords(lat, lon) {
                let dLon = HALF_SIDE_DEG / Math.cos(lat * Math.PI / 180);
                let minLat = (lat - HALF_SIDE_DEG).toFixed(COORD_PRECISION);
                let maxLat = (lat + HALF_SIDE_DEG).toFixed(COORD_PRECISION);
                let minLon = (lon - dLon).toFixed(COORD_PRECISION);
                let maxLon = (lon + dLon).toFixed(COORD_PRECISION);

                return `${minLon},${maxLat},0 ${minLon},${minLat},0 ${maxLon},${minLat},0 ${maxLon},${maxLat},0 ${minLon},${maxLat},0`;
            }

            function safeXML(text) {
                if (!text) return '';
                return String(text)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&apos;');
            }

            /**
             * Export Mode 1: Name → Range (Nested)
             */
            function exportKMZNested() {
                let kml = '<?xml version="1.0" encoding="UTF-8"?>\n' +
                    '<kml xmlns="http://www.opengis.net/kml/2.2"><Document><name>GridMaster Export</name>\n' +
                    getKMLStyles();

                // Group by name first
                const byName = {};
                uniqueNames.forEach(name => byName[name] = {});
                CATEGORIES.forEach(cat => {
                    uniqueNames.forEach(name => byName[name][cat.id] = []);
                });

                rawData.forEach(pt => {
                    let lat = pt[0], lon = pt[1], val = pt[2], name = pt[3], count = pt[4];
                    let catId = 'g6';
                    if (val > -70) catId = 'g1';
                    else if (val > -80) catId = 'g2';
                    else if (val > -95) catId = 'g3';
                    else if (val > -110) catId = 'g4';
                    else if (val > -125) catId = 'g5';

                    byName[name][catId].push({ lat, lon, val, count });
                });

                // Build nested folders
                uniqueNames.forEach(name => {
                    kml += `<Folder><name>${safeXML(name)}</name>\n`;

                    CATEGORIES.forEach(cat => {
                        const points = byName[name][cat.id];
                        if (points.length === 0) return;

                        kml += `<Folder><name>${safeXML(cat.label)}</name>\n`;

                        points.forEach(pt => {
                            let coords = getSquareCoords(pt.lat, pt.lon);
                            let desc = `<table><tr><td>Name</td><td>${safeXML(name)}</td></tr><tr><td>Value</td><td><b>${pt.val}</b></td></tr><tr><td>Count</td><td>${pt.count}</td></tr></table>`;

                            kml += `<Placemark><name>${safeXML(name)} (${pt.val})</name>
                                <description><![CDATA[${desc}]]></description>
                                <styleUrl>#style_${cat.id}</styleUrl>
                                <Polygon><outerBoundaryIs><LinearRing><coordinates>${coords}</coordinates></LinearRing></outerBoundaryIs></Polygon>
                            </Placemark>\n`;
                        });

                        kml += `</Folder>\n`;
                    });

                    kml += `</Folder>\n`;
                });

                kml += `</Document></kml>`;
                downloadKMZ(kml, 'gridmaster_nested.kmz');
            }

            /**
             * Export Mode 2: Range Only
             */
            function exportKMZRange() {
                let kml = '<?xml version="1.0" encoding="UTF-8"?>\n' +
                    '<kml xmlns="http://www.opengis.net/kml/2.2"><Document><name>GridMaster Export</name>\n' +
                    getKMLStyles();

                CATEGORIES.forEach(cat => {
                    const points = groups[cat.id];
                    if (points.length === 0) return;

                    kml += `<Folder><n>${safeXML(cat.label)}</n>\n`;

                    points.forEach(pt => {
                        let coords = getSquareCoords(pt.lat, pt.lon);
                        let desc = `<table><tr><td>Name</td><td>${safeXML(pt.name)}</td></tr><tr><td>Value</td><td><b>${pt.val}</b></td></tr><tr><td>Count</td><td>${pt.count}</td></tr></table>`;

                        kml += `<Placemark><n>${safeXML(pt.name)} (${pt.val})</n>
                            <description><![CDATA[${desc}]]></description>
                            <styleUrl>#style_${cat.id}</styleUrl>
                            <Polygon><outerBoundaryIs><LinearRing><coordinates>${coords}</coordinates></LinearRing></outerBoundaryIs></Polygon>
                        </Placemark>\n`;
                    });

                    kml += `</Folder>\n`;
                });

                kml += `</Document></kml>`;
                downloadKMZ(kml, 'gridmaster_range.kmz');
            }

            /**
             * Export Mode 3: Name Only
             */
            function exportKMZName() {
                let kml = '<?xml version="1.0" encoding="UTF-8"?>\n' +
                    '<kml xmlns="http://www.opengis.net/kml/2.2"><Document><n>GridMaster Export</n>\n' +
                    getKMLStyles();

                uniqueNames.forEach(name => {
                    const namePoints = rawData.filter(pt => pt[3] === name);
                    if (namePoints.length === 0) return;

                    kml += `<Folder><n>${safeXML(name)}</n>\n`;

                    namePoints.forEach(pt => {
                        let lat = pt[0], lon = pt[1], val = pt[2], count = pt[4];
                        let catId = 'g6';
                        if (val > -70) catId = 'g1';
                        else if (val > -80) catId = 'g2';
                        else if (val > -95) catId = 'g3';
                        else if (val > -110) catId = 'g4';
                        else if (val > -125) catId = 'g5';

                        let coords = getSquareCoords(lat, lon);
                        let desc = `<table><tr><td>Name</td><td>${safeXML(name)}</td></tr><tr><td>Value</td><td><b>${val}</b></td></tr><tr><td>Count</td><td>${count}</td></tr></table>`;

                        kml += `<Placemark><n>${safeXML(name)} (${val})</n>
                            <description><![CDATA[${desc}]]></description>
                            <styleUrl>#style_${getCategoryId(val)}</styleUrl>
                            <Polygon><outerBoundaryIs><LinearRing><coordinates>${coords}</coordinates></LinearRing></outerBoundaryIs></Polygon>
                        </Placemark>\n`;
                    });

                    kml += `</Folder>\n`;
                });

                kml += `</Document></kml>`;
                downloadKMZ(kml, 'gridmaster_name.kmz');
            }

            function getCategoryId(val) {
                if (val > -70) return 'g1';
                else if (val > -80) return 'g2';
                else if (val > -95) return 'g3';
                else if (val > -110) return 'g4';
                else if (val > -125) return 'g5';
                return 'g6';
            }

            function downloadKMZ(kml, filename) {
                var zip = new JSZip();
                zip.file("doc.kml", kml);
                zip.generateAsync({ type: "blob", compression: "DEFLATE" }).then(function (content) {
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(content);
                    link.download = filename;
                    link.click();
                    hideLoading();
                });
            }

            function exportKMZ(mode) {
                setTimeout(() => {
                    if (mode === 'name_range') {
                        exportKMZNested();
                    } else if (mode === 'range') {
                        exportKMZRange();
                    } else if (mode === 'name') {
                        exportKMZName();
                    }
                }, 100);
            }

            function exportCSVSummary() {
                let csv = "Range,Color,Count,Percentage\n";
                CATEGORIES.forEach(cat => {
                    let count = groups[cat.id] ? groups[cat.id].length : 0;
                    let percent = (count / rawData.length * 100).toFixed(2);
                    csv += `"${cat.label}","${cat.color}",${count},${percent}%\n`;
                });
                
                const link = document.createElement('a');
                link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
                link.download = 'gridmaster_summary.csv';
                link.click();
            }

        <?php endif; ?>
    </script>
</body>

</html>
