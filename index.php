<?php
session_start();
$basePath = '/home/ubuntu/mcserver';
$allowedWritable = ['worlds', 'server.properties'];
$uploadMaxSize = 3 * 1024 * 1024 * 1024;
$username = 'koba';
$password = 'console';
$screenName = '17359.koyasmp';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ?');
    exit;
}

if (!isset($_SESSION['auth'])) {
    if (isset($_POST['u']) && isset($_POST['p'])) {
        if ($_POST['u'] === $username && $_POST['p'] === $password) {
            $_SESSION['auth'] = true;
            header('Location: ?');
            exit;
        } else {
            $error = 'Invalid credentials';
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Console Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                background: #FF6B6B;
                font-family: 'Courier New', monospace;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                border: 4px solid #000;
            }
            .login-box {
                background: #FFE66D;
                border: 4px solid #000;
                padding: 40px;
                box-shadow: 8px 8px 0 #000;
                max-width: 400px;
                width: 90%;
            }
            h1 {
                font-size: 28px;
                margin-bottom: 20px;
                text-transform: uppercase;
                letter-spacing: 2px;
                border-bottom: 4px solid #000;
                padding-bottom: 10px;
            }
            input {
                width: 100%;
                padding: 12px;
                margin: 10px 0;
                border: 3px solid #000;
                font-family: inherit;
                font-size: 16px;
                background: #fff;
                box-shadow: 4px 4px 0 #000;
            }
            button {
                width: 100%;
                padding: 15px;
                background: #4ECDC4;
                border: 3px solid #000;
                font-family: inherit;
                font-size: 18px;
                font-weight: bold;
                cursor: pointer;
                box-shadow: 4px 4px 0 #000;
                text-transform: uppercase;
                margin-top: 10px;
            }
            button:hover {
                transform: translate(2px, 2px);
                box-shadow: 2px 2px 0 #000;
            }
            button:active {
                transform: translate(4px, 4px);
                box-shadow: 0 0 0 #000;
            }
            .error {
                background: #FF6B6B;
                border: 3px solid #000;
                padding: 10px;
                margin-bottom: 15px;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>> CONSOLE_</h1>
            <?php if (isset($error)): ?>
                <div class="error">! <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="u" placeholder="Username" required autofocus>
                <input type="password" name="p" placeholder="Password" required>
                <button type="submit">LOGIN</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function isWritable($path, $basePath, $allowed) {
    $relPath = str_replace($basePath . '/', '', $path);
    $relPath = str_replace($basePath, '', $relPath);
    
    if ($relPath === 'server.properties' || $relPath === '/server.properties') return true;
    if (strpos($relPath, 'worlds') === 0 || $relPath === 'worlds') return true;
    
    foreach ($allowed as $a) {
        if (strpos($relPath, $a) === 0 || $relPath === $a) return true;
    }
    return false;
}

function getDirSize($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : getDirSize($each);
    }
    return $size;
}

$currentDir = isset($_GET['dir']) ? $_GET['dir'] : '';
$fullPath = realpath($basePath . '/' . $currentDir);

if (strpos($fullPath, realpath($basePath)) !== 0) {
    $fullPath = realpath($basePath);
    $currentDir = '';
}

$message = '';
$error = '';

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'stop_server':
            exec('screen -S ' . escapeshellarg($screenName) . ' -p 0 -X stuff "stop$(printf \\\\r)"');
            $message = 'Stop command sent to server';
            break;
            
        case 'start_server':
            exec('screen -S ' . escapeshellarg($screenName) . ' -p 0 -X stuff "cd /home/ubuntu/mcserver && LD_LIBRARY_PATH=. ./bedrock_server$(printf \\\\r)"');
            $message = 'Start command sent to server';
            break;
            
        case 'upload':
            if (!isWritable($fullPath, $basePath, $allowedWritable)) {
                $error = 'Permission Denied: Cannot upload to this directory';
            } else {
                if (isset($_FILES['file'])) {
                    $file = $_FILES['file'];
                    if ($file['size'] > $uploadMaxSize) {
                        $error = 'File too large (Max 3GB)';
                    } else {
                        $dest = $fullPath . '/' . basename($file['name']);
                        if (move_uploaded_file($file['tmp_name'], $dest)) {
                            $message = 'File uploaded: ' . basename($file['name']);
                        } else {
                            $error = 'Upload failed';
                        }
                    }
                }
            }
            break;
            
        case 'mkdir':
            if (!isWritable($fullPath, $basePath, $allowedWritable)) {
                $error = 'Permission Denied';
            } else {
                $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $_POST['name']);
                if ($name && mkdir($fullPath . '/' . $name)) {
                    $message = 'Folder created: ' . $name;
                } else {
                    $error = 'Failed to create folder';
                }
            }
            break;
            
        case 'mkfile':
            if (!isWritable($fullPath, $basePath, $allowedWritable)) {
                $error = 'Permission Denied';
            } else {
                $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $_POST['name']);
                if ($name && file_put_contents($fullPath . '/' . $name, '') !== false) {
                    $message = 'File created: ' . $name;
                } else {
                    $error = 'Failed to create file';
                }
            }
            break;
            
        case 'delete':
            $target = $_POST['target'];
            $targetPath = realpath($basePath . '/' . $target);
            if (strpos($targetPath, realpath($basePath)) === 0) {
                if (!isWritable($targetPath, $basePath, $allowedWritable)) {
                    $error = 'Permission Denied: Cannot delete this item';
                } else {
                    if (is_dir($targetPath)) {
                        function rrmdir($dir) {
                            if (is_dir($dir)) {
                                $objects = scandir($dir);
                                foreach ($objects as $object) {
                                    if ($object != "." && $object != "..") {
                                        if (is_dir($dir . "/" . $object)) rrmdir($dir . "/" . $object);
                                        else unlink($dir . "/" . $object);
                                    }
                                }
                                rmdir($dir);
                            }
                        }
                        rrmdir($targetPath);
                        $message = 'Folder deleted';
                    } else {
                        unlink($targetPath);
                        $message = 'File deleted';
                    }
                }
            }
            break;
            
        case 'rename':
            $old = realpath($basePath . '/' . $_POST['old']);
            $new = dirname($old) . '/' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $_POST['new']);
            if (!isWritable($old, $basePath, $allowedWritable)) {
                $error = 'Permission Denied';
            } else {
                if (rename($old, $new)) {
                    $message = 'Renamed successfully';
                } else {
                    $error = 'Rename failed';
                }
            }
            break;
            
        case 'save':
            $file = realpath($basePath . '/' . $_POST['file']);
            if (strpos($file, realpath($basePath)) === 0) {
                if (!isWritable($file, $basePath, $allowedWritable)) {
                    $error = 'Permission Denied: Cannot edit this file';
                } else {
                    file_put_contents($file, $_POST['content']);
                    $message = 'File saved';
                }
            }
            break;
            
        case 'unzip':
            $file = realpath($basePath . '/' . $_POST['file']);
            $dest = dirname($file);
            
            if (!isWritable($dest, $basePath, $allowedWritable)) {
                $error = 'Permission Denied: Cannot extract here';
            } else {
                $zip = new ZipArchive;
                if ($zip->open($file) === TRUE) {
                    $totalSize = 0;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $totalSize += $zip->statIndex($i)['size'];
                    }
                    
                    if ($totalSize > $uploadMaxSize * 3) {
                        $error = 'Zip Bomb detected! Extracted size too large.';
                    } else {
                        $zip->extractTo($dest);
                        $message = 'Extracted successfully';
                    }
                    $zip->close();
                } else {
                    $error = 'Failed to extract';
                }
            }
            break;
    }
}

