<?php session_start();
$basePath=realpath('/home/ubuntu/mcserver');if(!$basePath){exit('Invalid base path');}
$allowedWritable=['worlds','server.properties'];$uploadMaxSize=3*1024*1024*1024;$username='koba';$password='console';
if(isset($_GET['logout'])){session_destroy();header('Location: ?');exit;}
if(!isset($_SESSION['auth'])){if(isset($_POST['u'])&&isset($_POST['p'])){if($_POST['u']===$username&&$_POST['p']===$password){$_SESSION['auth']=true;header('Location: ?');exit;}else{$error='Invalid credentials';}}?><!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Console Login</title><style>*{margin:0;padding:0;box-sizing:border-box;}body{background:#FF6B6B;font-family:'Courier New',monospace;display:flex;justify-content:center;align-items:center;height:100vh;border:4px solid #000;}.login-box{background:#FFE66D;border:4px solid #000;padding:40px;box-shadow:8px 8px 0 #000;max-width:400px;width:90%;}h1{font-size:28px;margin-bottom:20px;text-transform:uppercase;letter-spacing:2px;border-bottom:4px solid #000;padding-bottom:10px;}input{width:100%;padding:12px;margin:10px 0;border:3px solid #000;font-family:inherit;font-size:16px;background:#fff;box-shadow:4px 4px 0 #000;}button{width:100%;padding:15px;background:#4ECDC4;border:3px solid #000;font-family:inherit;font-size:18px;font-weight:bold;cursor:pointer;box-shadow:4px 4px 0 #000;text-transform:uppercase;margin-top:10px;}button:hover{transform:translate(2px,2px);box-shadow:2px 2px 0 #000;}button:active{transform:translate(4px,4px);box-shadow:0 0 0 #000;}.error{background:#FF6B6B;border:3px solid #000;padding:10px;margin-bottom:15px;font-weight:bold;}</style></head><body><div class="login-box"><h1>> CONSOLE_</h1><?php if(isset($error)):?><div class="error">! <?php echo htmlspecialchars($error);?></div><?php endif;?><form method="POST"><input type="text" name="u" placeholder="Username" required autofocus><input type="password" name="p" placeholder="Password" required><button type="submit">LOGIN</button></form></div></body></html><?php exit;}

/* BUG 1 FIX: isWritable hanya untuk DELETE/WRITE ops, EDIT/READ bebas semua file */
function isWritable($path,$basePath,$allowed){
  /* Cek apakah path berada di dalam basePath dulu */
  $real=realpath($path);
  if($real===false){
    /* Path belum ada (file baru), cek parent-nya */
    $real=realpath(dirname($path));
    if($real===false||strpos($real,$basePath)!==0) return false;
    $rel=ltrim(substr($real,strlen($basePath)),'/').'/' .basename($path);
  } else {
    if(strpos($real,$basePath)!==0) return false;
    $rel=ltrim(substr($real,strlen($basePath)),'/');
  }
  if(empty($rel)) return false;
  foreach($allowed as $a){
    if($rel===$a||strpos($rel,$a.'/')===0||strpos($rel,$a)===0) return true;
  }
  return false;
}

/* Validasi path aman (dalam basePath), tanpa cek whitelist — untuk operasi baca */
function isSafePath($path,$basePath){
  $real=realpath($path);
  if($real===false) return false;
  return strpos($real,$basePath)===0;
}

function getDirSize($dir){$size=0;foreach(glob(rtrim($dir,'/').'/*',GLOB_NOSORT) as $each){$size+=is_file($each)?filesize($each):getDirSize($each);}return $size;}
function formatSize($bytes){return $bytes>1024*1024*1024?round($bytes/1024/1024/1024,2).' GB':($bytes>1024*1024?round($bytes/1024/1024,2).' MB':round($bytes/1024,2).' KB');}

/* Hapus rekursif folder */
function rmdirRecursive($dir){
  if(!is_dir($dir)) return;
  $items=scandir($dir);
  foreach($items as $item){
    if($item==='.'||$item==='..') continue;
    $p=$dir.'/'.$item;
    is_dir($p)?rmdirRecursive($p):unlink($p);
  }
  rmdir($dir);
}

$currentDir=isset($_GET['dir'])?$_GET['dir']:'';
$fullPath=realpath($basePath.($currentDir?'/'.$currentDir:''));
if($fullPath===false||strpos($fullPath,$basePath)!==0){$fullPath=$basePath;$currentDir='';}

$message='';$error='';$specialUpload='';

if(isset($_POST['action'])){
  switch($_POST['action']){

    case 'upload':
      /* BUG 2 FIX: cek writable dengan benar */
      if(!isWritable($fullPath,$basePath,$allowedWritable)){
        $error='Permission Denied: Cannot upload to this directory';
      } elseif(isset($_FILES['file'])&&$_FILES['file']['error']===UPLOAD_ERR_OK){
        $file=$_FILES['file'];
        if($file['size']>$uploadMaxSize){
          $error='File too large (Max 3GB)';
        } else {
          $dest=$fullPath.'/'.basename($file['name']);
          if(move_uploaded_file($file['tmp_name'],$dest)){
            $ext=strtolower(pathinfo($dest,PATHINFO_EXTENSION));
            if(in_array($ext,['zip','7z','mcworld'])){
              $specialUpload=basename($dest);
              $message='File uploaded: '.basename($dest).' — Zip/archive terdeteksi!';
            } else {
              $message='File uploaded: '.basename($dest);
            }
          } else {
            $error='Upload failed. Pastikan folder writable.';
          }
        }
      } elseif(isset($_FILES['file'])){
        $uploadErrors=[
          UPLOAD_ERR_INI_SIZE=>'File terlalu besar (php.ini)',
          UPLOAD_ERR_FORM_SIZE=>'File terlalu besar (form)',
          UPLOAD_ERR_PARTIAL=>'Upload tidak lengkap',
          UPLOAD_ERR_NO_FILE=>'Tidak ada file dipilih',
          UPLOAD_ERR_NO_TMP_DIR=>'Tmp dir tidak ada',
          UPLOAD_ERR_CANT_WRITE=>'Gagal tulis ke disk',
          UPLOAD_ERR_EXTENSION=>'Upload diblok extension',
        ];
        $error='Upload error: '.($uploadErrors[$_FILES['file']['error']]??'Unknown error #'.$_FILES['file']['error']);
      } else {
        $error='Tidak ada file yang diterima server.';
      }
      break;

    /* BUG 2 FIX: auto unzip dengan validasi root structure, zip dihapus setelah extract */
    case 'auto_unzip':
      if(!extension_loaded('zip')){$error='ZIP extension tidak tersedia';break;}
      $target=$_POST['file']??'';
      $targetPath=$fullPath.'/'.basename($target);
      $realTarget=realpath($targetPath);
      if($realTarget===false||strpos($realTarget,$basePath)!==0){$error='Invalid path';break;}
      if(!isWritable($fullPath,$basePath,$allowedWritable)){$error='Permission Denied';break;}
      $ext=strtolower(pathinfo($realTarget,PATHINFO_EXTENSION));
      if($ext==='mcworld'||$ext==='zip'){
        $zip=new ZipArchive();
        if($zip->open($realTarget)===true){
          /* Validasi ukuran */
          $totalUncomp=0;
          for($i=0;$i<$zip->numFiles;$i++){$s=$zip->statIndex($i);$totalUncomp+=$s['size'];}
          if($totalUncomp>9*1024*1024*1024){$error='Extract akan melebihi 9GB (Anti-Zip Bomb)';$zip->close();break;}
          /* BUG 2 FIX: Validasi root structure — harus punya max 1 root folder atau 1 root file yang berisi konten */
          $rootEntries=[];
          for($i=0;$i<$zip->numFiles;$i++){
            $name=$zip->getNameIndex($i);
            $parts=explode('/',$name);
            $root=$parts[0];
            if(!empty($root)) $rootEntries[$root]=true;
          }
          $rootCount=count($rootEntries);
          /* Boleh: 1 root folder (isinya banyak), atau beberapa root files/folders tapi bukan puluhan */
          /* TOLAK: root langsung berisi sangat banyak entri (>20 root items) tanpa struktur folder */
          $rootFolders=0;$rootFiles=0;
          for($i=0;$i<$zip->numFiles;$i++){
            $name=$zip->getNameIndex($i);
            $parts=array_filter(explode('/',$name));
            if(count($parts)===1) $rootFiles++;
            elseif(count($parts)>=2&&substr($name,-1)==='/'&&count(array_filter(explode('/',$name)))===1) $rootFolders++;
          }
          /* Hitung root-level items secara tepat */
          $rootLevelNames=[];
          for($i=0;$i<$zip->numFiles;$i++){
            $name=$zip->getNameIndex($i);
            $first=explode('/',$name)[0];
            if(!empty($first)) $rootLevelNames[$first]=true;
          }
          $rootLevelCount=count($rootLevelNames);
          if($rootLevelCount>30){
            $error='Dibatalkan: Archive punya '.$rootLevelCount.' root item(s) — terlalu banyak file di root! Struktur harus 1 root folder atau maks 30 root items.';
            $zip->close();break;
          }
          $zip->extractTo($fullPath);
          $zip->close();
          /* Hapus zip setelah extract berhasil */
          unlink($realTarget);
          $message='Archive diekstrak dan zip dihapus! ('.$rootLevelCount.' root item(s))';
        } else {
          $error='Gagal membuka archive';
        }
      } else {
        $error='File bukan zip/mcworld yang valid';
      }
      break;

    case 'mkdir':
      if(!isWritable($fullPath,$basePath,$allowedWritable)){$error='Permission Denied';}
      else{$name=preg_replace('/[^a-zA-Z0-9_\-\.]/','',$_POST['name']??'');if($name&&mkdir($fullPath.'/'.$name)){$message='Folder created: '.$name;}else{$error='Failed to create folder';}}
      break;

    case 'mkfile':
      if(!isWritable($fullPath,$basePath,$allowedWritable)){$error='Permission Denied';}
      else{$name=preg_replace('/[^a-zA-Z0-9_\-\.]/','',$_POST['name']??'');if($name&&file_put_contents($fullPath.'/'.$name,'')!==false){$message='File created: '.$name;}else{$error='Failed to create file';}}
      break;

    case 'delete':
      $target=$_POST['target']??'';
      $targetPath=realpath($basePath.'/'.ltrim($target,'/'));
      if($targetPath===false||strpos($targetPath,$basePath)!==0){$error='Invalid path';}
      elseif(!isWritable($targetPath,$basePath,$allowedWritable)){$error='Permission Denied: Hanya worlds/ dan server.properties yang bisa didelete';}
      else{
        if(is_dir($targetPath)){rmdirRecursive($targetPath);$message='Folder deleted';}
        elseif(is_file($targetPath)){unlink($targetPath);$message='File deleted';}
        else{$error='Target not found';}
      }
      break;

    /* BUG 3 FIX: rename + move file dengan validasi basePath */
    case 'rename':
      $old=$_POST['old']??'';
      $new=$_POST['new']??'';
      /* Sanitasi nama baru — hanya nama file (tidak boleh ada slash) */
      $newName=preg_replace('/[^a-zA-Z0-9_\-\.]/','',$new);
      if(!$newName){$error='Nama baru tidak valid (hanya huruf, angka, _, -, .)';break;}
      $oldPath=realpath($basePath.'/'.ltrim($old,'/'));
      if($oldPath===false||strpos($oldPath,$basePath)!==0){$error='Invalid path sumber';break;}
      /* Cek writable pada parent sumber */
      if(!isWritable(dirname($oldPath),$basePath,$allowedWritable)&&!isWritable($oldPath,$basePath,$allowedWritable)){$error='Permission Denied untuk rename';break;}
      $newPath=dirname($oldPath).'/'.$newName;
      if(file_exists($newPath)){$error='Nama sudah ada di folder ini';break;}
      if(rename($oldPath,$newPath)){$message='Renamed: '.basename($oldPath).' → '.$newName;}
      else{$error='Rename gagal';}
      break;

    /* BUG 3 FIX: move file ke path lain dalam basePath */
    case 'move':
      $src=$_POST['src']??'';
      $dstDir=$_POST['dst_dir']??'';
      $srcPath=realpath($basePath.'/'.ltrim($src,'/'));
      if($srcPath===false||strpos($srcPath,$basePath)!==0){$error='Invalid path sumber';break;}
      /* Sanitasi dst_dir — boleh mengandung slash tapi tetap dalam basePath */
      $dstDirClean=str_replace(['..','//'],'',$dstDir);
      $dstFull=realpath($basePath.'/'.ltrim($dstDirClean,'/'));
      if($dstFull===false||strpos($dstFull,$basePath)!==0){$error='Invalid path tujuan';break;}
      if(!is_dir($dstFull)){$error='Folder tujuan tidak ditemukan';break;}
      /* Pastikan tidak move folder ke dalam dirinya sendiri */
      if(strpos($dstFull.'/',$srcPath.'/')===0){$error='Tidak bisa memindah folder ke dalam dirinya sendiri';break;}
      /* Cek writable tujuan dan sumber */
      if(!isWritable($dstFull,$basePath,$allowedWritable)&&!isWritable($srcPath,$basePath,$allowedWritable)){$error='Permission Denied: sumber atau tujuan harus dalam worlds/ atau server.properties';break;}
      $newPath=$dstFull.'/'.basename($srcPath);
      if(file_exists($newPath)){$error='File/folder dengan nama sama sudah ada di tujuan';break;}
      if(rename($srcPath,$newPath)){
        $newRel=ltrim(substr($newPath,strlen($basePath)),'/');
        $newDir=ltrim(substr(dirname($newPath),strlen($basePath)),'/');
        $message='Dipindahkan ke: /'.($newDir?$newDir.'/':'').basename($newPath);
        /* Redirect ke folder tujuan */
        header('Location: ?dir='.urlencode($newDir).($message?'&msg='.urlencode($message):''));exit;
      } else {
        $error='Move gagal';
      }
      break;

    case 'unzip':
      if(!extension_loaded('zip')){$error='ZIP extension not available';break;}
      $target=$_POST['file']??'';
      $targetPath=realpath($basePath.'/'.ltrim($target,'/'));
      if($targetPath===false||strpos($targetPath,$basePath)!==0){$error='Invalid path';break;}
      if(!isWritable($fullPath,$basePath,$allowedWritable)){$error='Permission Denied';break;}
      $zip=new ZipArchive();
      if($zip->open($targetPath)===true){
        $totalSize=0;
        for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$totalSize+=$stat['comp_size'];}
        if($totalSize>9*1024*1024*1024){$error='Extract would exceed 9GB limit (Anti-Zip Bomb)';$zip->close();break;}
        $zip->extractTo($fullPath);$zip->close();$message='Archive extracted';
      } else {$error='Failed to open archive';}
      break;

    case 'savefile':
      $target=$_POST['target']??'';
      /* BUG 1 FIX: savefile pakai isSafePath, semua file bisa di-save tanpa whitelist */
      $targetPath=realpath($basePath.'/'.ltrim($target,'/'));
      if($targetPath===false||strpos($targetPath,$basePath)!==0){$error='Invalid path';break;}
      /* Save tidak perlu whitelist — user sudah auth */
      if(file_put_contents($targetPath,$_POST['content']??'')!==false){$message='File saved';}
      else{$error='Save failed — permission issue di filesystem';}
      break;
  }
}

/* Ambil msg dari redirect */
if(isset($_GET['msg'])&&empty($message)) $message=htmlspecialchars($_GET['msg']);

if(isset($_GET['download'])){
  $target=$_GET['download']??'';
  $targetPath=realpath($basePath.'/'.ltrim($target,'/'));
  if($targetPath===false||strpos($targetPath,$basePath)!==0){exit('Invalid path');}
  if(!is_file($targetPath)){exit('File not found');}
  header('Content-Type: application/octet-stream');
  header('Content-Disposition: attachment; filename="'.basename($targetPath).'"');
  header('Content-Length: '.filesize($targetPath));
  readfile($targetPath);exit;
}

/* BUG 1 FIX: Edit file bebas semua file dalam basePath (hanya baca) */
if(isset($_GET['edit'])){
  $target=$_GET['edit']??'';
  $targetPath=realpath($basePath.'/'.ltrim($target,'/'));
  if($targetPath===false||strpos($targetPath,$basePath)!==0){exit('Invalid path');}
  if(!is_file($targetPath)){exit('File not found');}
  $content=file_get_contents($targetPath);
  $lines=count(explode("\n",$content));
  $editDir=ltrim(substr(dirname($targetPath),strlen($basePath)),'/');
?><!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Edit - <?php echo htmlspecialchars(basename($targetPath));?></title><style>*{margin:0;padding:0;box-sizing:border-box;}body{background:#2a2a2a;color:#fff;font-family:monospace;padding:20px;}textarea{width:100%;padding:15px;background:#1e1e1e;color:#00ff00;border:2px solid #444;font-family:monospace;font-size:14px;min-height:400px;resize:vertical;}button{padding:10px 20px;background:#4ECDC4;border:2px solid #000;font-weight:bold;cursor:pointer;margin-right:10px;margin-top:10px;}.btn-save{background:#7ED321;}.btn-back{background:#FF6B6B;color:#fff;}.editor-header{margin-bottom:20px;}a{color:#4ECDC4;text-decoration:none;}.info{color:#aaa;font-size:13px;margin-top:5px;}</style></head><body><div class="editor-header"><h1><?php echo htmlspecialchars(basename($targetPath));?></h1><p class="info"><?php echo number_format(strlen($content));?> bytes | <?php echo $lines;?> lines | <?php echo htmlspecialchars($targetPath);?></p></div><form method="POST" action="?dir=<?php echo urlencode($editDir);?>"><input type="hidden" name="action" value="savefile"><input type="hidden" name="target" value="<?php echo htmlspecialchars($target);?>"><textarea name="content"><?php echo htmlspecialchars($content);?></textarea><br><button type="submit" class="btn-save">SAVE</button><a href="?dir=<?php echo urlencode($editDir);?>"><button type="button" class="btn-back">BACK</button></a></form></body></html><?php exit;}

/* Kumpulkan daftar semua folder dalam basePath untuk fitur move */
function getAllDirs($base,$current='',$depth=0){
  if($depth>5) return [];
  $dirs=[];
  $path=$base.($current?'/'.$current:'');
  if(!is_dir($path)) return [];
  $items=scandir($path);
  foreach($items as $item){
    if($item==='.'||$item==='..') continue;
    $full=$path.'/'.$item;
    if(is_dir($full)){
      $rel=$current?$current.'/'.$item:$item;
      $dirs[]=['rel'=>$rel,'label'=>str_repeat('&nbsp;&nbsp;',$depth).'📁 '.$rel];
      $sub=getAllDirs($base,$rel,$depth+1);
      $dirs=array_merge($dirs,$sub);
    }
  }
  return $dirs;
}
$allDirs=getAllDirs($basePath);
$allDirsJson=json_encode(array_map(fn($d)=>['rel'=>$d['rel'],'label'=>htmlspecialchars_decode($d['label'])],$allDirs));

?><!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Console</title><style>*{margin:0;padding:0;box-sizing:border-box;}body{background:#1a1a1a;color:#fff;font-family:monospace;padding:20px;}.header{background:#000;border:3px solid #fff;padding:20px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;}.header h1{text-transform:uppercase;letter-spacing:2px;font-size:24px;}.status{background:#FFE66D;color:#000;padding:10px 15px;border:2px solid #000;font-weight:bold;}.message{background:#7ED321;border:3px solid #000;padding:15px;margin:10px 0;font-weight:bold;color:#000;}.error{background:#FF6B6B;border:3px solid #000;padding:15px;margin:10px 0;font-weight:bold;color:#000;}.breadcrumb{background:#2a2a2a;padding:15px;border:2px solid #444;margin-bottom:10px;}.breadcrumb a{color:#4ECDC4;text-decoration:none;margin:0 5px;}.breadcrumb a:hover{text-decoration:underline;}.dev-credit{background:#111;border:1px solid #333;padding:8px 15px;margin-bottom:15px;font-size:11px;color:#666;}.dev-credit span{color:#4ECDC4;}.controls{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:20px;}.btn{padding:12px;background:#4ECDC4;border:3px solid #000;font-family:monospace;font-weight:bold;cursor:pointer;text-transform:uppercase;text-decoration:none;display:inline-block;text-align:center;transition:all 0.2s;}.btn:hover{transform:translate(2px,2px);box-shadow:4px 4px 0 #000;}.btn-danger{background:#FF6B6B;}.btn-success{background:#7ED321;color:#000;}.drop-zone{border:3px dashed #4ECDC4;padding:40px;text-align:center;cursor:pointer;background:#2a2a2a;margin-bottom:20px;transition:all 0.3s;position:relative;}.drop-zone.dragover{background:#1a3a3a;border-color:#7ED321;}.drop-zone p{margin-bottom:10px;pointer-events:none;}.drop-zone-hint{font-size:12px;color:#aaa;}.files{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:15px;}.file-item{background:#2a2a2a;border:2px solid #444;padding:15px;display:flex;flex-direction:column;}.file-item-name{font-weight:bold;margin-bottom:10px;word-break:break-all;}.file-item-info{font-size:12px;color:#aaa;margin-bottom:10px;}.file-item-actions{display:flex;gap:5px;flex-wrap:wrap;}.btn-small{padding:6px 10px;font-size:11px;background:#4ECDC4;border:2px solid #000;cursor:pointer;flex:1;min-width:50px;text-align:center;text-decoration:none;display:inline-block;}.btn-small.btn-del{background:#FF6B6B;}.btn-small.btn-edit{background:#FFE66D;color:#000;}.btn-small.btn-dl{background:#7ED321;color:#000;}.btn-small.btn-disabled{background:#555;color:#888;cursor:not-allowed;border-color:#555;}.btn-small.btn-move{background:#c678dd;color:#fff;}.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);justify-content:center;align-items:center;z-index:1000;}.modal-content{background:#2a2a2a;border:3px solid #fff;padding:30px;max-width:420px;width:90%;max-height:80vh;overflow-y:auto;}.modal-content h3{margin-bottom:15px;text-transform:uppercase;}.modal-content input,.modal-content select{width:100%;padding:10px;margin-bottom:15px;background:#1a1a1a;border:2px solid #444;color:#fff;font-family:monospace;}.modal-content button{width:100%;padding:10px;margin-bottom:10px;background:#4ECDC4;border:2px solid #000;font-family:monospace;font-weight:bold;cursor:pointer;font-size:14px;}.modal-content .btn-cancel{background:#FF6B6B;}.progress-wrap{display:none;margin-top:15px;}.progress-bar{width:100%;height:24px;background:#333;border:2px solid #fff;overflow:hidden;}.progress-fill{height:100%;background:#7ED321;width:0%;transition:width 0.15s;}.progress-text{text-align:center;font-size:12px;color:#aaa;margin-top:5px;}.special-notice{background:#1a1a2a;border:3px solid #4ECDC4;padding:20px;margin:15px 0;}.special-notice h3{color:#4ECDC4;margin-bottom:10px;}.special-notice p{margin-bottom:10px;font-size:13px;color:#ccc;}.special-notice button{padding:12px 20px;background:#7ED321;border:3px solid #000;font-family:monospace;font-weight:bold;cursor:pointer;margin-right:10px;font-size:14px;}.special-notice .btn-skip{background:#444;color:#ccc;}</style></head><body>
<div class="header"><h1>> CONSOLE_</h1><div><a href="?logout=1" class="status" style="text-decoration:none;cursor:pointer;">LOGOUT</a></div></div>
<?php if(!empty($message)):?><div class="message">✓ <?php echo htmlspecialchars($message);?></div><?php endif;?>
<?php if(!empty($error)):?><div class="error">✗ <?php echo htmlspecialchars($error);?></div><?php endif;?>
<div class="breadcrumb">Location: <a href="?" style="color:#fff;font-weight:bold;">/mcserver</a><?php if($currentDir){foreach(explode('/',$currentDir) as $i=>$part){if(empty($part))continue;$path=implode('/',array_slice(explode('/',$currentDir),0,$i+1));echo ' / <a href="?dir='.urlencode($path).'">'.htmlspecialchars($part).'</a>';}}?></div>
<div class="dev-credit">For Developer : <span>RIZZXD</span> &nbsp;|&nbsp; WA : <span>081224595908</span></div>

<?php if(!empty($specialUpload)):?>
<div class="special-notice" id="specialNotice">
  <h3>📦 Archive Terdeteksi!</h3>
  <p>File <strong><?php echo htmlspecialchars($specialUpload);?></strong> adalah archive (zip/7z/mcworld).</p>
  <p>Mau langsung di-extract otomatis? Zip akan dihapus setelah extract berhasil.</p>
  <form method="POST" id="autoUnzipForm">
    <input type="hidden" name="action" value="auto_unzip">
    <input type="hidden" name="file" value="<?php echo htmlspecialchars($specialUpload);?>">
    <button type="submit">⚡ AUTO EXTRACT & HAPUS ZIP</button>
    <button type="button" class="btn-skip" onclick="document.getElementById('specialNotice').style.display='none'">Nanti Aja</button>
  </form>
</div>
<?php endif;?>

<div class="controls">
  <button class="btn btn-success" onclick="showModal('folder')">+ FOLDER</button>
  <button class="btn btn-success" onclick="showModal('file')">+ FILE</button>
</div>

<!-- BUG 2 FIX: Drop zone dengan file input di dalam, pakai label trick agar klik juga kerja -->
<div class="drop-zone" id="dropZone">
  <p>📥 Drag & drop files here to upload</p>
  <p class="drop-zone-hint">Max size: 3GB | ZIP/7z/MCWORLD akan ditawarkan auto-extract</p>
  <input type="file" id="fileInputMain" style="display:none">
  <div class="progress-wrap" id="progressWrap">
    <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
    <div class="progress-text" id="progressText">Uploading...</div>
  </div>
</div>

<?php
$dirs=[];$files=[];
if($handle=opendir($fullPath)){
  while(($f=readdir($handle))!==false){
    if($f==='.'||$f==='..') continue;
    if(is_dir($fullPath.'/'.$f)) $dirs[]=$f;
    else $files[]=$f;
  }
  closedir($handle);
}
sort($dirs);sort($files);
if(empty($dirs)&&empty($files)):?>
<div style="text-align:center;padding:40px;color:#aaa;">Empty directory</div>
<?php else:?>
<div class="files">
<?php foreach($dirs as $dir){
  $path=$currentDir?$currentDir.'/'.$dir:$dir;
  $fullDirPath=$fullPath.'/'.$dir;
  $dirWritable=isWritable($fullDirPath,$basePath,$allowedWritable);
?>
<div class="file-item">
  <div class="file-item-name">📁 <?php echo htmlspecialchars($dir);?>/</div>
  <div class="file-item-info">Folder</div>
  <div class="file-item-actions">
    <a href="?dir=<?php echo urlencode($path);?>" class="btn-small">OPEN</a>
    <button class="btn-small" onclick="renameItem('<?php echo htmlspecialchars(addslashes($path));?>','<?php echo htmlspecialchars(addslashes($dir));?>')">REN</button>
    <button class="btn-small btn-move" onclick="moveItem('<?php echo htmlspecialchars(addslashes($path));?>','<?php echo htmlspecialchars(addslashes($dir));?>')">MOVE</button>
    <?php if($dirWritable):?>
    <form method="POST" style="display:contents;" onsubmit="return confirm('Delete folder <?php echo htmlspecialchars($dir);?> dan semua isinya?');">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="target" value="<?php echo htmlspecialchars($path);?>">
      <button type="submit" class="btn-small btn-del">DEL</button>
    </form>
    <?php else:?>
    <button class="btn-small btn-disabled" title="Hanya worlds/ yang bisa didelete">LOCKED</button>
    <?php endif;?>
  </div>
</div>
<?php }
foreach($files as $file){
  $path=$currentDir?$currentDir.'/'.$file:$file;
  $fullFilePath=$fullPath.'/'.$file;
  $fileWritable=isWritable($fullFilePath,$basePath,$allowedWritable);
  $size=filesize($fullFilePath);
  $sizeStr=formatSize($size);
  $ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));
  $isZip=in_array($ext,['zip','mcworld']);
?>
<div class="file-item">
  <div class="file-item-name"><?php echo $isZip?'📦':'📄';?> <?php echo htmlspecialchars($file);?></div>
  <div class="file-item-info"><?php echo $sizeStr;?></div>
  <div class="file-item-actions">
    <!-- BUG 1 FIX: EDIT bebas semua file -->
    <a href="?edit=<?php echo urlencode($path);?>&dir=<?php echo urlencode($currentDir);?>" class="btn-small btn-edit">EDIT</a>
    <a href="?download=<?php echo urlencode($path);?>&dir=<?php echo urlencode($currentDir);?>" class="btn-small btn-dl">DL</a>
    <button class="btn-small" onclick="renameItem('<?php echo htmlspecialchars(addslashes($path));?>','<?php echo htmlspecialchars(addslashes($file));?>')">REN</button>
    <button class="btn-small btn-move" onclick="moveItem('<?php echo htmlspecialchars(addslashes($path));?>','<?php echo htmlspecialchars(addslashes($file));?>')">MOVE</button>
    <?php if($fileWritable):?>
    <form method="POST" style="display:contents;" onsubmit="return confirm('Delete <?php echo htmlspecialchars($file);?>?');">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="target" value="<?php echo htmlspecialchars($path);?>">
      <button type="submit" class="btn-small btn-del">DEL</button>
    </form>
    <?php if($isZip):?>
    <form method="POST" style="display:contents;" onsubmit="return confirm('Extract <?php echo htmlspecialchars($file);?>?\nZip akan DIHAPUS setelah extract.\nMax 9GB. Lanjut?');">
      <input type="hidden" name="action" value="auto_unzip">
      <input type="hidden" name="file" value="<?php echo htmlspecialchars($path);?>">
      <button type="submit" class="btn-small">UNZIP</button>
    </form>
    <?php endif;?>
    <?php else:?>
    <button class="btn-small btn-disabled" title="Hanya worlds/ dan server.properties yang bisa didelete">LOCKED</button>
    <?php endif;?>
  </div>
</div>
<?php }?>
</div>
<?php endif;?>

<!-- Modal create folder/file -->
<div class="modal" id="modal">
  <div class="modal-content">
    <h3 id="modalTitle">New Item</h3>
    <form method="POST">
      <input type="hidden" name="action" id="modalAction">
      <input type="text" name="name" id="modalInput" placeholder="Name..." required autofocus>
      <button type="submit">CREATE</button>
      <button type="button" class="btn-cancel" onclick="closeModal()">CANCEL</button>
    </form>
  </div>
</div>

<!-- BUG 3 FIX: Modal rename -->
<div class="modal" id="renameModal">
  <div class="modal-content">
    <h3>Rename Item</h3>
    <form method="POST" id="renameForm">
      <input type="hidden" name="action" value="rename">
      <input type="hidden" name="old" id="renameOld">
      <label style="color:#aaa;font-size:12px;display:block;margin-bottom:5px;">Nama baru (hanya huruf, angka, _, -, .):</label>
      <input type="text" name="new" id="renameNew" placeholder="Nama baru..." required>
      <button type="submit">RENAME</button>
      <button type="button" class="btn-cancel" onclick="closeModal2('renameModal')">CANCEL</button>
    </form>
  </div>
</div>

<!-- BUG 3 FIX: Modal move -->
<div class="modal" id="moveModal">
  <div class="modal-content">
    <h3>Pindahkan File/Folder</h3>
    <form method="POST" id="moveForm">
      <input type="hidden" name="action" value="move">
      <input type="hidden" name="src" id="moveSrc">
      <label style="color:#aaa;font-size:12px;display:block;margin-bottom:5px;">Pindahkan <strong id="moveItemName"></strong> ke:</label>
      <select name="dst_dir" id="moveDst" style="color:#fff;background:#1a1a1a;border:2px solid #444;width:100%;padding:10px;margin-bottom:15px;font-family:monospace;">
        <option value="">/mcserver (root)</option>
      </select>
      <button type="submit">PINDAHKAN</button>
      <button type="button" class="btn-cancel" onclick="closeModal2('moveModal')">CANCEL</button>
    </form>
  </div>
</div>

<script>
const basePath='<?php echo addslashes($basePath);?>';
const currentDir='<?php echo addslashes($currentDir);?>';
const allDirs=<?php echo $allDirsJson;?>;
const maxSize=<?php echo $uploadMaxSize;?>;

/* === BUG 2 FIX: Upload === */
const dropZone=document.getElementById('dropZone');
const fileInput=document.getElementById('fileInputMain');
const progressWrap=document.getElementById('progressWrap');
const progressFill=document.getElementById('progressFill');
const progressText=document.getElementById('progressText');

dropZone.addEventListener('dragover',(e)=>{e.preventDefault();dropZone.classList.add('dragover');});
dropZone.addEventListener('dragleave',()=>{dropZone.classList.remove('dragover');});
dropZone.addEventListener('drop',(e)=>{e.preventDefault();dropZone.classList.remove('dragover');if(e.dataTransfer.files.length>0)handleUpload(e.dataTransfer.files[0]);});
dropZone.addEventListener('click',(e)=>{if(e.target===fileInput)return;fileInput.click();});
fileInput.addEventListener('change',(e)=>{if(e.target.files.length>0)handleUpload(e.target.files[0]);});

function handleUpload(file){
  if(file.size>maxSize){alert('File terlalu besar! Max 3GB.');return;}
  const formData=new FormData();
  formData.append('action','upload');
  formData.append('file',file);
  progressWrap.style.display='block';
  progressFill.style.width='0%';
  progressText.textContent='Uploading '+file.name+'...';
  const xhr=new XMLHttpRequest();
  xhr.upload.addEventListener('progress',(e)=>{
    if(e.lengthComputable){
      const pct=Math.round((e.loaded/e.total)*100);
      progressFill.style.width=pct+'%';
      progressText.textContent='Uploading... '+pct+'% ('+formatBytes(e.loaded)+' / '+formatBytes(e.total)+')';
    }
  });
  xhr.addEventListener('load',()=>{location.reload();});
  xhr.addEventListener('error',()=>{alert('Upload gagal! Cek permission folder.');progressWrap.style.display='none';});
  xhr.open('POST','?dir='+encodeURIComponent(currentDir));
  xhr.send(formData);
}

function formatBytes(b){if(b>1073741824)return(b/1073741824).toFixed(1)+' GB';if(b>1048576)return(b/1048576).toFixed(1)+' MB';return(b/1024).toFixed(0)+' KB';}

/* === Modal create === */
function showModal(type){
  document.getElementById('modal').style.display='flex';
  document.getElementById('modalAction').value=type==='folder'?'mkdir':'mkfile';
  document.getElementById('modalTitle').textContent=type==='folder'?'Create Folder':'Create File';
  document.getElementById('modalInput').value='';
  setTimeout(()=>document.getElementById('modalInput').focus(),100);
}
function closeModal(){document.getElementById('modal').style.display='none';}
function closeModal2(id){document.getElementById(id).style.display='none';}

/* === BUG 3 FIX: Rename === */
function renameItem(path,oldName){
  document.getElementById('renameOld').value=path;
  document.getElementById('renameNew').value=oldName;
  document.getElementById('renameModal').style.display='flex';
  setTimeout(()=>document.getElementById('renameNew').focus(),100);
}

/* === BUG 3 FIX: Move === */
function moveItem(path,name){
  document.getElementById('moveSrc').value=path;
  document.getElementById('moveItemName').textContent=name;
  /* Populate select */
  const sel=document.getElementById('moveDst');
  sel.innerHTML='<option value="">/mcserver (root)</option>';
  allDirs.forEach(d=>{
    /* Jangan tampilkan folder yang sama dengan item itu sendiri */
    if(d.rel===path||d.rel.startsWith(path+'/')) return;
    const opt=document.createElement('option');
    opt.value=d.rel;
    opt.textContent=d.label;
    sel.appendChild(opt);
  });
  document.getElementById('moveModal').style.display='flex';
}

document.addEventListener('keydown',(e)=>{
  if(e.key==='Escape'){closeModal();closeModal2('renameModal');closeModal2('moveModal');}
});
window.onclick=function(e){
  const modals=['modal','renameModal','moveModal'];
  modals.forEach(id=>{if(e.target===document.getElementById(id))closeModal2(id);});
};
</script>
</body></html>
