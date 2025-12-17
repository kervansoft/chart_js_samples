<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Vodafone RF Network Map Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

    <script>
      document.addEventListener('contextmenu', event => event.preventDefault());
      document.onkeydown = function(e) {
        if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) ||
            (e.ctrlKey && e.shiftKey && e.keyCode == 'J'.charCodeAt(0)) ||
            (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) ||
            (e.ctrlKey && e.keyCode == 'S'.charCodeAt(0))) return false;
      }
    </script>

    <style>
      body {
        margin: 0;
        font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background-color: #f8fafc;
        color: #334155;
        -webkit-user-select: none; user-select: none;
      }
      .scrollbar-thin::-webkit-scrollbar { width: 8px; height: 8px; }
      .scrollbar-thin::-webkit-scrollbar-track { background: #1e293b; }
      .scrollbar-thin::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
      .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #64748b; }
      @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
      .animate-fade-in { animation: fadeIn 0.5s ease-out forwards; }
      .stat-header { @apply px-2 py-2 text-xs font-bold uppercase tracking-wider text-center border-b border-gray-600; }
      .stat-cell { @apply px-2 py-2 text-xs border-b border-gray-700 text-center; }
    </style>
  </head>
  <body>
    <div id="root"></div>

    <script type="text/babel">
      const { useState, useCallback, useRef, useEffect, useMemo } = React;
      const { createRoot } = ReactDOM;

      // ===== CONFIG & CONSTANTS =====
      const CONFIG = {
        BEAM_WIDTH: 60,
        MIN_ANGULAR_GAP: 2,
        INDOOR: { START: 0.001, WIDTH: 0.0012, GAP: 0.0003 },
        OUTDOOR: { MIN_START: 0.008, WIDTH: 0.003, GAP: 0.0008 },
        TECH_PRIORITY: { 'GSM': 1, 'UMTS': 2, 'LTE': 3, '5G': 4 },
        EARTH_RADIUS: 6378137,
        NM_TO_METERS: 1852,
      };

      const BAND_COLORS = {
        "N78": "ffD187E0", "N28": "ff8080ff", "N8": "ff80ff80", "N1": "ff40a0ff", "N7": "ffffd700",
        "N258": "ffcccccc", "N3": "ffffff00", "N20": "ff4b0082", "3075": "fff08080", "1899": "ff90ee90",
        "6300": "ffcefa22", "276": "ffab82ff", "3725": "ff9370db", "301": "ff00bfff", "37950":"ffff69b4", "9460": "ff778899"
      };

      const FILES_CONFIG = [
        { key: 'GSM', label: 'GSM Engineering DB', sub: '.xls/.csv', required: true, color: 'text-green-600' },
        { key: 'UMTS', label: 'UMTS Engineering DB', sub: '.xls/.csv', required: true, color: 'text-orange-600' },
        { key: 'LTE', label: 'LTE Engineering DB', sub: '.xls/.csv', required: true, color: 'text-blue-600' },
        { key: '5G', label: '5G Engineering DB', sub: '.xls/.csv', required: true, color: 'text-purple-600' },
        { key: 'ENG_DB', label: 'Site Database', sub: '.xls/.csv', required: true, color: 'text-gray-600' },
        { key: 'URA', label: 'URA Info', sub: '.xls/.csv', required: true, color: 'text-teal-600' },
      ];

      const ICONS = {
        UploadCloud: (p) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m16 16-4-4-4 4"/></svg>,
        FileCheck: (p) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/></svg>,
        Download: (p) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>,
        CheckCircle2: (p) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>,
        Terminal: (p) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><polyline points="4 17 10 11 4 5"/><line x1="12" x2="20" y1="19" y2="19"/></svg>,
        Map: (p) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" x2="9" y1="3" y2="18"/><line x1="15" x2="15" y1="6" y2="21"/></svg>,
        Filter: (p) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>,
        Plus: (p) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M5 12h14"/><path d="M12 5v14"/></svg>,
        Trash2: (p) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>,
        Layers: (p) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>,
        BarChart: (p) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/></svg>,
        Activity: (p) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>,
        FileSpreadsheet: (p) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/></svg>,
      };

      // ===== UTILITY FUNCTIONS =====
      const createNormalizerCache = () => {
        const cache = new Map();
        return (text) => {
          if (cache.has(text)) return cache.get(text);
          const normalized = String(text || '')
            .replace(/[İı]/g, 'I').replace(/[Şş]/g, 'S').replace(/[Ğğ]/g, 'G')
            .replace(/[Üü]/g, 'U').replace(/[Öö]/g, 'O').replace(/[Çç]/g, 'C')
            .toUpperCase().trim();
          cache.set(text, normalized);
          return normalized;
        };
      };
      const normalizeTR = createNormalizerCache();

      const createPropGetter = (obj) => {
        const keys = Object.keys(obj || {});
        const lowerKeys = new Map(keys.map(k => [k.trim().toLowerCase(), k]));
        return (propKeys) => {
          for (const k of propKeys) {
            if (obj[k] !== undefined && obj[k] !== null && obj[k] !== '') 
              return typeof obj[k] === 'string' ? obj[k].trim() : obj[k];
            const cleanK = k.trim().toLowerCase();
            const found = lowerKeys.get(cleanK) || lowerKeys.get(cleanK.replace(/_/g, ''));
            if (found && obj[found] !== undefined && obj[found] !== null && obj[found] !== '')
              return typeof obj[found] === 'string' ? obj[found].trim() : obj[found];
          }
          return '';
        };
      };

      const toRad = deg => deg * Math.PI / 180;
      const toDeg = rad => rad * 180 / Math.PI;

      const getDestinationPoint = (lat, lon, az, distNM) => {
        const distM = distNM * CONFIG.NM_TO_METERS;
        const brng = toRad(az), lat1 = toRad(lat), lon1 = toRad(lon);
        const lat2 = Math.asin(Math.sin(lat1) * Math.cos(distM / CONFIG.EARTH_RADIUS) + 
                               Math.cos(lat1) * Math.sin(distM / CONFIG.EARTH_RADIUS) * Math.cos(brng));
        const lon2 = lon1 + Math.atan2(Math.sin(brng) * Math.sin(distM / CONFIG.EARTH_RADIUS) * Math.cos(lat1),
                                       Math.cos(distM / CONFIG.EARTH_RADIUS) - Math.sin(lat1) * Math.sin(lat2));
        return { lat: parseFloat(toDeg(lat2).toFixed(6)), lon: parseFloat(toDeg(lon2).toFixed(6)) };
      };

      const generateSectorPolygon = (lat, lon, az, beamWidth, innerR, outerR) => {
        const coords = [], startAngle = az - (beamWidth / 2), endAngle = az + (beamWidth / 2);
        for (let a = startAngle; a <= endAngle; a += 5) coords.push(getDestinationPoint(lat, lon, a, outerR));
        coords.push(getDestinationPoint(lat, lon, endAngle, outerR));
        if (innerR <= 0.0005) coords.push({lat, lon});
        else {
          for (let a = endAngle; a >= startAngle; a -= 5) coords.push(getDestinationPoint(lat, lon, a, innerR));
          coords.push(getDestinationPoint(lat, lon, startAngle, innerR));
        }
        coords.push(coords[0]);
        return coords;
      };

      const getBandColor = (tech, band) => {
        let b = String(band || '').trim().toUpperCase();
        if (tech === 'GSM') return "ff53a834";
        if (tech === 'UMTS' && (b === 'NULL' || b === '')) return "fff08080";
        if (tech === 'LTE') {
          const e = parseInt(b);
          if (!isNaN(e) && e >= 2750 && e <= 3449) return "ff6666ff";
        }
        if (BAND_COLORS[b]) return BAND_COLORS[b];
        if (!band || b === 'NULL' || b === 'UNDEFINED' || b === '') return "ff9e9e9e";
        let num = parseInt(b.replace(/\D/g, ''));
        if (isNaN(num)) num = b.split('').reduce((a, c) => a + c.charCodeAt(0), 0);
        const DEFAULT_PALETTE = ["ff778899", "ffD187E0", "ff8080ff", "ff80ff80", "ff40a0ff", "ffffd700", "ffffff00", "fff08080"];
        return DEFAULT_PALETTE[num % DEFAULT_PALETTE.length];
      };

      const getLteBandOrder = (earfcn) => {
        const e = parseInt(earfcn);
        if (isNaN(e)) return 99;
        if (e >= 6150 && e <= 6449) return 1;
        if (e >= 3450 && e <= 3799) return 2;
        if (e >= 1200 && e <= 1949) return 3;
        if (e >= 0 && e <= 599) return 4;
        if (e >= 2750 && e <= 3449) return 5;
        return 50;
      };

      // ===== ANALYSIS ENGINE =====
      const analyzeData = async (files, onLog, onProgress) => {
        const showLog = async (msgs, delay = 50) => {
          for (const msg of msgs) {
            onLog(msg);
            await new Promise(r => setTimeout(r, delay));
          }
        };

        onLog("Initializing analysis engine...");
        if (!window.XLSX || !window.JSZip) throw new Error("Core libraries missing!");

        const data = {};
        let processed = 0;
        const totalFiles = FILES_CONFIG.filter(c => files[c.key]).length;

        await showLog([`Found ${totalFiles} files to process.`, 'Starting data ingestion...'], 100);

        for (const config of FILES_CONFIG) {
          const file = files[config.key];
          if (!file && config.required) throw new Error(`${config.label} is missing!`);
          if (!file) continue;

          await showLog([
            `[OPEN] ${config.label} - ${file.name}`,
            `       Size: ${Math.round(file.size/1024)} KB`,
            `[PARSE] Processing workbook...`
          ]);

          const arrayBuffer = await file.arrayBuffer();
          const workbook = window.XLSX.read(arrayBuffer, { type: 'array' });
          data[config.key] = window.XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
          onLog(`[DONE] Ingested ${data[config.key].length} records.`, 'success');
          onProgress((++processed / totalFiles) * 30);
        }

        await showLog(['All files loaded. Indexing data...'], 80);

        // Index engineering DB
        const engDB = data['ENG_DB'].map(r => ({
          ...r,
          SiteID: String(r['Site_ID'] || r['SITE_ID'] || r['Name'] || 'UNKNOWN').trim()
        }));
        const engLookup = new Map(engDB.map(r => [r.SiteID, r]));
        onLog(` > Indexed ${engLookup.size} sites.`);

        // Index URA
        const uraLookup = new Map();
        if (data['URA']) {
          data['URA'].forEach(r => {
            let id = r['Site_ID'] ? String(r['Site_ID']) :
                     r['SITEID_4G'] ? 'S' + String(r['SITEID_4G']).trim().slice(-5) : '';
            if (id) uraLookup.set(id.trim(), r['URA']);
          });
        }
        onLog(` > Indexed ${uraLookup.size} URA records.`);
        onProgress(40);

        const techConfigs = [
          { key: 'GSM', nameCol: 'CELLNAME', latCol: 'Lat_Site', lonCol: 'Lon_Site', azCol: 'AZIMUTH', bandCol: null },
          { key: 'UMTS', nameCol: 'CELLNAME', latCol: 'Lat_Site', lonCol: 'Lon_Site', azCol: 'AZIMUTH', bandCol: 'UARFCNDOWNLINK' },
          { key: 'LTE', nameCol: 'CELLNAME', latCol: 'Lat_Site', lonCol: 'Lon_Site', azCol: 'AZIMUTH', bandCol: 'DLEARFCN' },
          { key: '5G', nameCol: 'CELLNAME', latCol: 'Lat_Site', lonCol: 'Lon_Site', azCol: 'AZIMUTH', bandCol: 'FREQUENCYBAND' }
        ];

        let allCells = [];
        const allSites = new Map();
        const cities = new Set(), districts = new Set();
        let totalCells = 0;

        for (const tech of techConfigs) {
          if (!data[tech.key]) continue;
          onLog(`[MERGE] Integrating ${tech.key} cells...`);

          const rows = data[tech.key].map(row => {
            const cellName = String(row[tech.nameCol] || '').trim();
            let siteID = (row['Site_ID'] || row['SiteID'] || '').toString().trim();
            if (!siteID && cellName.length > 6) siteID = 'S' + cellName.substring(1, 6);
            if (siteID.startsWith('N')) siteID = 'S' + siteID.substring(1);
            if (siteID.length === 5 && !siteID.startsWith('S')) siteID = 'S' + siteID;

            const eng = engLookup.get(siteID) || {};
            const getProp = createPropGetter(row);
            const getEngProp = createPropGetter(eng);

            const lat = parseFloat(getProp([tech.latCol, 'Lat', 'Latitude'])) || 0;
            const lon = parseFloat(getProp([tech.lonCol, 'Lon', 'Longitude'])) || 0;
            const az = parseFloat(getProp([tech.azCol, 'Azimuth']) || '0');
            const dist = getProp(['DISTRICT', 'District']) || getEngProp(['DISTRICT', 'District']);
            const city = getProp(['CITY', 'City']) || getEngProp(['CITY', 'City']);

            if (!allSites.has(siteID) && !isNaN(lat) && !isNaN(lon)) {
              const siteName = getEngProp(['SITE_NAME_SR', 'Site_Name_SR']) || getProp(['SITE_NAME', 'SiteName']) || siteID;
              allSites.set(siteID, {
                SiteID: siteID, SiteName: siteName, Lat: lat, Lon: lon, City: city, District: dist,
                DescData: {
                  Site: siteName, SiteID: siteID, Address: getEngProp(['SITE_ADDRESS', 'Address']),
                  Type: getEngProp(['SITE_TYPE', 'Type']), ConstructionType: getEngProp(['CONSTRUCTION_TYPE']),
                  ConstructionHeight: getEngProp(['CONSTRUCTION_HEIGHT']), District: dist, Lat: lat, Lon: lon
                }
              });
            }

            const masterSite = allSites.get(siteID);
            const finalCity = masterSite?.City || city;
            const finalDist = masterSite?.District || dist;

            if (finalCity) cities.add(normalizeTR(finalCity));
            if (finalDist) districts.add(normalizeTR(finalDist));

            return {
              ...eng, ...row, URA: uraLookup.get(siteID) || 'N/A',
              SiteID: siteID, _tech: tech.key,
              _lat: masterSite?.Lat || lat, _lon: masterSite?.Lon || lon,
              _az: az, _name: cellName, _band: tech.bandCol ? row[tech.bandCol] : null,
              _isIndoor: cellName.toUpperCase().includes('I'), _innerR: 0, _outerR: 0,
              City: finalCity, District: finalDist
            };
          }).filter(r => !isNaN(r._lat) && !isNaN(r._lon));

          onLog(`         > Found ${rows.length} valid cells.`);
          totalCells += rows.length;
          allCells.push(...rows);
          onProgress(40 + (techConfigs.indexOf(tech) + 1) / techConfigs.length * 20);
        }

        await showLog([
          `[OK] Data integration complete.`,
          `     Total cells: ${totalCells}, Sites: ${allSites.size}, Cities: ${cities.size}, Districts: ${districts.size}`
        ], 80);

        return { allCells, allSites, cities: Array.from(cities).sort(), districts: Array.from(districts).sort() };
      };

      // ===== COMPONENTS =====
      const FileUploadCard = ({ config, file, onChange }) => {
        const inputRef = useRef(null);
        const Icon = ICONS[config.icon] || ICONS.Activity;
        return (
          <div onClick={() => inputRef.current?.click()} className={`cursor-pointer transition-all duration-300 border-2 rounded-xl p-4 flex flex-col items-center gap-3 bg-white hover:shadow-lg hover:-translate-y-1 ${file ? 'border-emerald-400 bg-emerald-50/40' : config.required ? 'border-dashed border-gray-300 hover:border-blue-400' : 'border-dashed border-gray-200 hover:border-gray-400 opacity-80'}`}>
            <input ref={inputRef} type="file" accept=".xls,.xlsx,.csv,.txt" onChange={onChange} className="hidden" />
            <div className={`p-3 rounded-full transition-colors ${file ? 'bg-emerald-100' : 'bg-gray-100'}`}>
              {file ? <ICONS.CheckCircle2 className="w-6 h-6 text-emerald-600" /> : <Icon className={`w-6 h-6 ${config.color}`} />}
            </div>
            <div className="text-center">
              <h3 className={`font-semibold text-sm ${file ? 'text-emerald-800' : 'text-gray-700'}`}>{config.label}</h3>
              {file ? <p className="text-xs text-emerald-600 font-medium truncate max-w-[140px] mt-1">{file.name}</p> : <p className="text-xs text-gray-400 mt-1">{config.sub} {config.required && <span className="text-red-400 ml-1">*Required</span>}</p>}
            </div>
          </div>
        );
      };

      const ResultCard = ({ file }) => (
        <a href={URL.createObjectURL(file.blob)} download={file.name} className="flex flex-col items-center p-4 bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md transition-all duration-300 group">
          <div className="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
            <ICONS.Download className="w-6 h-6 text-blue-600" />
          </div>
          <span className="font-bold text-gray-800 mb-1 truncate w-full text-center">{file.name}</span>
          <span className="text-xs text-gray-500">Click to Download</span>
        </a>
      );

      function App() {
        const [files, setFiles] = useState({});
        const [logs, setLogs] = useState([]);
        const [step, setStep] = useState('UPLOAD');
        const [progress, setProgress] = useState(0);
        const [analyzedData, setAnalyzedData] = useState(null);
        const [generatedFiles, setGeneratedFiles] = useState([]);
        const logsEndRef = useRef(null);

        const addLog = (msg, type = 'info') => {
          const time = new Date().toLocaleTimeString([], {hour12:false, hour:'2-digit', minute:'2-digit', second:'2-digit'});
          setLogs(prev => [...prev.slice(-150), { text: msg, time, type }]);
        };

        useEffect(() => { logsEndRef.current?.scrollIntoView({ behavior: "smooth" }); }, [logs]);

        const handleAnalyze = async () => {
          setStep('PROCESSING');
          setProgress(0);
          addLog("Starting analysis...");
          setTimeout(async () => {
            try {
              const res = await analyzeData(files, addLog, setProgress);
              setAnalyzedData(res);
              addLog("Analysis complete.", 'success');
              setStep('CONFIG');
            } catch(e) {
              console.error(e);
              addLog(`Error: ${e.message}`, 'error');
              setStep('UPLOAD');
            }
          }, 100);
        };

        return (
          <div className="min-h-screen p-6 md:p-12 font-sans animate-fade-in">
            <div className="max-w-7xl mx-auto flex flex-col gap-4">
              <header className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-300">
                <div className="flex items-center gap-3">
                  <div className="p-3 bg-blue-600 rounded-lg shadow-lg shadow-blue-500/20"><ICONS.Map className="w-8 h-8 text-white" /></div>
                  <div><h1 className="text-3xl font-extrabold text-gray-800">Vodafone RF Network Map Generator</h1><p className="text-gray-500 font-medium">Istanbul RF Planning & Optimization Team</p></div>
                </div>
                <div className="hidden md:block"><div className="inline-flex items-center gap-2 px-4 py-2 bg-white rounded-full text-sm font-semibold shadow-sm text-gray-600 border border-gray-300"><span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>Ready</div></div>
              </header>

              <main className="flex flex-col gap-4">
                {step === 'UPLOAD' && (
                  <section className="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 md:p-8 animate-fade-in">
                    <h2 className="text-xl font-bold flex items-center gap-2 text-gray-800 mb-6"><ICONS.UploadCloud className="w-5 h-5 text-blue-600" />Data Sources</h2>
                    <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                      {FILES_CONFIG.map((conf) => (
                        <FileUploadCard key={conf.key} config={{...conf, icon: conf.key === 'ENG_DB' ? 'FileSpreadsheet' : conf.key === 'URA' ? 'Layers' : 'Activity'}} file={files[conf.key]} onChange={(e) => e.target.files?.[0] && setFiles(p => ({...p, [conf.key]: e.target.files[0]}))} />
                      ))}
                    </div>
                    <button onClick={handleAnalyze} className="w-full mt-8 py-4 rounded-xl font-bold text-lg shadow-lg transition-all transform hover:-translate-y-0.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white">Load & Analyze Data</button>
                  </section>
                )}

                {step === 'PROCESSING' && (
                  <section>
                    <div className="bg-slate-900 rounded-2xl shadow-2xl overflow-hidden flex flex-col border border-slate-700 h-96">
                      <div className="p-3 bg-slate-800 border-b border-slate-700 flex items-center justify-between">
                        <span className="text-slate-400 font-mono text-xs flex items-center gap-2"><ICONS.Terminal className="w-4 h-4" />CONSOLE OUTPUT</span>
                      </div>
                      <div className="h-0.5 w-full bg-slate-800"><div className="h-full bg-gradient-to-r from-blue-500 to-emerald-500 transition-all" style={{ width: `${progress}%` }} /></div>
                      <div className="flex-1 p-4 font-mono text-xs text-slate-300 overflow-y-auto space-y-1 scrollbar-thin">
                        {logs.map((log, i) => <div key={i} className={`flex gap-3 border-l-2 pl-3 ${log.type === 'error' ? 'border-red-500 bg-red-900/10' : log.type === 'success' ? 'border-emerald-500 bg-emerald-900/10' : 'border-slate-700'}`}><span className="text-slate-500 min-w-fit">{log.time}</span><span className={log.type === 'error' ? 'text-red-400' : log.type === 'success' ? 'text-emerald-400' : 'text-blue-200'}>{log.text}</span></div>)}
                        <div ref={logsEndRef} />
                      </div>
                    </div>
                  </section>
                )}

                {analyzedData && step !== 'PROCESSING' && (
                  <section className="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
                    <h2 className="text-lg font-bold text-gray-800 mb-4">Configuration Ready</h2>
                    <p className="text-gray-600">Data loaded: {analyzedData.cities.length} cities, {analyzedData.districts.length} districts</p>
                  </section>
                )}

                {generatedFiles.length > 0 && (
                  <section className="bg-white rounded-2xl shadow-xl p-6 border border-gray-200">
                    <h3 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2"><ICONS.FileCheck className="w-5 h-5 text-emerald-600" />Generated Results</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                      {generatedFiles.map((file, i) => <ResultCard key={i} file={file} />)}
                    </div>
                  </section>
                )}
              </main>
            </div>
          </div>
        );
      }

      createRoot(document.getElementById('root')).render(<App />);
    </script>
  </body>
</html>
