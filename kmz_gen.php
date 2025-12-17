<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Vodafone RF Network Map Generator</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- React & ReactDOM -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    
    <!-- Babel -->
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    
    <!-- Libraries -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

    <!-- Security -->
    <script>
      document.addEventListener('contextmenu', event => event.preventDefault());
      document.onkeydown = function(e) {
        if (e.keyCode == 123) return false;
        if (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) return false;
        if (e.ctrlKey && e.shiftKey && e.keyCode == 'J'.charCodeAt(0)) return false;
        if (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) return false;
        if (e.ctrlKey && e.keyCode == 'S'.charCodeAt(0)) return false;
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
    <!-- LICENSE / COPYRIGHT NOTICE -->
    <!-- 
      CONFIDENTIAL SOFTWARE
      This code is proprietary and confidential. 
      Unauthorized copying of this file, via any medium is strictly prohibited.
    -->
    <div id="root"></div>

    <script type="text/babel">
      const { useState, useCallback, useRef, useEffect, useMemo } = React;
      const { createRoot } = ReactDOM;
      const XLSX = window.XLSX;
      const JSZip = window.JSZip;

      // --- Icons ---
      const Icons = {
        UploadCloud: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m16 16-4-4-4 4"/></svg>,
        FileCheck: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/></svg>,
        Download: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>,
        Activity: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>,
        CheckCircle2: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>,
        Terminal: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><polyline points="4 17 10 11 4 5"/><line x1="12" x2="20" y1="19" y2="19"/></svg>,
        Map: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" x2="9" y1="3" y2="18"/><line x1="15" x2="15" y1="6" y2="21"/></svg>,
        Loader2: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M21 12a9 9 0 1 1-6.219-8.56" /></svg>,
        FileSpreadsheet: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/></svg>,
        Layers: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>,
        BarChart: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/></svg>,
        Globe: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>,
        Search: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>,
        Filter: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>,
        Plus: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M5 12h14"/><path d="M12 5v14"/></svg>,
        Trash2: (props) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
      };

      // --- TEMPLATES (District Level & City Level) ---
      const DISTRICT_TEMPLATES = {
        'Istanbul EUROPE': ['ARNAVUTKOY', 'AVCILAR', 'BAGCILAR', 'BAHCELIEVLER', 'BAKIRKOY', 'BASAKSEHIR', 'BAYRAMPASA', 'BESIKTAS', 'BEYLIKDUZU', 'BEYOGLU', 'BUYUKCEKMECE', 'CATALCA', 'ESENLER', 'ESENYURT', 'EYUPSULTAN', 'FATIH', 'GAZIOSMANPASA', 'GUNGOREN', 'KAGITHANE', 'KUCUKCEKMECE', 'SARIYER', 'SILIVRI', 'SISLI', 'SULTANGAZI', 'ZEYTINBURNU'],
        'Istanbul ASIA': ['ADALAR', 'ATASEHIR', 'BEYKOZ', 'CEKMEKOY', 'KADIKOY', 'KARTAL', 'MALTEPE', 'PENDIK', 'SANCAKTEPE', 'SILE', 'SULTANBEYLI', 'TUZLA', 'UMRANIYE', 'USKUDAR']
      };

      const CITY_TEMPLATES = {
        'Adana Region': ['ADANA', 'ANTALYA', 'GAZIANTEP', 'HATAY', 'KAHRAMANMARAS', 'KILIS', 'MERSIN', 'OSMANIYE'],
        'Ankara Region': ['ANKARA', 'CANKIRI', 'ESKISEHIR', 'KIRIKKALE'],
        'Antalya Region': ['AFYONKARAHISAR', 'ANTALYA', 'BURDUR', 'ISPARTA', 'KARAMAN', 'KONYA'],
        'Bursa Region': ['CANAKKALE', 'BALIKESIR', 'BILECIK', 'BURSA', 'ESKISEHIR', 'KUTAHYA', 'YALOVA'],
        'Diyarbakir Region': ['ADIYAMAN', 'BATMAN', 'BITLIS', 'DIYARBAKIR', 'HAKKARI', 'MARDIN', 'SANLIURFA', 'SIIRT', 'SIRNAK', 'VAN'],
        'Erzurum Region': ['AGRI', 'ARDAHAN', 'BAYBURT', 'BINGOL', 'ELAZIG', 'ERZINCAN', 'ERZURUM', 'GUMUSHANE', 'IGDIR', 'KARS', 'MUS', 'TRABZON', 'TUNCELI'],
        'Istanbul Region': ['EDIRNE', 'ISTANBUL', 'KIRKLARELI', 'TEKIRDAG'],
        'Izmir Region': ['AYDIN', 'DENIZLI', 'IZMIR', 'MANISA', 'MUGLA', 'USAK'],
        'Kayseri Region': ['AKSARAY', 'KAYSERI', 'KIRSEHIR', 'MALATYA', 'NEVSEHIR', 'NIGDE', 'SIVAS', 'YOZGAT'],
        'Kibris Region': ['GIRNE', 'GUZELYURT', 'ISKELE', 'LEFKE', 'LEFKOSA', 'MAGUSA'],
        'Sakarya Region': ['BARTIN', 'BILECIK', 'BOLU', 'DUZCE', 'KARABUK', 'KASTAMONU', 'KOCAELI', 'SAKARYA', 'YALOVA', 'ZONGULDAK'],
        'Samsun Region': ['AMASYA', 'ARTVIN', 'CORUM', 'GIRESUN', 'GUMUSHANE', 'ORDU', 'RIZE', 'SAMSUN', 'SINOP', 'TOKAT', 'TRABZON']
      };

      const FILES_CONFIG = [
        { key: 'GSM', label: 'GSM Engineering DB', sub: '.xls/.csv', required: true, icon: Icons.Activity, color: 'text-green-600' },
        { key: 'UMTS', label: 'UMTS Engineering DB', sub: '.xls/.csv', required: true, icon: Icons.Activity, color: 'text-orange-600' },
        { key: 'LTE', label: 'LTE Engineering DB', sub: '.xls/.csv', required: true, icon: Icons.Activity, color: 'text-blue-600' },
        { key: '5G', label: '5G Engineering DB', sub: '.xls/.csv', required: true, icon: Icons.Activity, color: 'text-purple-600' },
        { key: 'ENG_DB', label: 'Site Database', sub: '.xls/.csv', required: true, icon: Icons.FileSpreadsheet, color: 'text-gray-600' },
        { key: 'URA', label: 'URA Info', sub: '.xls/.csv', required: true, icon: Icons.Layers, color: 'text-teal-600' },
      ];

      const DEFAULT_BEAM_WIDTH = 60; const MIN_ANGULAR_GAP = 2;
      const INDOOR_START_RADIUS = 0.001; const INDOOR_WIDTH = 0.0012; const INDOOR_GAP = 0.0003; 
      const MIN_OUTDOOR_START_RADIUS = 0.008; const OUTDOOR_WIDTH = 0.003; const GAP_BETWEEN_LAYERS = 0.0008;
      const TECH_PRIORITY = { 'GSM': 1, 'UMTS': 2, 'LTE': 3, '5G': 4 };

      const BAND_COLOR_MAP = {
        "N78": "ffD187E0", "N28": "ff8080ff", "N8": "ff80ff80", "N1": "ff40a0ff", "N7": "ffffd700", "N258": "ffcccccc", "N3": "ffffff00", "N20": "ff4b0082",
        "3075": "fff08080", "1899": "ff90ee90", "6300": "ffcefa22", "276": "ffab82ff", "3725": "ff9370db", "301": "ff00bfff", "37950":"ffff69b4", "9460": "ff778899"
      };
      const DEFAULT_PALETTE = ["ff778899", "ffD187E0", "ff8080ff", "ff80ff80", "ff40a0ff", "ffffd700", "ffffff00", "fff08080"];

      // --- UTILS ---
      function toRad(deg) { return deg * Math.PI / 180; }
      function toDeg(rad) { return rad * 180 / Math.PI; }
      function getDestinationPoint(lat, lon, az, distNM) {
        const distMeters = distNM * 1852, R = 6378137;
        const brng = toRad(az), lat1 = toRad(lat), lon1 = toRad(lon);
        const lat2 = Math.asin(Math.sin(lat1) * Math.cos(distMeters / R) + Math.cos(lat1) * Math.sin(distMeters / R) * Math.cos(brng));
        const lon2 = lon1 + Math.atan2(Math.sin(brng) * Math.sin(distMeters / R) * Math.cos(lat1), Math.cos(distMeters / R) - Math.sin(lat1) * Math.sin(lat2));
        return { lat: parseFloat(toDeg(lat2).toFixed(6)), lon: parseFloat(toDeg(lon2).toFixed(6)) };
      }
      function generateSectorPolygon(lat, lon, az, beamWidth, innerRNM, outerRNM) {
        const coords = [], startAngle = az - (beamWidth / 2), endAngle = az + (beamWidth / 2), step = 5;
        for (let a = startAngle; a <= endAngle; a += step) { coords.push(getDestinationPoint(lat, lon, a, outerRNM)); }
        coords.push(getDestinationPoint(lat, lon, endAngle, outerRNM));
        if (innerRNM <= 0.0005) { coords.push({lat, lon}); } 
        else {
            for (let a = endAngle; a >= startAngle; a -= step) { coords.push(getDestinationPoint(lat, lon, a, innerRNM)); }
            coords.push(getDestinationPoint(lat, lon, startAngle, innerRNM));
        }
        coords.push(coords[0]); return coords;
      }
      function coordsToKml(coords) { return coords.map(p => `${p.lon},${p.lat},0`).join(' '); }
      function normalizeTR(text) {
        return String(text || '').replace(/İ/g, 'I').replace(/ı/g, 'I').replace(/Ş/g, 'S').replace(/ş/g, 'S')
          .replace(/Ğ/g, 'G').replace(/ğ/g, 'G').replace(/Ü/g, 'U').replace(/ü/g, 'U')
          .replace(/Ö/g, 'O').replace(/ö/g, 'O').replace(/Ç/g, 'C').replace(/ç/g, 'C').toUpperCase().trim();
      }
      function getProp(obj, keys) {
        if (!obj) return '';
        const objKeys = Object.keys(obj);
        for (const k of keys) {
          if (obj[k] !== undefined && obj[k] !== null && obj[k] !== '') return obj[k];
          const cleanK = k.trim().toLowerCase();
          const found = objKeys.find(key => {
            const cleanKey = key.trim().toLowerCase();
            return cleanKey === cleanK || cleanKey === cleanK.replace(/_/g, '');
          });
          if (found && obj[found] !== undefined && obj[found] !== null && obj[found] !== '') return typeof obj[found] === 'string' ? obj[found].trim() : obj[found];
        }
        return '';
      }
      function getBandColor(tech, band) {
        let b = String(band || '').trim().toUpperCase();
        if (tech === 'GSM') return "ff53a834"; 
        if (tech === 'UMTS' && (b === 'NULL' || b === '')) return "fff08080"; 
        if (tech === 'LTE') {
            const e = parseInt(b);
            if (!isNaN(e) && e >= 2750 && e <= 3449) return "ff6666ff"; 
        }
        if (BAND_COLOR_MAP[b] !== undefined) return BAND_COLOR_MAP[b];
        if (!band || b === 'NULL' || b === 'UNDEFINED' || b === '') return "ff9e9e9e";
        let num = parseInt(b.replace(/\D/g, ''));
        if (isNaN(num)) { num = 0; for(let i=0; i<b.length; i++) num += b.charCodeAt(i); }
        return DEFAULT_PALETTE[num % DEFAULT_PALETTE.length];
      }
      function getLteBandOrder(earfcn) {
        const e = parseInt(earfcn); if (isNaN(e)) return 99;
        if (e >= 6150 && e <= 6449) return 1; if (e >= 3450 && e <= 3799) return 2; if (e >= 1200 && e <= 1949) return 3;
        if (e >= 0 && e <= 599) return 4; if (e >= 2750 && e <= 3449) return 5; return 50;
      }
      function generateSharedStyles(usedColors) {
        return Array.from(usedColors).map(color => `<Style id="style_${color}"><LineStyle><color>${color}</color><width>1</width></LineStyle><PolyStyle><color>${color}</color><fill>1</fill><outline>1</outline></PolyStyle></Style>`).join('');
      }
      function generateKMLString(folderName, placemarksByFolder, usedColors) {
        const mainFolders = ['Site', 'GSM', 'UMTS', 'LTE', '5G'];
        const folderContent = mainFolders.map(tech => {
            const districtsObj = placemarksByFolder[tech] || {};
            const distKeys = Object.keys(districtsObj).sort();
            if(distKeys.length === 0) return '';
            const distXml = distKeys.map(d => `<Folder><name>${d}</name>${districtsObj[d].join('')}</Folder>`).join('');
            return `<Folder><name>${tech}</name>${distXml}</Folder>`;
        }).join('');
        return `<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document><name>${folderName}</name><Style id="site_style"><IconStyle><scale>0.8</scale><Icon><href>http://maps.google.com/mapfiles/kml/shapes/donut.png</href></Icon></IconStyle><LabelStyle><scale>0.7</scale></LabelStyle></Style>${generateSharedStyles(usedColors)}${folderContent}</Document></kml>`;
      }
      function buildCellPopupData(cell) {
        const siteName = getProp(cell, ['SITE_NAME_SR', 'Site_Name_SR', 'SITE_NAME', 'SiteName', 'Name']);
        const district = getProp(cell, ['DISTRICT', 'District']);
        let cellStatus = 'N/A';
        if (cell._tech === 'GSM') cellStatus = 'N/A';
        else if (cell._tech === 'UMTS') cellStatus = getProp(cell, ['ACTSTATUS', 'ActStatus', 'Status']) || 'N/A';
        else cellStatus = getProp(cell, ['CELLACTIVESTATE', 'CellActiveState', 'ActiveStatus']) || 'N/A';
        const band = cell._band || '-';
        let extraRow = '';
        if (cell._tech === 'GSM') {
            const trx = getProp(cell, ['TRX_NUM', 'TRX', 'TrxNum']) || '-'; extraRow = `<tr><th>TRX</th><td>${trx}</td></tr>`;
        } else if (cell._tech === 'LTE' || cell._tech === '5G') {
            const txrx = getProp(cell, ['TXRXMODE', 'TxRxMode', 'MIMO', 'Mimo', 'TxRx']) || '-'; extraRow = `<tr><th>Tx/Rx</th><td>${txrx}</td></tr>`;
        }
        let lacTacLabel='LAC-TAC',lacTacVal='',bcchPscPciLabel='BCCH-PSC-PCI',bcchPscPciVal='',bscRncLabel='BSC-RNC',bscRncVal='';
        if (cell._tech === 'GSM') { lacTacLabel='LAC'; lacTacVal=getProp(cell,['LAC']); bcchPscPciLabel='BCCH'; bcchPscPciVal=getProp(cell,['BCCHNO','BCCH']); bscRncLabel='BSC'; bscRncVal=getProp(cell,['BSC']); }
        else if (cell._tech === 'UMTS') { lacTacLabel='LAC'; lacTacVal=getProp(cell,['LAC']); bcchPscPciLabel='PSC'; bcchPscPciVal=getProp(cell,['PSCRAMBCODE','PSC']); bscRncLabel='RNC'; bscRncVal=getProp(cell,['RNCNAME','RNC']); }
        else if (cell._tech === 'LTE') { lacTacLabel='TAC'; lacTacVal=getProp(cell,['TAC','TAC_ATOLL']); bcchPscPciLabel='PCI'; bcchPscPciVal=getProp(cell,['PHYCELLID','PCI']); }
        else if (cell._tech === '5G') { lacTacLabel='TAC'; lacTacVal=getProp(cell,['TAC_ATOLL','TAC']); bcchPscPciLabel='PCI'; bcchPscPciVal=getProp(cell,['PHYSICALCELLID','PCI']); }
        const antenna = getProp(cell, ['ANTENNA', 'Antenna', 'Ant_Type']);
        return `<table class="popup-table"><tr><th>Site</th><td>${siteName}</td></tr><tr><th>Cell Name</th><td>${cell._name}</td></tr><tr><th>Band</th><td>${band}</td></tr>${extraRow}<tr><th>Status</th><td><b>${cellStatus}</b></td></tr><tr><th>Antenna</th><td>${antenna}</td></tr><tr><th>Azimuth</th><td>${cell._az}</td></tr><tr><th>Height</th><td>${getProp(cell, ['HEIGHT'])}</td></tr><tr><th>M_TILT</th><td>${getProp(cell, ['M_TILT'])}</td></tr><tr><th>E_TILT</th><td>${getProp(cell, ['E_TILT'])}</td></tr><tr><th>${lacTacLabel}</th><td>${lacTacVal}</td></tr><tr><th>${bcchPscPciLabel}</th><td>${bcchPscPciVal}</td></tr>${bscRncVal ? `<tr><th>${bscRncLabel}</th><td>${bscRncVal}</td></tr>` : ''}<tr><th>DISTRICT</th><td>${district}</td></tr></table>`;
      }
      function createDetailedPlacemarkHTML(cell) {
        const content = buildCellPopupData(cell);
        return `<![CDATA[<style>#c{font-family:Arial,sans-serif;border-collapse:collapse;width:350px}#c td,#c th{border:1px solid #ddd;padding:3px;font-size:10px}#c tr:nth-child(even){background-color:#f2f2f2}#c th{text-align:left;background-color:#e64543;color:white;width:120px}</style>${content.replace('class="popup-table"', 'id="c"')}]]>`;
      }
      function buildSitePopupHtml(data, lat, lon) {
          const getVal = (key) => (data[key] !== undefined && data[key] !== null) ? data[key] : '';
          const svImgUrl = `https://cbk0.google.com/cbk?output=thumbnail&w=400&h=200&ll=${lat},${lon}`;
          const mapsUrl = `https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=${lat},${lon}`;
          return `<div class="pop-cont"><table id="c_site"><tr><th>Site Name</th><td>${getVal('Site')}</td></tr><tr><th>Site ID</th><td>${getVal('SiteID')}</td></tr><tr><th>Site Type</th><td>${getVal('Type')}</td></tr><tr><th>Construction</th><td>${getVal('ConstructionType')}</td></tr><tr><th>Height</th><td>${getVal('ConstructionHeight')}</td></tr><tr><th>Latitude</th><td>${lat}</td></tr><tr><th>Longitude</th><td>${lon}</td></tr><tr><th>Address</th><td>${getVal('Address')}</td></tr><tr><th>District</th><td>${getVal('District')}</td></tr></table><div class="sv-box"><img src="${svImgUrl}" class="sv-img" alt="Preview" onerror="this.style.display='none';this.parentElement.innerHTML='<div style=\\'padding:10px;text-align:center;color:#666\\'>No Preview</div>';"/></div><a href="${mapsUrl}" target="_blank" class="btn-map">Open in Google Maps</a></div>`;
      }
      function createSitePlacemarkHTML(data) {
        const lat = data['Lat']; const lon = data['Lon'];
        const htmlContent = buildSitePopupHtml(data, lat, lon);
        return `<![CDATA[<style>.pop-cont { font-family: Arial, sans-serif; width: 420px; } #c { border-collapse: collapse; width: 100%; margin-bottom: 10px; } #c td, #c th { border: 1px solid #ddd; padding: 3px; font-size: 10px; } #c tr:nth-child(even) { background-color: #f2f2f2; } #c th { text-align: left; background-color: #04AA6D; color: white; width: 100px; } .sv-box { border: 1px solid #ccc; margin-top: 5px; height: 200px; background: #eee; overflow: hidden; display: flex; align-items: center; justify-content: center; } .sv-img { width: 100%; height: 100%; object-fit: cover; } .btn-map { display: block; width: 100%; text-align: center; background: #2196F3; color: white; padding: 8px; text-decoration: none; font-size: 12px; margin-top: 5px; border-radius: 4px; box-sizing: border-box; font-weight: bold; } .btn-map:hover { background: #0b7dda; }</style>${htmlContent.replace('id="c_site"', 'id="c"')}]]>`;
      }

      // --- LOGIC ---
      const analyzeData = async (files, onLog, onProgress) => {
          const onShowyLog = async (messages, delay = 50) => {
            for (const msg of messages) {
                onLog(msg);
                await new Promise(resolve => setTimeout(resolve, delay));
            }
          };
          onLog("Initializing analysis engine...");

          if (!XLSX || !JSZip) throw new Error("Core libraries (XLSX, JSZip) missing!");
          const data = {};
          let filesProcessed = 0;
          const totalFiles = FILES_CONFIG.filter(c => files[c.key]).length;

          await onShowyLog([
            `Found ${totalFiles} files to process.`,
            'Starting data ingestion sequence...'
          ], 100);

          for (const config of FILES_CONFIG) {
            const file = files[config.key];
            if (!file && config.required) {
                onLog(`[WARN]   > Required file ${config.label} is missing!`, 'error');
                throw new Error(`${config.label} is missing!`);
            }
            if (!file) continue;
            
            await onShowyLog([
                `[OPEN]   ${config.label} - ${file.name}`,
                `         > Size: ${Math.round(file.size/1024)} KB, Type: ${file.type}`,
                `[STREAM] > Reading file into memory buffer...`
            ]);
            
            const arrayBuffer = await file.arrayBuffer();
            onLog(`[PARSE]  > Processing workbook...`);
            const workbook = XLSX.read(arrayBuffer, { type: 'array' });
            data[config.key] = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
            
            onLog(`[DONE]   > Ingested ${data[config.key].length} records from ${config.label}.`, 'success');
            filesProcessed++;
            onProgress((filesProcessed / totalFiles) * 30);
          }

          await onShowyLog([
            '-------------------------------------------',
            'All files ingested. Indexing relational data...'
          ], 80);

          onLog("Indexing Main Engineering Database...");
          const engDB = data['ENG_DB'].map((row) => {
            let siteID = row['Site_ID'] || row['SITE_ID'] || (row['Name'] ? 'S'+String(row['Name']).padStart(5,'0') : 'UNKNOWN');
            return { ...row, SiteID: String(siteID).trim() };
          });
          const engLookup = new Map(); engDB.forEach((row) => engLookup.set(row.SiteID, row));
          onLog(` > Indexed ${engLookup.size} unique sites from ENG_DB.`);

          onLog("Indexing URA Information...");
          const uraLookup = new Map();
          if (data['URA']) data['URA'].forEach((row) => {
              let siteID = '';
              if(row['Site_ID']) siteID = String(row['Site_ID']);
              else if(row['OMC_CELLNAME'] && String(row['OMC_CELLNAME']).length > 6) siteID = 'S' + String(row['OMC_CELLNAME']).substring(1, 6);
              else if (row['SITEID_4G']) { const raw = String(row['SITEID_4G']).trim(); if (raw.length >= 5) siteID = 'S' + raw.substring(raw.length - 5); }
              if(siteID) uraLookup.set(siteID.trim(), row['URA']);
          });
          onLog(` > Indexed ${uraLookup.size} URA records.`);
          onProgress(40);

          await onShowyLog([
            '-------------------------------------------',
            'Starting cell data integration...'
          ], 80);

          const techConfigs = [
            { key: 'GSM', nameCol: 'CELLNAME', latCol: 'Lat_Site', lonCol: 'Lon_Site', azCol: 'AZIMUTH', bandCol: null },
            { key: 'UMTS', nameCol: 'CELLNAME', latCol: 'Lat_Site', lonCol: 'Lon_Site', azCol: 'AZIMUTH', bandCol: 'UARFCNDOWNLINK' },
            { key: 'LTE', nameCol: 'CELLNAME', latCol: 'Lat_Site', lonCol: 'Lon_Site', azCol: 'AZIMUTH', bandCol: 'DLEARFCN' },
            { key: '5G', nameCol: 'CELLNAME', latCol: 'Lat_Site', lonCol: 'Lon_Site', azCol: 'AZIMUTH', bandCol: 'FREQUENCYBAND' }
          ];

          let allCells = [];
          const allSites = new Map();
          const citiesSet = new Set();
          const districtsSet = new Set();
          let totalCellCount = 0;

          for (const tech of techConfigs) {
            if (!data[tech.key]) continue;
            onLog(`[MERGE]  > Integrating ${tech.key} cell data...`);
            const rows = data[tech.key].map((row) => {
               const cellName = String(getProp(row, [tech.nameCol, 'CellName', 'Cell', 'Name']) || '');
               let siteID = row['Site_ID'] || row['SiteID'] || row['SITEID'] || (cellName.length > 6 ? 'S'+cellName.substring(1,6) : '');
               siteID = String(siteID).trim();
               if (siteID.startsWith('N')) siteID = 'S' + siteID.substring(1);
               if (siteID.length === 5 && !siteID.startsWith('S')) siteID = 'S' + siteID;

               const engInfo = engLookup.get(siteID) || {};
               const lat = parseFloat(getProp(row, [tech.latCol, 'Lat', 'Latitude']));
               const lon = parseFloat(getProp(row, [tech.lonCol, 'Lon', 'Longitude']));
               const az = parseFloat(getProp(row, [tech.azCol, 'Azimuth']) || '0');

               if (!allSites.has(siteID) && !isNaN(lat) && !isNaN(lon)) {
                  const dist = getProp(row, ['DISTRICT', 'District']) || getProp(engInfo, ['DISTRICT', 'District']);
                  const city = getProp(row, ['CITY', 'City']) || getProp(engInfo, ['CITY', 'City']);
                  let siteName = getProp(engInfo, ['SITE_NAME_SR', 'Site_Name_SR']);
                  if (!siteName) siteName = getProp(row, ['SITE_NAME', 'SiteName']);
                  if (!siteName) siteName = siteID;
                  allSites.set(siteID, { SiteID: siteID, SiteName: siteName, Lat: lat, Lon: lon, City: city, District: dist, DescData: { Site: siteName, SiteID: siteID, Address: getProp(engInfo, ['SITE_ADDRESS', 'Address']), Type: getProp(engInfo, ['SITE_TYPE', 'Type']), ConstructionType: getProp(engInfo, ['CONSTRUCTION_TYPE', 'Construction_Type']), ConstructionHeight: getProp(engInfo, ['CONSTRUCTION_HEIGHT', 'Construction_Height']), District: dist, Lat: lat, Lon: lon }});
               }

               const masterSite = allSites.get(siteID);
               const finalCity = masterSite ? masterSite.City : (getProp(row, ['CITY', 'City']) || getProp(engInfo, ['CITY', 'City']));
               const finalDist = masterSite ? masterSite.District : (getProp(row, ['DISTRICT', 'District']) || getProp(engInfo, ['DISTRICT', 'District']));

               if(finalCity) citiesSet.add(normalizeTR(finalCity));
               if(finalDist) districtsSet.add(normalizeTR(finalDist));

               return { 
                 ...engInfo, ...row, URA: uraLookup.get(siteID) || 'N/A', 
                 SiteID: siteID, _tech: tech.key, 
                 _lat: masterSite ? masterSite.Lat : lat, _lon: masterSite ? masterSite.Lon : lon, 
                 _az: az, _name: cellName, _band: tech.bandCol ? row[tech.bandCol] : null, 
                 _isIndoor: String(cellName).toUpperCase().includes('I'), _innerR: 0, _outerR: 0,
                 City: finalCity, District: finalDist
               };
            }).filter(r => !isNaN(r._lat) && !isNaN(r._lon));
            onLog(`         > Found ${rows.length} valid cells for ${tech.key}.`);
            totalCellCount += rows.length;
            allCells = [...allCells, ...rows];
            onProgress(40 + (techConfigs.indexOf(tech)+1)/techConfigs.length * 20);
          }
          await onShowyLog([
            `[OK]     > Data integration complete.`,
            `         > Total cells processed: ${totalCellCount}`,
            `         > Total unique sites: ${allSites.size}`,
            `         > Total unique cities: ${citiesSet.size}`,
            `         > Total unique districts: ${districtsSet.size}`
          ], 80);

          return { allCells, allSites, cities: Array.from(citiesSet).sort(), districts: Array.from(districtsSet).sort() };
      };

      const generateBatchOutput = async (allCells, allSites, filterType, batches, onLog, onProgress) => {
           const onShowyLog = async (messages, delay = 50) => {
            for (const msg of messages) {
                onLog(msg);
                await new Promise(resolve => setTimeout(resolve, delay));
            }
           };

           onLog("KML Generation process initiated...");
           const results = [];
           const allStats = {};
           let batchCounter = 0;
           onProgress(0);

           for (const batch of batches) {
               batchCounter++;
               const { name, filters, mode } = batch;
               await onShowyLog([
                 '-------------------------------------------',
                 `[START]  Processing batch job ${batchCounter}/${batches.length}: "${name}"`,
                 `[FILTER] > Applying ${mode} filter with ${filters.length} targets...`
               ]);
               
               const filterSet = new Set(filters);
               const filteredSites = [];
               const filteredCells = [];

               for(const site of allSites.values()) {
                  const val = normalizeTR(mode === 'CITY' ? site.City : site.District);
                  if(filterSet.has(val)) filteredSites.push(site);
               }
               for(const cell of allCells) {
                   const val = normalizeTR(mode === 'CITY' ? cell.City : cell.District);
                   if(filterSet.has(val)) filteredCells.push(cell);
               }
               
               onLog(`[INFO]   > Found ${filteredSites.length} matching sites and ${filteredCells.length} cells.`);

               if(filteredSites.length === 0) { onLog(`[WARN]   > Batch ${name} skipped (No Data).`, 'error'); continue; }

               await onShowyLog([
                   '[GEOM]   > Calculating sector geometries...',
                   '         > Analyzing azimuths and beam widths...'
               ]);
               const siteAzimuths = {}, siteAzimuthWidths = {}; 
               filteredCells.forEach(cell => { if (!cell._isIndoor) { if (!siteAzimuths[cell.SiteID]) siteAzimuths[cell.SiteID] = new Set(); siteAzimuths[cell.SiteID].add(Math.round(cell._az)); }});
               Object.entries(siteAzimuths).forEach(([siteID, azSet]) => {
                  const azList = Array.from(azSet).sort((a, b) => a - b);
                  if (azList.length <= 1) { if(azList.length === 1) siteAzimuthWidths[`${siteID}_${azList[0]}`] = DEFAULT_BEAM_WIDTH; return; }
                  for (let i = 0; i < azList.length; i++) {
                    const current = azList[i], prev = azList[(i - 1 + azList.length) % azList.length], next = azList[(i + 1) % azList.length];
                    let distPrev = (current - prev + 360) % 360; if (distPrev === 0) distPrev = 360; 
                    let distNext = (next - current + 360) % 360; if (distNext === 0) distNext = 360;
                    const availableHalf = Math.min(distPrev, distNext) / 2 - (MIN_ANGULAR_GAP / 2);
                    siteAzimuthWidths[`${siteID}_${current}`] = Math.max(1, Math.min(DEFAULT_BEAM_WIDTH, availableHalf * 2));
                  }
               });
               onLog('         > Calculating layer radii for indoor/outdoor cells...');

               const indoorGroups = new Map(), sectorGroups = new Map();
               filteredCells.forEach(cell => {
                 if (cell._isIndoor) { const key = `${cell.SiteID}_${cell._tech}`; if (!indoorGroups.has(key)) indoorGroups.set(key, []); indoorGroups.get(key).push(cell); } 
                 else { const azKey = Math.round(cell._az); const key = `${cell.SiteID}_${azKey}`; if (!sectorGroups.has(key)) sectorGroups.set(key, []); sectorGroups.get(key).push(cell); }
               });
               const advancedSort = (a, b) => {
                    const techDiff = (TECH_PRIORITY[a._tech] || 99) - (TECH_PRIORITY[b._tech] || 99); if (techDiff !== 0) return techDiff;
                    if (a._tech === 'LTE' && b._tech === 'LTE') { const boA = getLteBandOrder(a._band), boB = getLteBandOrder(b._band); if (boA !== boB) return boA - boB; }
                    return a._name.localeCompare(b._name);
               };
               for (const [key, group] of indoorGroups.entries()) {
                   group.sort(advancedSort); 
                   const cell = group[0]; const tech = cell._tech; const priority = TECH_PRIORITY[tech] || 1;
                   const myStartRadius = INDOOR_START_RADIUS + ((priority - 1) * (INDOOR_WIDTH + INDOOR_GAP));
                   const count = group.length; const sliceAngle = 360 / Math.max(1, count);
                   group.forEach((c, index) => {
                      const centerAz = (index * sliceAngle) + (sliceAngle / 2);
                      const gap = count > 1 ? 2 : 0; 
                      const actualBeamWidth = Math.max(1, sliceAngle - gap);
                      c._innerR = myStartRadius; c._outerR = myStartRadius + INDOOR_WIDTH; c._az = centerAz; c._beamWidth = actualBeamWidth; 
                   });
               }
               for (const group of sectorGroups.values()) {
                  if(group.length === 0) continue;
                  let currentRadius = MIN_OUTDOOR_START_RADIUS;
                  group.sort(advancedSort);
                  group.forEach((cell) => { cell._innerR = currentRadius; cell._outerR = currentRadius + OUTDOOR_WIDTH; currentRadius += OUTDOOR_WIDTH + GAP_BETWEEN_LAYERS; });
               }

               onLog('[KML]    > Building KML structure for ' + name);
               const placemarksByFolder = { Site: {}, GSM: {}, UMTS: {}, LTE: {}, '5G': {} };
               const usedColors = new Set();
               for (const site of filteredSites) {
                   const groupKey = normalizeTR(mode === 'CITY' ? site.City : site.District) || 'Unknown';
                   if (!placemarksByFolder['Site'][groupKey]) placemarksByFolder['Site'][groupKey] = [];
                   placemarksByFolder['Site'][groupKey].push(`<Placemark><name>${site.SiteName}</name><description>${createSitePlacemarkHTML(site.DescData)}</description><styleUrl>#site_style</styleUrl><Point><coordinates>${site.Lon.toFixed(6)},${site.Lat.toFixed(6)},0</coordinates></Point></Placemark>`);
               }
               for(const cell of filteredCells) {
                   let color = getBandColor(cell._tech, cell._band); usedColors.add(color);
                   let coordsArray = [];
                   if (cell._isIndoor) { const bw = cell._beamWidth || 360; coordsArray = generateSectorPolygon(cell._lat, cell._lon, cell._az, bw, cell._innerR, cell._outerR); } 
                   else { const azKey = `${cell.SiteID}_${Math.round(cell._az)}`; const currentBeamWidth = siteAzimuthWidths[azKey] || DEFAULT_BEAM_WIDTH; coordsArray = generateSectorPolygon(cell._lat, cell._lon, cell._az, currentBeamWidth, cell._innerR, cell._outerR); }
                   const coordsStr = coordsToKml(coordsArray);
                   let geometryXml = `<Polygon><tessellate>1</tessellate><altitudeMode>clampToGround</altitudeMode><outerBoundaryIs><LinearRing><coordinates>${coordsStr}</coordinates></LinearRing></outerBoundaryIs></Polygon>`;
                   const groupKey = normalizeTR(mode === 'CITY' ? cell.City : cell.District) || 'Unknown';
                   if (placemarksByFolder[cell._tech]) {
                       if (!placemarksByFolder[cell._tech][groupKey]) placemarksByFolder[cell._tech][groupKey] = [];
                       placemarksByFolder[cell._tech][groupKey].push(`<Placemark><name>${cell._name}</name><description>${createDetailedPlacemarkHTML(cell)}</description><styleUrl>#style_${color}</styleUrl>${geometryXml}</Placemark>`);
                   }
                   
                   const c = normalizeTR(cell.City || 'Unknown');
                   const d = normalizeTR(cell.District || 'Unknown');
                   const statKey = `${c}_${d}`;
                   
                   if(!allStats[statKey]) { 
                       allStats[statKey] = { 
                           city: c, 
                           dist: d,
                           GSM: { sites: new Set(), act: 0, deact: 0 }, 
                           UMTS: { sites: new Set(), act: 0, deact: 0 }, 
                           LTE: { sites: new Set(), act: 0, deact: 0 }, 
                           '5G': { sites: new Set(), act: 0, deact: 0 } 
                       }; 
                   }
                   const t = allStats[statKey][cell._tech];
                   if(t) {
                       t.sites.add(cell.SiteID);
                       let isAct = false;
                       if(cell._tech === 'GSM') isAct = true;
                       else if(cell._tech === 'UMTS') isAct = (getProp(cell, ['ACTSTATUS', 'ActStatus', 'Status']) === 'ACTIVATED');
                       else isAct = (getProp(cell, ['CELLACTIVESTATE', 'CellActiveState', 'ActiveStatus']) === 'CELL_ACTIVE');
                       if(isAct) t.act++; else t.deact++;
                   }
               }
               
               await onShowyLog([
                 `[ZIP]    > Compressing KML data into KMZ archive for "${name}"...`,
                 `[ZIP]    > Compression level: 6 (DEFLATE)`
               ]);
               const zip = new JSZip();
               const kmlString = generateKMLString(name, placemarksByFolder, usedColors);
               zip.file(`${name}.kml`, kmlString);
               const blobKmz = await zip.generateAsync({ type: "blob", compression: "DEFLATE", compressionOptions: { level: 6 } });
               results.push({ name: `${name}.kmz`, blob: blobKmz });
               onLog(`[OK]     > Batch "${name}" completed successfully.`, 'success');
               onProgress(60 + (batchCounter / batches.length) * 40);
           }

           onProgress(100);
           return { results, stats: Object.values(allStats).sort((a,b) => a.city.localeCompare(b.city) || a.dist.localeCompare(b.dist)) };
      };

      // --- React Components ---
      const FileUploadCard = ({ config, file, onChange }) => {
        const Icon = config.icon;
        const inputRef = useRef(null);
        return (
          <div onClick={() => inputRef.current?.click()} className={`relative group cursor-pointer transition-all duration-300 border-2 rounded-xl p-4 flex flex-col items-center justify-center gap-3 bg-white hover:shadow-lg hover:-translate-y-1 ${file ? 'border-emerald-400 bg-emerald-50/40' : config.required ? 'border-dashed border-gray-300 hover:border-blue-400' : 'border-dashed border-gray-200 hover:border-gray-400 opacity-80'}`}>
            <input ref={inputRef} type="file" accept=".xls,.xlsx,.csv,.txt" onChange={onChange} className="hidden" />
            <div className={`p-3 rounded-full transition-colors ${file ? 'bg-emerald-100' : 'bg-gray-100 group-hover:bg-blue-50'}`}>
              {file ? <Icons.CheckCircle2 className="w-6 h-6 text-emerald-600" /> : <Icon className={`w-6 h-6 ${file ? 'text-emerald-600' : config.color}`} />}
            </div>
            <div className="text-center">
              <h3 className={`font-semibold text-sm ${file ? 'text-emerald-800' : 'text-gray-700'}`}>{config.label}</h3>
              {file ? <p className="text-xs text-emerald-600 font-medium truncate max-w-[140px] mt-1">{file.name}</p> : <p className="text-xs text-gray-400 mt-1">{config.sub} {config.required && <span className="text-red-400 ml-1">*Required</span>}</p>}
            </div>
          </div>
        );
      };

      const ResultCard = ({ file }) => (
        <a href={URL.createObjectURL(file.blob)} download={file.name} className="flex flex-col items-center p-4 bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md hover:border-blue-300 transition-all duration-300 group">
          <div className="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
            <Icons.Download className="w-6 h-6 text-blue-600" />
          </div>
          <span className="font-bold text-gray-800 mb-1 truncate w-full text-center">{file.name}</span>
          <span className="text-xs text-gray-500">Click to Download</span>
        </a>
      );

      const ConfigPanel = ({ cities, districts, onGenerate, onBack }) => {
          const [mode, setMode] = useState('CITY'); 
          const [search, setSearch] = useState("");
          const [batchName, setBatchName] = useState("My_Region");
          const [selection, setSelection] = useState(new Set());
          const [batches, setBatches] = useState([]);

          const list = mode === 'CITY' ? cities : districts;
          const filteredList = list.filter(item => item.includes(search.toUpperCase()));
          
          const availableCities = useMemo(() => new Set(cities.map(normalizeTR)), [cities]);
          const availableDistricts = useMemo(() => new Set(districts.map(normalizeTR)), [districts]);

          useEffect(() => {
              if(batches.length === 0) setSelection(new Set(list));
              else setSelection(new Set());
          }, [mode, list]);

          const toggleItem = (item) => {
              const newSet = new Set(selection);
              if (newSet.has(item)) newSet.delete(item); else newSet.add(item);
              setSelection(newSet);
          };

          const toggleAll = (select) => {
              if (select) setSelection(new Set(filteredList)); else setSelection(new Set());
          };
          
          const isTemplateAvailable = (templateName, type) => {
              const targets = type === 'DISTRICT' ? DISTRICT_TEMPLATES[templateName] : CITY_TEMPLATES[templateName];
              if(!targets) return false;
              return targets.some(t => {
                  const normT = normalizeTR(t);
                  return type === 'DISTRICT' ? availableDistricts.has(normT) : availableCities.has(normT);
              });
          };

          const applyTemplate = (templateName, type) => {
              if(mode !== type) setMode(type);
              const targets = type === 'DISTRICT' ? DISTRICT_TEMPLATES[templateName] : CITY_TEMPLATES[templateName];
              if(!targets) return;
              const newSet = new Set(selection);
              let foundCount = 0;
              targets.forEach(t => {
                  const normT = normalizeTR(t);
                  const exists = type === 'DISTRICT' ? availableDistricts.has(normT) : availableCities.has(normT);
                  if (exists) { newSet.add(normT); foundCount++; }
              });
              if(foundCount === 0) { alert("No matching data found for this template in uploaded files."); return; }
              setSelection(newSet);
              setBatchName(templateName.replace(/\s+/g, '_'));
          };

          const addBatch = () => {
              if (selection.size === 0) { alert("Please select at least one region."); return; }
              if (!batchName) { alert("Please enter a name for this KMZ file."); return; }
              setBatches([...batches, { name: batchName, filters: Array.from(selection), mode }]);
              setSelection(new Set()); setBatchName("");
          };

          const removeBatch = (index) => {
              const newBatches = [...batches];
              newBatches.splice(index, 1);
              setBatches(newBatches);
          };

          return (
              <div className="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 md:p-8 flex flex-col md:flex-row gap-6">
                  <div className="flex-1 flex flex-col">
                      <div className="flex items-center justify-between mb-4 border-b pb-2">
                         <h2 className="text-lg font-bold flex items-center gap-2 text-gray-800"><Icons.Filter className="w-5 h-5 text-blue-600" />Pool Selection</h2>
                         <div className="flex gap-2">
                             <button onClick={() => { setMode('CITY'); }} className={`px-3 py-1 rounded text-xs font-bold border ${mode === 'CITY' ? 'bg-blue-600 text-white' : 'bg-gray-100'}`}>City Mode</button>
                             <button onClick={() => { setMode('DISTRICT'); }} className={`px-3 py-1 rounded text-xs font-bold border ${mode === 'DISTRICT' ? 'bg-blue-600 text-white' : 'bg-gray-100'}`}>District Mode</button>
                         </div>
                      </div>

                      <div className="mb-4">
                          <span className="text-xs font-bold text-gray-500 block mb-1">Quick Templates:</span>
                          <div className="flex flex-wrap gap-2 max-h-24 overflow-y-auto scrollbar-thin p-1">
                              {mode === 'DISTRICT' && Object.keys(DISTRICT_TEMPLATES).map(t => {
                                  const available = isTemplateAvailable(t, 'DISTRICT');
                                  return (
                                      <button key={t} onClick={() => available && applyTemplate(t, 'DISTRICT')} disabled={!available} className={`px-2 py-1 text-[10px] rounded font-bold border ${available ? 'bg-indigo-100 hover:bg-indigo-200 text-indigo-700 border-indigo-200' : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed opacity-60'}`}>{t}</button>
                                  )
                              })}
                              {mode === 'CITY' && Object.keys(CITY_TEMPLATES).map(t => {
                                  const available = isTemplateAvailable(t, 'CITY');
                                  return (
                                      <button key={t} onClick={() => available && applyTemplate(t, 'CITY')} disabled={!available} className={`px-2 py-1 text-[10px] rounded font-bold border ${available ? 'bg-teal-100 hover:bg-teal-200 text-teal-700 border-teal-200' : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed opacity-60'}`}>{t}</button>
                                  )
                              })}
                          </div>
                      </div>

                      <div className="flex gap-2 mb-2">
                          <input type="text" placeholder="Search..." className="flex-1 px-3 py-1 text-sm border rounded" value={search} onChange={e => setSearch(e.target.value)} />
                          <button onClick={() => toggleAll(true)} className="text-xs text-blue-600 font-bold hover:underline">All</button>
                          <button onClick={() => toggleAll(false)} className="text-xs text-blue-600 font-bold hover:underline">None</button>
                      </div>

                      <div className="border rounded-lg h-64 overflow-y-auto p-2 bg-gray-50 grid grid-cols-2 gap-1 content-start scrollbar-thin">
                          {filteredList.map(item => (
                              <label key={item} className={`flex items-center gap-2 cursor-pointer p-1 rounded ${selection.has(item) ? 'bg-blue-100' : 'hover:bg-gray-100'}`}>
                                  <input type="checkbox" checked={selection.has(item)} onChange={() => toggleItem(item)} className="rounded text-blue-600" />
                                  <span className="text-xs font-medium text-gray-700 truncate" title={item}>{item}</span>
                              </label>
                          ))}
                      </div>
                  </div>

                  <div className="w-full md:w-12 flex md:flex-col items-center justify-center gap-2">
                       <div className="text-gray-300 text-3xl hidden md:block">➔</div>
                       <div className="text-gray-300 text-3xl md:hidden">⬇</div>
                  </div>

                  <div className="flex-1 flex flex-col bg-gray-50 rounded-xl border border-gray-200 p-4">
                      <h3 className="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2"><Icons.Layers className="w-4 h-4" />Output Queue</h3>
                      <div className="mb-4">
                          <label className="block text-xs font-bold text-gray-500 mb-1">KMZ Filename</label>
                          <div className="flex gap-2">
                              <input type="text" value={batchName} onChange={(e) => setBatchName(e.target.value)} className="flex-1 px-3 py-2 border rounded-lg text-sm" placeholder="e.g. Region_1" />
                              <button onClick={addBatch} className="bg-emerald-500 hover:bg-emerald-600 text-white p-2 rounded-lg font-bold text-xs uppercase flex items-center gap-1"><Icons.Plus className="w-4 h-4" /> Add</button>
                          </div>
                          <div className="text-xs text-gray-400 mt-1">{selection.size} items selected</div>
                      </div>
                      <div className="flex-1 overflow-y-auto space-y-2 mb-4 scrollbar-thin">
                          {batches.length === 0 && <div className="text-center text-gray-400 text-xs mt-10">No batches added yet.</div>}
                          {batches.map((batch, idx) => (
                              <div key={idx} className="bg-white p-3 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center group">
                                  <div>
                                      <div className="font-bold text-sm text-gray-800">{batch.name}.kmz</div>
                                      <div className="text-xs text-gray-500">{batch.filters.length} {batch.mode.toLowerCase()}s included</div>
                                  </div>
                                  <button onClick={() => removeBatch(idx)} className="text-gray-400 hover:text-red-500"><Icons.Trash2 className="w-4 h-4" /></button>
                              </div>
                          ))}
                      </div>
                      <button onClick={() => onGenerate(batches)} disabled={batches.length === 0} className={`w-full py-3 rounded-xl font-bold text-white shadow-lg transition-all ${batches.length > 0 ? 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:-translate-y-0.5' : 'bg-gray-300 cursor-not-allowed'}`}>
                          GENERATE ALL ({batches.length})
                      </button>
                      <button onClick={onBack} className="w-full mt-2 py-2 text-xs text-gray-500 hover:underline">Back to Upload</button>
                  </div>
              </div>
          );
      };

      const StatisticsTable = ({ stats }) => {
        if (!stats || stats.length === 0) return null;
        const totals = { GSM: {s:0,a:0,d:0}, UMTS: {s:0,a:0,d:0}, LTE: {s:0,a:0,d:0}, '5G': {s:0,a:0,d:0} };
        stats.forEach(r => {
           totals.GSM.s += r.GSM.sites.size; totals.GSM.a += r.GSM.act; totals.GSM.d += r.GSM.deact;
           totals.UMTS.s += r.UMTS.sites.size; totals.UMTS.a += r.UMTS.act; totals.UMTS.d += r.UMTS.deact;
           totals.LTE.s += r.LTE.sites.size; totals.LTE.a += r.LTE.act; totals.LTE.d += r.LTE.deact;
           totals['5G'].s += r['5G'].sites.size; totals['5G'].a += r['5G'].act; totals['5G'].d += r['5G'].deact;
        });
        const downloadStatsCSV = () => {
          const header = ["City", "District", "GSM Sites", "GSM Active", "GSM Deactive", "UMTS Sites", "UMTS Active", "UMTS Deactive", "LTE Sites", "LTE Active", "LTE Deactive", "5G Sites", "5G Active", "5G Deactive"].join(",");
          const body = stats.map(row => [ `"${row.city}"`, `"${row.dist}"`, row.GSM.sites.size, row.GSM.act, row.GSM.deact, row.UMTS.sites.size, row.UMTS.act, row.UMTS.deact, row.LTE.sites.size, row.LTE.act, row.LTE.deact, row['5G'].sites.size, row['5G'].act, row['5G'].deact ].join(",")).join("\n");
          const footer = ["GRAND TOTAL", "", totals.GSM.s, totals.GSM.a, totals.GSM.d, totals.UMTS.s, totals.UMTS.a, totals.UMTS.d, totals.LTE.s, totals.LTE.a, totals.LTE.d, totals['5G'].s, totals['5G'].a, totals['5G'].d].join(",");
          const csvContent = "\uFEFF" + header + "\n" + body + "\n" + footer;
          const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
          const url = URL.createObjectURL(blob);
          const link = document.createElement("a");
          link.setAttribute("href", url);
          link.setAttribute("download", "Network_Statistics_Report.csv");
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
        };
        return (
          <div className="bg-slate-800 rounded-2xl shadow-xl border border-slate-700 overflow-hidden mt-4">
            <div className="p-3 bg-slate-900 border-b border-slate-700 flex items-center justify-between">
               <span className="text-slate-400 font-mono text-xs flex items-center gap-2"><Icons.BarChart className="w-4 h-4" />NETWORK STATISTICS</span>
               <button onClick={downloadStatsCSV} className="flex items-center gap-2 bg-slate-700 hover:bg-slate-600 text-xs text-white px-3 py-1.5 rounded transition-colors border border-slate-600"><Icons.Download className="w-3 h-3" /> Export CSV</button>
            </div>
            <div className="overflow-x-auto max-h-[500px] scrollbar-thin">
              <table className="w-full text-xs text-left text-slate-300">
                <thead className="text-xs text-slate-400 uppercase bg-slate-900 sticky top-0 z-10">
                  <tr><th rowSpan="2" className="px-3 py-2 border-b border-r border-gray-700">Location</th><th colSpan="2" className="stat-header text-green-500 border-r border-gray-700">GSM (2G)</th><th colSpan="2" className="stat-header text-orange-500 border-r border-gray-700">UMTS (3G)</th><th colSpan="2" className="stat-header text-blue-500 border-r border-gray-700">LTE (4G)</th><th colSpan="2" className="stat-header text-purple-500">NR (5G)</th></tr>
                  <tr><th className="stat-cell border-r border-gray-700 bg-slate-800">Sites</th><th className="stat-cell border-r border-gray-700 bg-slate-800">Cell (Act/Deact)</th><th className="stat-cell border-r border-gray-700 bg-slate-800">Sites</th><th className="stat-cell border-r border-gray-700 bg-slate-800">Cell (Act/Deact)</th><th className="stat-cell border-r border-gray-700 bg-slate-800">Sites</th><th className="stat-cell border-r border-gray-700 bg-slate-800">Cell (Act/Deact)</th><th className="stat-cell border-r border-gray-700 bg-slate-800">Sites</th><th className="stat-cell bg-slate-800">Cell (Act/Deact)</th></tr>
                </thead>
                <tbody>
                  {stats.map((row, i) => (
                    <tr key={i} className="border-b border-slate-700 hover:bg-slate-700/50 transition-colors">
                      <td className="px-3 py-2 font-medium border-r border-slate-700 truncate max-w-[150px]">{row.city} / {row.dist}</td>
                      <td className="stat-cell border-r border-slate-700">{row.GSM.sites.size || '-'}</td>
                      <td className="stat-cell border-r border-slate-700">{row.GSM.act + row.GSM.deact > 0 ? `${row.GSM.act} / ${row.GSM.deact}` : '-'}</td>
                      <td className="stat-cell border-r border-slate-700">{row.UMTS.sites.size || '-'}</td>
                      <td className="stat-cell border-r border-slate-700">{row.UMTS.act + row.UMTS.deact > 0 ? `${row.UMTS.act} / ${row.UMTS.deact}` : '-'}</td>
                      <td className="stat-cell border-r border-slate-700">{row.LTE.sites.size || '-'}</td>
                      <td className="stat-cell border-r border-slate-700">{row.LTE.act + row.LTE.deact > 0 ? `${row.LTE.act} / ${row.LTE.deact}` : '-'}</td>
                      <td className="stat-cell border-r border-slate-700">{row['5G'].sites.size || '-'}</td>
                      <td className="stat-cell">{row['5G'].act + row['5G'].deact > 0 ? `${row['5G'].act} / ${row['5G'].deact}` : '-'}</td>
                    </tr>
                  ))}
                </tbody>
                <tfoot className="bg-slate-900 sticky bottom-0 font-bold text-white shadow-lg">
                   <tr><td className="px-3 py-3 border-r border-slate-600">GRAND TOTAL</td><td className="stat-cell border-r border-slate-600">{totals.GSM.s}</td><td className="stat-cell border-r border-slate-600">{totals.GSM.a} / {totals.GSM.d}</td><td className="stat-cell border-r border-slate-600">{totals.UMTS.s}</td><td className="stat-cell border-r border-slate-600">{totals.UMTS.a} / {totals.UMTS.d}</td><td className="stat-cell border-r border-slate-600">{totals.LTE.s}</td><td className="stat-cell border-r border-slate-600">{totals.LTE.a} / {totals.LTE.d}</td><td className="stat-cell border-r border-slate-600">{totals['5G'].s}</td><td className="stat-cell">{totals['5G'].a} / {totals['5G'].d}</td></tr>
                </tfoot>
              </table>
            </div>
          </div>
        );
      };

      function App() {
        const [files, setFiles] = useState({});
        const [logs, setLogs] = useState([]);
        const [step, setStep] = useState('UPLOAD'); 
        const [progress, setProgress] = useState(0);
        const [analyzedData, setAnalyzedData] = useState(null); 
        const [generatedFiles, setGeneratedFiles] = useState([]);
        const [statsResult, setStatsResult] = useState(null);
        const logsEndRef = useRef(null);

        const addLog = (msg, type = 'info') => {
           const time = new Date().toLocaleTimeString([], {hour12:false, hour:'2-digit', minute:'2-digit', second:'2-digit'});
           setLogs(prev => [...prev.slice(-150), { text: msg, time, type }]);
        };
        useEffect(() => { if (logsEndRef.current) logsEndRef.current.scrollIntoView({ behavior: "smooth" }); }, [logs]);
        const handleFileChange = (key, e) => { if (e.target.files?.[0]) setFiles(prev => ({ ...prev, [key]: e.target.files[0] })); };

        const handleAnalyze = async () => {
           setStep('PROCESSING'); setProgress(0); addLog("Starting analysis...");
           setTimeout(async () => {
             try {
                const res = await analyzeData(files, (msg)=>addLog(msg), (p)=>setProgress(p));
                setAnalyzedData(res);
                addLog("Analysis complete. Configure your export.", 'success');
                setStep('CONFIG');
             } catch(e) {
                console.error(e); addLog(`Error: ${e.message}`, 'error'); setStep('UPLOAD');
             }
           }, 100);
        };

        const handleGenerate = async (batches) => {
           setStep('PROCESSING'); setProgress(0); addLog(`Processing ${batches.length} batch(es)...`);
           setTimeout(async () => {
              try {
                 const res = await generateBatchOutput(analyzedData.allCells, analyzedData.allSites, null, batches, (msg)=>addLog(msg), (p)=>setProgress(p));
                 setGeneratedFiles(res.results);
                 setStatsResult(res.stats);
                 setStep('DONE');
              } catch(e) {
                 console.error(e); addLog(`Error: ${e.message}`, 'error'); setStep('CONFIG');
              }
           }, 100);
        };

        return (
          <div className="min-h-screen p-6 md:p-12 font-sans animate-fade-in">
            <div className="max-w-7xl mx-auto flex flex-col gap-4">
              <header className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-300">
                <div>
                  <div className="flex items-center gap-3 mb-2">
                    <div className="p-3 bg-blue-600 rounded-lg shadow-lg shadow-blue-500/20"><Icons.Map className="w-8 h-8 text-white" /></div>
                    <div><h1 className="text-3xl font-extrabold tracking-tight text-gray-800">Vodafone RF Network Map Generator</h1><p className="text-gray-500 font-medium">Istanbul RF Planning & Optimization Team</p></div>
                  </div>
                </div>
                <div className="hidden md:block"><div className="inline-flex items-center gap-2 px-4 py-2 bg-white rounded-full text-sm font-semibold shadow-sm text-gray-600 border border-gray-300"><span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>Ready to Use</div></div>
              </header>
              
              <main className="flex flex-col gap-4">
                {step === 'UPLOAD' && (
                    <section className="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 md:p-8 animate-fade-in">
                        <div className="flex items-center justify-between mb-6"><h2 className="text-xl font-bold flex items-center gap-2 text-gray-800"><Icons.UploadCloud className="w-5 h-5 text-blue-600" />Data Sources</h2></div>
                        <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        {FILES_CONFIG.map((conf) => <FileUploadCard key={conf.key} config={conf} file={files[conf.key]} onChange={(e) => handleFileChange(conf.key, e)} />)}
                        </div>
                        <div className="mt-8">
                        <button onClick={handleAnalyze} className="w-full py-4 rounded-xl font-bold text-lg shadow-lg transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700">Load & Analyze Data</button>
                        </div>
                    </section>
                )}

                {/* Keep Config Panel visible always after analyze */}
                {analyzedData && (
                    <ConfigPanel cities={analyzedData.cities} districts={analyzedData.districts} onGenerate={handleGenerate} onBack={() => { setStep('UPLOAD'); setAnalyzedData(null); }} />
                )}

                {(step === 'PROCESSING' || step === 'DONE') && (
                    <section>
                    <div className="bg-slate-900 rounded-2xl shadow-2xl overflow-hidden flex flex-col border border-slate-700" style={{ height: '600px', maxHeight: '600px' }}>
                        <div className="p-3 bg-slate-800 border-b border-slate-700 flex items-center justify-between">
                        <span className="text-slate-400 font-mono text-xs flex items-center gap-2"><Icons.Terminal className="w-4 h-4" />CONSOLE OUTPUT</span>
                        </div>
                        {step === 'PROCESSING' && <div className="h-0.5 w-full bg-slate-800"><div className="h-full bg-gradient-to-r from-blue-500 to-emerald-500 transition-all duration-300 ease-out" style={{ width: `${progress}%` }} /></div>}
                        <div className="flex-1 p-4 font-mono text-[11px] text-slate-300 overflow-y-auto space-y-1 scrollbar-thin scrollbar-thumb-slate-600 tracking-wide leading-relaxed">
                        {logs.map((log, i) => <div key={i} className={`flex gap-3 border-l-2 pl-3 ${log.type === 'error' ? 'border-red-500 bg-red-900/10' : log.type === 'success' ? 'border-emerald-500 bg-emerald-900/10' : 'border-slate-700 hover:bg-slate-800/50'}`}><span className="text-slate-500 min-w-[70px]">{log.time}</span><span className={log.type === 'error' ? 'text-red-400 font-bold' : log.type === 'success' ? 'text-emerald-400 font-bold' : 'text-blue-200'}>{log.text}</span></div>)}
                        <div ref={logsEndRef} />
                        </div>
                    </div>
                    </section>
                )}

                {step === 'DONE' && (
                    <>
                        <StatisticsTable stats={statsResult} />
                        {generatedFiles.length > 0 && (
                            <section className="bg-white rounded-2xl shadow-xl p-6 border border-gray-200 animate-fade-in mt-4">
                                <h3 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2"><Icons.FileCheck className="w-5 h-5 text-emerald-600" />Generated Results</h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    {generatedFiles.map((file, i) => <ResultCard key={i} file={file} />)}
                                </div>
                            </section>
                        )}
                    </>
                )}
              </main>
            </div>
          </div>
        );
      }

      const root = createRoot(document.getElementById('root'));
      root.render(<App />);
    </script>
  </body>
</html>