if (isset($_GET['download'])) {
    $file = realpath($basePath . '/' . $_GET['download']);
    if (strpos($file, realpath($basePath)) === 0 && is_file($file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

if (isset($_GET['edit'])) {
    $file = realpath($basePath . '/' . $_GET['edit']);
    if (strpos($file, realpath($basePath)) === 0 && is_file($file)) {
        $content = file_get_contents($file);
        $writable = isWritable($file, $basePath, $allowedWritable);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Edit: <?php echo basename($file); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    background: #1a1a2e;
                    font-family: 'Courier New', monospace;
                    color: #eee;
                    padding: 20px;
                }
                .header {
                    background: #FF6B6B;
                    border: 4px solid #000;
                    padding: 15px;
                    margin-bottom: 20px;
                    box-shadow: 6px 6px 0 #000;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                h1 {
                    font-size: 20px;
                    text-transform: uppercase;
                    color: #000;
                }
                .btn {
                    padding: 10px 20px;
                    border: 3px solid #000;
                    font-family: inherit;
                    font-weight: bold;
                    cursor: pointer;
                    box-shadow: 4px 4px 0 #000;
                    text-decoration: none;
                    display: inline-block;
                    font-size: 14px;
                }
                .btn:hover {
                    transform: translate(2px, 2px);
                    box-shadow: 2px 2px 0 #000;
                }
                .btn-back { background: #FFE66D; color: #000; }
                .btn-save { background: #4ECDC4; color: #000; }
                .btn-disabled { background: #95a5a6; cursor: not-allowed; opacity: 0.6; }
                .editor-container {
                    background: #16213e;
                    border: 4px solid #000;
                    box-shadow: 6px 6px 0 #000;
                    padding: 20px;
                }
                textarea {
                    width: 100%;
                    min-height: 70vh;
                    background: #0f3460;
                    color: #e94560;
                    border: 3px solid #000;
                    padding: 15px;
                    font-family: 'Courier New', monospace;
                    font-size: 14px;
                    line-height: 1.6;
                    resize: vertical;
                }
                .readonly-notice {
                    background: #FF6B6B;
                    border: 3px solid #000;
                    padding: 10px;
                    margin-bottom: 15px;
                    font-weight: bold;
                    color: #000;
                    box-shadow: 4px 4px 0 #000;
                }
                .info {
                    background: #FFE66D;
                    border: 3px solid #000;
                    padding: 10px;
                    margin-bottom: 15px;
                    color: #000;
                    font-weight: bold;
                    box-shadow: 4px 4px 0 #000;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>> Editing: <?php echo basename($file); ?></h1>
                <div>
                    <a href="?dir=<?php echo urlencode(dirname(str_replace(realpath($basePath), '', $file))); ?>" class="btn btn-back">BACK</a>
                    <?php if ($writable): ?>
                        <button onclick="saveFile()" class="btn btn-save">SAVE</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!$writable): ?>
                <div class="readonly-notice">! PERMISSION DENIED - READ ONLY MODE</div>
            <?php else: ?>
                <div class="info">Writable - Changes allowed</div>
            <?php endif; ?>
            
            <div class="editor-container">
                <textarea id="editor" <?php echo !$writable ? 'readonly' : ''; ?>><?php echo htmlspecialchars($content); ?></textarea>
            </div>
            
            <?php if ($writable): ?>
            <form id="saveForm" method="POST" style="display:none">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="file" value="<?php echo htmlspecialchars($_GET['edit']); ?>">
                <input type="hidden" name="content" id="content">
            </form>
            <script>
                function saveFile() {
                    document.getElementById('content').value = document.getElementById('editor').value;
                    document.getElementById('saveForm').submit();
                }
            </script>
            <?php endif; ?>
        </body>
        </html>
        <?php
        exit;
    }
}

$files = scandir($fullPath);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MC CONSOLE FILE MANAGER</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f0f0f0;
            font-family: 'Courier New', monospace;
            color: #000;
            line-height: 1.4;
        }
        .header {
            background: #FF6B6B;
            border-bottom: 4px solid #000;
            padding: 20px;
            box-shadow: 0 4px 0 rgba(0,0,0,0.2);
        }
        .header h1 {
            font-size: 24px;
            text-transform: uppercase;
            letter-spacing: 3px;
            text-shadow: 3px 3px 0 #000;
            color: #fff;
        }
        .controls {
            background: #4ECDC4;
            border-bottom: 4px solid #000;
            padding: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .btn {
            padding: 10px 15px;
            border: 3px solid #000;
            font-family: inherit;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 4px 4px 0 #000;
            text-transform: uppercase;
            font-size: 12px;
            background: #FFE66D;
            text-decoration: none;
            color: #000;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn:hover {
            transform: translate(2px, 2px);
            box-shadow: 2px 2px 0 #000;
        }
        .btn:active {
            transform: translate(4px, 4px);
            box-shadow: 0 0 0 #000;
        }
        .btn-danger { background: #FF6B6B; color: #fff; }
        .btn-success { background: #95e1d3; }
        .btn-server { background: #f38181; color: #fff; }
        .btn-start { background: #2ecc71; color: #fff; }
        .path-bar {
            background: #FFE66D;
            border-bottom: 4px solid #000;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 14px;
        }
        .container {
            padding: 20px;
        }
        .message {
            background: #2ecc71;
            border: 3px solid #000;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 4px 4px 0 #000;
            font-weight: bold;
        }
        .error {
            background: #e74c3c;
            color: #fff;
            border: 3px solid #000;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 4px 4px 0 #000;
            font-weight: bold;
        }
        .file-grid {
            background: #fff;
            border: 4px solid #000;
            box-shadow: 6px 6px 0 #000;
        }
        .file-header {
            background: #000;
            color: #fff;
            padding: 12px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 2fr;
            gap: 10px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
        }
        .file-item {
            padding: 12px;
            border-bottom: 3px solid #000;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 2fr;
            gap: 10px;
            align-items: center;
            background: #fff;
        }
        .file-item:nth-child(even) { background: #f8f9fa; }
        .file-item:hover { background: #FFE66D; }
        .file-item:last-child { border-bottom: none; }
        .filename {
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .icon {
            font-size: 20px;
        }
        .size { font-size: 12px; }
        .date { font-size: 12px; }
        .actions { display: flex; gap: 5px; flex-wrap: wrap; }
        .btn-small {
            padding: 5px 10px;
            border: 2px solid #000;
            font-family: inherit;
            font-size: 10px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 2px 2px 0 #000;
            background: #fff;
            text-transform: uppercase;
        }
        .btn-small:hover {
            transform: translate(1px, 1px);
            box-shadow: 1px 1px 0 #000;
        }
        .btn-edit { background: #4ECDC4; }
        .btn-del { background: #FF6B6B; color: #fff; }
        .btn-dl { background: #FFE66D; }
        .btn-unzip { background: #9b59b6; color: #fff; }
        .btn-disabled {
            background: #95a5a6 !important;
            cursor: not-allowed;
            opacity: 0.5;
        }
        .upload-area {
            background: #fff;
            border: 4px dashed #000;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 6px 6px 0 #000;
        }
        .upload-area.dragover { background: #FFE66D; }
        input[type="file"] {
            display: none;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #ddd;
            border: 3px solid #000;
            margin-top: 10px;
            display: none;
            box-shadow: 3px 3px 0 #000;
        }
        .progress-fill {
            height: 100%;
            background: #4ECDC4;
            width: 0%;
            transition: width 0.3s;
            border-right: 3px solid #000;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: #FFE66D;
            border: 4px solid #000;
            padding: 30px;
            box-shadow: 8px 8px 0 #000;
            min-width: 300px;
        }
        .modal h3 {
            margin-bottom: 15px;
            text-transform: uppercase;
            border-bottom: 3px solid #000;
            padding-bottom: 10px;
        }
        .modal input {
            width: 100%;
            padding: 10px;
            border: 3px solid #000;
            font-family: inherit;
            margin-bottom: 15px;
            box-shadow: 3px 3px 0 #000;
        }
        .server-status {
            background: #000;
            color: #0f0;
            padding: 10px;
            font-family: monospace;
            border: 3px solid #000;
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-dot {
            width: 10px;
            height: 10px;
            background: #0f0;
            border-radius: 50%;
            box-shadow: 0 0 10px #0f0;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        .restrict-notice {
            background: #e74c3c;
            color: #fff;
            border: 3px solid #000;
            padding: 10px;
            margin-bottom: 20px;
            box-shadow: 4px 4px 0 #000;
            font-weight: bold;
            text-transform: uppercase;
        }
        .restrict-notice.ok {
            background: #2ecc71;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>> MC Bedrock Console // File Manager</h1>
    </div>
    
    <div class="controls">
        <form method="POST" style="display:inline" onsubmit="return confirm('Stop server?');">
            <input type="hidden" name="action" value="stop_server">
            <button type="submit" class="btn btn-server">STOP SERVER</button>
        </form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Start server?');">
            <input type="hidden" name="action" value="start_server">
            <button type="submit" class="btn btn-start">START SERVER</button>
        </form>
        <div class="server-status">
            <div class="status-dot"></div>
            <span>SCREEN: <?php echo $screenName; ?></span>
        </div>
        <a href="?logout=1" class="btn" style="margin-left: auto; background: #e74c3c; color: #fff;">LOGOUT</a>
    </div>
    
    <div class="path-bar">
        PATH: /<?php echo htmlspecialchars($currentDir ?: 'mcserver'); ?>/
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message">> <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error">! <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php 
        $canWriteHere = isWritable($fullPath, $basePath, $allowedWritable);
        if (!$canWriteHere && $currentDir !== ''): 
        ?>
            <div class="restrict-notice">! PERMISSION RESTRICTED - READ ONLY MODE</div>
        <?php else: ?>
            <div class="restrict-notice ok">> WRITE ACCESS GRANTED</div>
        <?php endif; ?>
        
        <?php if ($canWriteHere): ?>
        <div class="upload-area" id="dropZone">
            <h3 style="font-size: 18px; margin-bottom: 10px;">DROP FILES HERE OR CLICK TO UPLOAD</h3>
            <p style="font-size: 12px; margin-bottom: 15px;">MAX 3GB PER FILE | ZIP EXTRACTED SIZE LIMITED</p>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload">
                <input type="file" name="file" id="fileInput" onchange="handleFiles(this.files)">
                <button type="button" class="btn" onclick="document.getElementById('fileInput').click()">SELECT FILE</button>
            </form>
            <div class="progress-bar" id="progressBar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
            <button class="btn" onclick="showModal('folder')">+ NEW FOLDER</button>
            <button class="btn" onclick="showModal('file')">+ NEW FILE</button>
        </div>
        <?php endif; ?>
        
        <div class="file-grid">
            <div class="file-header">
                <div>Name</div>
                <div>Size</div>
                <div>Modified</div>
                <div>Actions</div>
            </div>
            
            <?php if ($currentDir !== ''): ?>
                <?php 
                $parent = dirname($currentDir);
                if ($parent === '.') $parent = '';
                ?>
                <div class="file-item">
                    <div class="filename">
                        <span class="icon">📁</span>
                        <a href="?dir=<?php echo urlencode($parent); ?>" style="color: #000; text-decoration: none;">..</a>
                    </div>
                    <div>-</div>
                    <div>-</div>
                    <div>-</div>
                </div>
            <?php endif; ?>
            
            <?php 
            $dirs = [];
            $files = [];
            foreach (scandir($fullPath) as $f) {
                if ($f === '.' || $f === '..') continue;
                if (is_dir($fullPath . '/' . $f)) $dirs[] = $f;
                else $files[] = $f;
            }
            sort($dirs);
            sort($files);
            
            foreach ($dirs as $dir): 
                $path = $currentDir ? $currentDir . '/' . $dir : $dir;
                $fullDirPath = $fullPath . '/' . $dir;
                $dirWritable = isWritable($fullDirPath, $basePath, $allowedWritable);
            ?>
                <div class="file-item">
                    <div class="filename">
                        <span class="icon">📁</span>
                        <a href="?dir=<?php echo urlencode($path); ?>" style="color: #000; text-decoration: none; font-weight: bold;"><?php echo htmlspecialchars($dir); ?>/</a>
                    </div>
                    <div class="size">-</div>
                    <div class="date"><?php echo date('Y-m-d H:i', filemtime($fullDirPath)); ?></div>
                    <div class="actions">
                        <?php if ($dirWritable): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete folder <?php echo $dir; ?>?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="target" value="<?php echo htmlspecialchars($path); ?>">
                                <button type="submit" class="btn-small btn-del">DEL</button>
                            </form>
                            <button class="btn-small" onclick="renameItem('<?php echo htmlspecialchars($path); ?>', '<?php echo $dir; ?>')">REN</button>
                        <?php else: ?>
                            <button class="btn-small btn-disabled" title="Permission Denied">LOCKED</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php foreach ($files as $file): 
                $path = $currentDir ? $currentDir . '/' . $file : $file;
                $fullFilePath = $fullPath . '/' . $file;
                $fileWritable = isWritable($fullFilePath, $basePath, $allowedWritable);
                $size = filesize($fullFilePath);
                $sizeStr = $size > 1024*1024*1024 ? round($size/1024/1024/1024, 2).' GB' : ($size > 1024*1024 ? round($size/1024/1024, 2).' MB' : round($size/1024, 2).' KB');
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $isZip = $ext === 'zip';
            ?>
                <div class="file-item">
                    <div class="filename">
                        <span class="icon"><?php echo $isZip ? '📦' : '📄'; ?></span>
                        <?php echo htmlspecialchars($file); ?>
                    </div>
                    <div class="size"><?php echo $sizeStr; ?></div>
                    <div class="date"><?php echo date('Y-m-d H:i', filemtime($fullFilePath)); ?></div>
                    <div class="actions">
                        <a href="?edit=<?php echo urlencode($path); ?>" class="btn-small btn-edit">EDIT</a>
                        <a href="?download=<?php echo urlencode($path); ?>" class="btn-small btn-dl">DL</a>
                        <?php if ($fileWritable): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete <?php echo $file; ?>?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="target" value="<?php echo htmlspecialchars($path); ?>">
                                <button type="submit" class="btn-small btn-del">DEL</button>
                            </form>
                            <?php if ($isZip): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Extract <?php echo $file; ?>?\nMax extracted size: 9GB (Anti-Zip Bomb)');">
                                    <input type="hidden" name="action" value="unzip">
                                    <input type="hidden" name="file" value="<?php echo htmlspecialchars($path); ?>">
                                    <button type="submit" class="btn-small btn-unzip">UNZIP</button>
                                </form>
                            <?php endif; ?>
                            <button class="btn-small" onclick="renameItem('<?php echo htmlspecialchars($path); ?>', '<?php echo $file; ?>')">REN</button>
                        <?php else: ?>
                            <button class="btn-small btn-disabled" title="Permission Denied">LOCKED</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="modal" id="modal">
        <div class="modal-content">
            <h3 id="modalTitle">New Item</h3>
            <form method="POST" id="modalForm">
                <input type="hidden" name="action" id="modalAction">
                <input type="text" name="name" id="modalInput" placeholder="Name..." required autofocus>
                <button type="submit" class="btn" style="width: 100%;">CREATE</button>
                <button type="button" class="btn btn-danger" style="width: 100%; margin-top: 10px;" onclick="closeModal()">CANCEL</button>
            </form>
        </div>
    </div>
    
    <form method="POST" id="renameForm" style="display:none">
        <input type="hidden" name="action" value="rename">
        <input type="hidden" name="old" id="renameOld">
        <input type="hidden" name="new" id="renameNew">
    </form>
    
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const uploadForm = document.getElementById('uploadForm');
        const progressBar = document.getElementById('progressBar');
        const progressFill = document.getElementById('progressFill');
        const maxSize = <?php echo $uploadMaxSize; ?>;
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });
        
        function handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                if (file.size > maxSize) {
                    alert('File too large! Max 3GB allowed.');
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'upload');
                formData.append('file', file);
                
                progressBar.style.display = 'block';
                
                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressFill.style.width = percentComplete + '%';
                    }
                });
                
                xhr.addEventListener('load', () => {
                    location.reload();
                });
                
                xhr.addEventListener('error', () => {
                    alert('Upload failed');
                    progressBar.style.display = 'none';
                });
                
                xhr.open('POST', '');
                xhr.send(formData);
            }
        }
        
        function showModal(type) {
            document.getElementById('modal').style.display = 'flex';
            document.getElementById('modalAction').value = type === 'folder' ? 'mkdir' : 'mkfile';
            document.getElementById('modalTitle').textContent = type === 'folder' ? 'Create Folder' : 'Create File';
            document.getElementById('modalInput').value = '';
            document.getElementById('modalInput').focus();
        }
        
        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }
        
        function renameItem(path, oldName) {
            const newName = prompt('Rename to:', oldName);
            if (newName && newName !== oldName) {
                document.getElementById('renameOld').value = path;
                document.getElementById('renameNew').value = newName;
                document.getElementById('renameForm').submit();
            }
        }
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
        
        window.onclick = function(e) {
            if (e.target === document.getElementById('modal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>
