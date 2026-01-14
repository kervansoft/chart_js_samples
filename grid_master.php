<?php
/**
 * GridMaster - CSV to Map Visualizer (Optimized for Large Datasets)
 *
 * Requirements: PHP (No ZipArchive needed).
 */

// Increase memory limit for large files
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 600);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$uploadError = '';
$processError = '';
$rawDataPoints = null; // Will hold [[lat, lon, val, name, count], ...]
$stats = [];

// Check if POST request but no files (likely exceeded post_max_size)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $uploadError = 'File size exceeds PHP post_max_size limit.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    // Check for upload errors
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
        // Process the file
        try {
            $data = parseCSV($file['tmp_name']);
            if (empty($data)) {
                $processError = 'No valid data found in the CSV file. Please ensure columns "Lat", "Lon", "Value" exist.';
            } else {
                $rawDataPoints = $data;
                $stats['total_points'] = count($data);
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
        // Skip empty rows
        if (array_filter($row) === [])
            continue;

        if (empty($headers)) {
            // First non-empty row is headers
            $headers = array_map('trim', $row);
        } else {
            $item = processRow($row, $headers);
            if ($item) {
                // Return [lat, lon, val, name, count]
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
    $latIdx = -1;
    $lonIdx = -1;
    $valIdx = -1;
    $nameIdx = -1;
    $countIdx = -1;

    foreach ($headers as $idx => $name) {
        $n = strtolower($name);
        // UTF-8 safety for Turkish characters if needed, but strtolower handles basic ASCII
        if ($n === 'lat' || $n === 'latitude')
            $latIdx = $idx;
        if ($n === 'lon' || $n === 'long' || $n === 'longitude')
            $lonIdx = $idx;
        if ($n === 'value' || $n === 'val' || $n === 'signal' || $n === 'değer' || $n === 'deger')
            $valIdx = $idx;
        if ($n === 'name' || $n === 'ad' || $n === 'isim')
            $nameIdx = $idx;
        if ($n === 'count' || $n === 'adet' || $n === 'sayı' || $n === 'sayi')
            $countIdx = $idx;
    }

    if ($latIdx === -1 || $lonIdx === -1 || $valIdx === -1)
        return null;

    $lat = isset($row[$latIdx]) ? (float) $row[$latIdx] : 0;
    $lon = isset($row[$lonIdx]) ? (float) $row[$lonIdx] : 0;
    $val = isset($row[$valIdx]) ? (float) $row[$valIdx] : 0;
    $name = ($nameIdx !== -1 && isset($row[$nameIdx])) ? $row[$nameIdx] : '';
    $count = ($countIdx !== -1 && isset($row[$countIdx])) ? (int) $row[$countIdx] : 1; // Default to 1 if missing

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

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <!-- JSZip -->
    <script src="https://unpkg.com/jszip@3.10.1/dist/jszip.min.js"></script>

    <style>
        body,
        html {
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
            width: 320px;
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
        }

        .btn:hover {
            background: #1557b0;
        }

        .btn-outline {
            background: white;
            color: #1a73e8;
            border: 1px solid #1a73e8;
        }

        .btn-outline:hover {
            background: #f0f7ff;
        }

        .error-msg {
            background: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 13px;
        }

        .success-msg {
            color: #2e7d32;
            font-size: 13px;
            margin-top: 5px;
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

        /* Loading Overlay */
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
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
                    <div class="error-msg"><?php echo htmlspecialchars($uploadError); ?></div><?php endif; ?>
                <?php if ($processError): ?>
                    <div class="error-msg"><?php echo htmlspecialchars($processError); ?></div><?php endif; ?>

                <div style="margin-bottom: 20px;">
                    <h3 style="font-size:14px; text-transform:uppercase; color:#888; margin-top:0;">Dataset</h3>
                    <form action="" method="post" enctype="multipart/form-data"
                        onsubmit="document.getElementById('loading').style.display='flex'">
                        <div class="upload-box">
                            <input type="file" name="csv_file" accept=".csv" required
                                style="width:100%; font-size:12px;">
                            <button type="submit" class="btn"><i class="fas fa-cloud-upload-alt"></i> Upload
                                CSV</button>
                        </div>
                    </form>
                </div>

                <?php if ($rawDataPoints): ?>
                    <div style="margin-bottom: 20px;">
                        <h3 style="font-size:14px; text-transform:uppercase; color:#888;">Filters by Value</h3>
                        <div id="legend-container"></div>
                    </div>

                    <div>
                        <h3 style="font-size:14px; text-transform:uppercase; color:#888;">Export</h3>
                        <div style="display:flex; gap:10px;">
                            <button class="btn btn-outline" onclick="exportKMZ()"><i class="fas fa-file-archive"></i> KMZ
                                (Zipped)</button>
                            <button class="btn btn-outline" onclick="exportCSVSummary()"><i class="fas fa-file-csv"></i>
                                Summary CSV</button>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="color:#666; font-size:13px; text-align:center; padding:20px;">
                        Upload a CSV file with <b>Lat, Lon, Value, Name</b> columns.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <script>
        // --- Configuration ---
        const SQUARE_SIZE_METERS = 28.6;
        // Approx degrees for 14.3m radius (Simple approximation)
        // 1 deg Lat ~= 111km. 14.3m = 0.0000143 km 
        // dLat = 14.3 / 111111 ~= 0.0001287 degrees
        // 1 deg Lat ~= 111km. 14.3m = 0.0000143 km 
        // dLat = 14.3 / 111111 ~= 0.0001287 degrees
        const HALF_SIDE_DEG = 14.3 / 111111;
        const MIN_ZOOM = 13; // Optimization: Only show data when zoomed in

        // Categories
        const CATEGORIES = [
            { id: 'g1', label: '> -70', color: '#006400', min: -70, max: Infinity },
            { id: 'g2', label: '-70 to -80', color: '#90EE90', min: -80, max: -70 },
            { id: 'g3', label: '-80 to -95', color: '#FFFF00', min: -95, max: -80 },
            { id: 'g4', label: '-95 to -110', color: '#FFA500', min: -110, max: -95 },
            { id: 'g5', label: '-110 to -125', color: '#FF0000', min: -125, max: -110 },
            { id: 'g6', label: '<= -125', color: '#8B0000', min: -Infinity, max: -125 }
        ];

        // --- Map Initialization ---
        var map = L.map('map', {
            preferCanvas: true // IMPORTANT for performance
        }).setView([39, 35], 6); // Default Turkey view

        // Base Layers
        var osmLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '© OpenStreetMap'
        });

        var satLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles © Esri'
        });

        // Default to Satellite
        satLayer.addTo(map);

        // Layer Control
        var baseMaps = {
            "Satellite": satLayer,
            "OpenStreetMap": osmLayer
        };
        var overlayMaps = {}; // Will be populated
        L.control.layers(baseMaps, overlayMaps).addTo(map);


        <?php if ($rawDataPoints): ?>
            // --- Client-Side Processing ---

            // Raw Data: [[lat, lon, val, name], ...]
            const rawData = <?php echo json_encode($rawDataPoints); ?>;

            // Group Data
            const groups = {};
            CATEGORIES.forEach(c => groups[c.id] = []);

            rawData.forEach(pt => {
                let lat = pt[0];
                let lon = pt[1];
                let val = pt[2];
                let name = pt[3] !== undefined ? pt[3] : ''; // Name from Column 4
                let count = pt[4] !== undefined ? pt[4] : 1; // Count from Column 5

                let catId = 'g6';
                if (val > -70) catId = 'g1';
                else if (val > -80) catId = 'g2';
                else if (val > -95) catId = 'g3';
                else if (val > -110) catId = 'g4';
                else if (val > -125) catId = 'g5';

                groups[catId].push({ lat: lat, lon: lon, val: val, name: name, count: count });
            });

            // Create Layers using Canvas
            const bounds = L.latLngBounds([]);
            const layerGroups = {};

            CATEGORIES.forEach(cat => {
                const points = groups[cat.id];
                if (points.length === 0) return;

                const layerGroup = L.featureGroup(); // Group for this color

                points.forEach(pt => {
                    // Generate Square Bounds
                    let dLon = HALF_SIDE_DEG / Math.cos(pt.lat * Math.PI / 180);

                    let boundsArr = [
                        [pt.lat - HALF_SIDE_DEG, pt.lon - dLon], // SouthWest
                        [pt.lat + HALF_SIDE_DEG, pt.lon + dLon]  // NorthEast
                    ];

                    // Canvas optimized rectangle
                    L.rectangle(boundsArr, {
                        stroke: false, // No border
                        fillColor: cat.color,
                        fillOpacity: 0.7,
                        interactive: true // Ensure clickable
                    })
                        // Add detailed popup
                        .bindPopup(`
                <div style="font-size:13px">
                    <b>Name:</b> ${pt.name}<br>
                    <b>Value:</b> ${pt.val}<br>
                    <b>Count:</b> ${pt.count}
                </div>
            `)
                        .addTo(layerGroup);

                    bounds.extend(boundsArr);
                });

                // Add to map conditionally based on zoom
                // layerGroup.addTo(map); // REMOVED: Managed by zoom handler
                layerGroups[cat.id] = layerGroup;

                // Add Filters to UI
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

                // Toggle Handler
                item.querySelector('input').addEventListener('change', function (e) {
                    if (e.target.checked) {
                        map.addLayer(layerGroups[cat.id]);
                    } else {
                        map.removeLayer(layerGroups[cat.id]);
                    }
                });

                container.appendChild(item);
            });

            if (rawData.length > 0) {
                // map.fitBounds(bounds); // REMOVED: Optimization - Keep Turkey view
            }

            // --- Zoom Optimization Handler ---
            function updateLayerVisibility() {
                const currentZoom = map.getZoom();
                const container = document.getElementById('legend-container');

                if (currentZoom < MIN_ZOOM) {
                    // Hide all layers
                    CATEGORIES.forEach(cat => {
                        if (layerGroups[cat.id] && map.hasLayer(layerGroups[cat.id])) {
                            map.removeLayer(layerGroups[cat.id]);
                        }
                    });

                    // Show warning if not exists
                    if (!document.getElementById('zoom-warning')) {
                        let warn = document.createElement('div');
                        warn.id = 'zoom-warning';
                        warn.style.cssText = 'position:absolute; top:80px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.7); color:white; padding:10px 20px; border-radius:30px; z-index:2000; font-weight:bold; font-size:14px; pointer-events:none;';
                        warn.innerText = `Zoom in to view data (Level ${currentZoom}/${MIN_ZOOM})`;
                        document.body.appendChild(warn);
                    } else {
                        document.getElementById('zoom-warning').innerText = `Zoom in to view data (Level ${currentZoom}/${MIN_ZOOM})`;
                        document.getElementById('zoom-warning').style.display = 'block';
                    }

                } else {
                    // Show enabled layers
                    if (document.getElementById('zoom-warning')) {
                        document.getElementById('zoom-warning').style.display = 'none';
                    }

                    CATEGORIES.forEach(cat => {
                        // Check checkbox state
                        // Find the checkbox for this category
                        // Assuming order is same in DOM
                        // Better: store checkbox ref in CATEGORIES or lookup
                        // Simplified: Re-add all layers that should be active? 
                        // Actually, we need to respect the checkbox.
                        // Let's rely on the checkbox state which we don't store easily here.
                        // Instead, let's look at the DOM inputs.

                        // But wait, the checkboxes add/remove layers directly.
                        // If we are zoomed out, we force remove.
                        // If we zoom in, we should re-add IF checkbox is checked.
                    });

                    // Quick Fix: Re-trigger checkboxes or store state.
                    // Let's iterate inputs in legend-container
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

            // Initial Check
            updateLayerVisibility();

            // Event Listeners
            map.on('zoomend', updateLayerVisibility);

            // --- Export Functions ---
            function exportKMZ() {
                let kml = '<' + '?xml version="1.0" encoding="UTF-8"?>\n' +
                    `<kml xmlns="http://www.opengis.net/kml/2.2">
        <Document>
            <name>GridMaster Export</name>
            <Style id="polyStyle"><LineStyle><color>00ffffff</color><width>0</width></LineStyle><PolyStyle><fill>1</fill></PolyStyle></Style>
        `;

                CATEGORIES.forEach(cat => {
                    const points = groups[cat.id];
                    if (!points || points.length === 0) return;

                    // KML Color (AABBGGRR)
                    let hex = cat.color.replace('#', '');
                    let r = hex.substring(0, 2); let g = hex.substring(2, 4); let b = hex.substring(4, 6);
                    let kmlColor = 'B0' + b + g + r;

                    let safeLabel = cat.label.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

                    kml += `\n<Folder><name>${safeLabel}</name>\n`;

                    points.forEach(pt => {
                        // Generate coords again for KML
                        let dLon = HALF_SIDE_DEG / Math.cos(pt.lat * Math.PI / 180);
                        let minLat = pt.lat - HALF_SIDE_DEG; let maxLat = pt.lat + HALF_SIDE_DEG;
                        let minLon = pt.lon - dLon; let maxLon = pt.lon + dLon;

                        // TL, BL, BR, TR, TL
                        let coords = `${minLon},${maxLat},0 ${minLon},${minLat},0 ${maxLon},${minLat},0 ${maxLon},${maxLat},0 ${minLon},${maxLat},0`;

                        // Description for Popup
                        let desc = `
                    <table border="1" padding="3" width="300">
                        <tr><td>Name</td><td>${pt.name}</td></tr>
                        <tr><td>Value</td><td><b>${pt.val}</b></td></tr>
                        <tr><td>Count</td><td>${pt.count}</td></tr>
                    </table>
                `;

                        kml += `<Placemark>
                    <name>${pt.name || pt.val}</name>
                    <description><![CDATA[${desc}]]></description>
                    <Style><LineStyle><color>00ffffff</color><width>0</width></LineStyle><PolyStyle><color>${kmlColor}</color></PolyStyle></Style>
                    <Polygon><outerBoundaryIs><LinearRing><coordinates>${coords}</coordinates></LinearRing></outerBoundaryIs></Polygon>
                </Placemark>\n`;
                    });
                    kml += `</Folder>`;
                });

                kml += `</Document></kml>`;

                // Zip the KML
                var zip = new JSZip();
                zip.file("doc.kml", kml);
                zip.generateAsync({ type: "blob", compression: "DEFLATE" }).then(function (content) {
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(content);
                    link.download = "gridmaster.kmz";
                    link.click();
                });
            }

            function exportCSVSummary() {
                let csv = "Range,Color,Count\n";
                CATEGORIES.forEach(cat => {
                    let count = groups[cat.id] ? groups[cat.id].length : 0;
                    csv += `"${cat.label}","${cat.color}",${count}\n`;
                });
                downloadFile('gridmaster_summary.csv', csv, 'text/csv');
            }

            function downloadFile(filename, content, mime) {
                const link = document.createElement('a');
                link.href = URL.createObjectURL(new Blob([content], { type: mime }));
                link.download = filename;
                link.click();
            }

        <?php endif; ?>
    </script>
</body>

</html>
