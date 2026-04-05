<?php
$isHttps=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||(isset($_SERVER['HTTP_X_FORWARDED_PROTO'])&&$_SERVER['HTTP_X_FORWARDED_PROTO']==='https')||(isset($_SERVER['HTTP_X_FORWARDED_SSL'])&&$_SERVER['HTTP_X_FORWARDED_SSL']==='on')||($_SERVER['SERVER_PORT']??80)==443;
$cookieLife=60*60*24*365;
ini_set('session.gc_maxlifetime',$cookieLife);
session_set_cookie_params(['lifetime'=>$cookieLife,'path'=>'/','domain'=>'','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
session_start();
$basePath=realpath('/home/ubuntu/mcserver');if(!$basePath)exit('Invalid base path');
$allowedWritable=['worlds','server.properties'];
$uploadMaxSize=3*1024*1024*1024;
$username='koba';$password='console';

function safeRedirect($qs=''){
  global $isHttps;
  $s=$isHttps?'https':'http';
  $h=$_SERVER['HTTP_HOST'];
  $p=strtok($_SERVER['REQUEST_URI'],'?');
  header('Location: '.$s.'://'.$h.$p.($qs?'?'.$qs:''),true,302);
  exit;
}

function jsonOut($ok,$msg){
  header('Content-Type: application/json');
  echo json_encode(['ok'=>(bool)$ok,'msg'=>(string)$msg]);
  exit;
}

if(isset($_GET['logout'])){
  $_SESSION=[];
  session_destroy();
  setcookie(session_name(),'',['expires'=>time()-86400,'path'=>'/','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
  safeRedirect();
}

if(!isset($_SESSION['auth'])){
  $loginError='';
  if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['u'],$_POST['p'])){
    if($_POST['u']===$username&&$_POST['p']===$password){
      $_SESSION['auth']=true;
      safeRedirect();
    }else{
      $loginError='Username atau password salah';
    }
  }
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Console Login</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#FF6B6B;font-family:'Courier New',monospace;display:flex;justify-content:center;align-items:center;min-height:100vh}
.box{background:#FFE66D;border:4px solid #000;padding:40px;box-shadow:8px 8px 0 #000;max-width:400px;width:90%}
h1{font-size:28px;margin-bottom:20px;text-transform:uppercase;letter-spacing:2px;border-bottom:4px solid #000;padding-bottom:10px}
input[type=text],input[type=password]{width:100%;padding:12px;margin:10px 0;border:3px solid #000;font-family:inherit;font-size:16px;background:#fff;box-shadow:4px 4px 0 #000}
button{width:100%;padding:15px;background:#4ECDC4;border:3px solid #000;font-family:inherit;font-size:18px;font-weight:bold;cursor:pointer;box-shadow:4px 4px 0 #000;text-transform:uppercase;margin-top:10px}
button:hover{transform:translate(2px,2px);box-shadow:2px 2px 0 #000}
button:active{transform:translate(4px,4px);box-shadow:none}
.err{background:#FF6B6B;border:3px solid #000;padding:10px;margin-bottom:15px;font-weight:bold}
.note{font-size:11px;color:#333;margin-top:12px;border-top:2px solid #000;padding-top:10px;line-height:1.5}
</style>
</head>
<body>
<div class="box">
  <h1>&gt; CONSOLE_</h1>
  <?php if($loginError):?><div class="err">! <?=htmlspecialchars($loginError)?></div><?php endif?>
  <form method="POST" action="">
    <input type="text" name="u" placeholder="Username" required autofocus autocomplete="username">
    <input type="password" name="p" placeholder="Password" required autocomplete="current-password">
    <button type="submit">LOGIN</button>
  </form>
  <p class="note">Dengan login, sesi akan disimpan permanen (1 tahun) di browser Anda. Sesi tetap aktif meski browser atau tab ditutup.</p>
</div>
</body>
</html>
<?php exit;}

function isW($path,$base,$allowed){
  $r=realpath($path);
  if($r===false){
    $r=realpath(dirname($path));
    if($r===false||strpos($r,$base)!==0)return false;
    $rel=ltrim(substr($r,strlen($base)),'/').'/'.basename($path);
  }else{
    if(strpos($r,$base)!==0)return false;
    $rel=ltrim(substr($r,strlen($base)),'/');
  }
  if(empty($rel))return false;
  foreach($allowed as $a){if($rel===$a||strpos($rel,$a.'/')===0||strpos($rel,$a)===0)return true;}
  return false;
}

function fmtSz($b){
  if($b>1073741824)return round($b/1073741824,2).' GB';
  if($b>1048576)return round($b/1048576,2).' MB';
  return round($b/1024,2).' KB';
}

function rmRec($dir){
  if(!is_dir($dir))return;
  $items=@scandir($dir);
  if(!$items)return;
  foreach($items as $i){
    if($i==='.'||$i==='..') continue;
    $p=$dir.'/'.$i;
    is_dir($p)?rmRec($p):@unlink($p);
  }
  @rmdir($dir);
}

function doExtract($realFile,$destDir){
  if(!extension_loaded('zip'))return[false,'PHP zip extension tidak tersedia di server ini'];
  if(!is_file($realFile))return[false,'File tidak ditemukan di server'];
  $ext=strtolower(pathinfo($realFile,PATHINFO_EXTENSION));
  if(!in_array($ext,['zip','mcworld']))return[false,'Format tidak didukung untuk auto-extract: '.$ext];
  $zip=new ZipArchive();
  $opened=$zip->open($realFile);
  if($opened!==true)return[false,'Gagal membuka archive ZIP (kode error: '.$opened.')'];
  $totalUncomp=0;
  for($i=0;$i<$zip->numFiles;$i++){
    $s=$zip->statIndex($i);
    $totalUncomp+=(int)$s['size'];
  }
  if($totalUncomp>9*1024*1024*1024){
    $zip->close();
    return[false,'Extract akan melebihi 9GB — dibatalkan untuk mencegah Zip Bomb'];
  }
  $rootNames=[];
  for($i=0;$i<$zip->numFiles;$i++){
    $n=$zip->getNameIndex($i);
    $first=explode('/',$n)[0];
    if(!empty($first))$rootNames[$first]=true;
  }
  $rc=count($rootNames);
  if($rc>30){
    $zip->close();
    return[false,'Dibatalkan: '.$rc.' root item terdeteksi dalam zip — struktur tidak valid (maks 30 root item)'];
  }
  $ok=$zip->extractTo($destDir);
  $zip->close();
  if(!$ok)return[false,'extractTo() gagal — cek permission direktori tujuan'];
  @unlink($realFile);
  return[true,'Extract berhasil ('.$rc.' root item) & zip dihapus otomatis'];
}

$currentDir=isset($_GET['dir'])?trim(str_replace(['..','//'],'',$_GET['dir']),'/'):'';
$fullPath=realpath($basePath.($currentDir?'/'.$currentDir:''));
if($fullPath===false||strpos($fullPath,$basePath)!==0){$fullPath=$basePath;$currentDir='';}

$isJson=isset($_GET['json'])&&$_GET['json']==='1';
$message='';$error='';

if(isset($_GET['download'])){
  $t=realpath($basePath.'/'.ltrim($_GET['download']??'','/'));
  if(!$t||!is_file($t)||strpos($t,$basePath)!==0)exit('Invalid path');
  header('Content-Type: application/octet-stream');
  header('Content-Disposition: attachment; filename="'.basename($t).'"');
  header('Content-Length: '.filesize($t));
  readfile($t);exit;
}

if(isset($_GET['edit'])){
  $t=realpath($basePath.'/'.ltrim($_GET['edit']??'','/'));
  if(!$t||!is_file($t)||strpos($t,$basePath)!==0)exit('Invalid path');
  $ec=file_get_contents($t);
  $eDir=ltrim(substr(dirname($t),strlen($basePath)),'/');
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit - <?=htmlspecialchars(basename($t))?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#2a2a2a;color:#fff;font-family:monospace;padding:20px}
h1{margin-bottom:5px;font-size:18px}
.info{color:#aaa;font-size:12px;margin-bottom:15px}
textarea{width:100%;padding:15px;background:#1e1e1e;color:#00ff00;border:2px solid #444;font-family:monospace;font-size:14px;min-height:60vh;resize:vertical}
.acts{margin-top:10px;display:flex;gap:10px;flex-wrap:wrap}
button{padding:10px 20px;border:2px solid #000;font-weight:bold;cursor:pointer;font-family:monospace;font-size:14px}
.sv{background:#7ED321;color:#000}
.bk{background:#FF6B6B;color:#fff}
a{text-decoration:none}
.toast{position:fixed;top:20px;right:20px;padding:12px 20px;font-weight:bold;font-family:monospace;border:3px solid #000;display:none;z-index:999}
.toast.ok{background:#7ED321;color:#000}
.toast.fail{background:#FF6B6B;color:#000}
</style>
</head>
<body>
<h1>📝 <?=htmlspecialchars(basename($t))?></h1>
<p class="info"><?=number_format(strlen($ec))?> bytes &nbsp;|&nbsp; <?=number_format(count(explode("\n",$ec)))?> baris &nbsp;|&nbsp; <?=htmlspecialchars($t)?></p>
<form id="ef" method="POST" action="?dir=<?=urlencode($eDir)?>">
  <input type="hidden" name="action" value="savefile">
  <input type="hidden" name="target" value="<?=htmlspecialchars($_GET['edit'])?>">
  <textarea name="content" id="ta"><?=htmlspecialchars($ec)?></textarea>
  <div class="acts">
    <button type="submit" class="sv">💾 SAVE</button>
    <a href="?dir=<?=urlencode($eDir)?>"><button type="button" class="bk">← BACK</button></a>
  </div>
</form>
<div class="toast" id="toast"></div>
<script>
document.getElementById('ef').addEventListener('submit',function(e){
  e.preventDefault();
  var fd=new FormData(this);
  var t=document.getElementById('toast');
  fetch(this.action,{method:'POST',body:fd})
    .then(function(r){
      t.className='toast ok';t.textContent='✓ File berhasil disimpan!';t.style.display='block';
      setTimeout(function(){t.style.display='none';},3000);
    })
    .catch(function(){
      t.className='toast fail';t.textContent='✗ Gagal menyimpan!';t.style.display='block';
      setTimeout(function(){t.style.display='none';},4000);
    });
});
document.addEventListener('keydown',function(e){
  if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();document.getElementById('ef').dispatchEvent(new Event('submit'));}
});
</script>
</body>
</html>
<?php exit;}

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])){
  switch($_POST['action']){

    case 'upload':
      if(!isW($fullPath,$basePath,$allowedWritable)){
        $msg='Permission Denied: direktori ini tidak bisa di-upload (hanya worlds/)';
        $isJson?jsonOut(false,$msg):($error=$msg);break;
      }
      if(!isset($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK){
        $ec=isset($_FILES['file'])?$_FILES['file']['error']:4;
        $em=[1=>'File terlalu besar (php.ini upload_max_filesize)',2=>'File terlalu besar (MAX_FILE_SIZE form)',3=>'File hanya terupload sebagian',4=>'Tidak ada file yang dikirim ke server',6=>'Tidak ada direktori tmp di server',7=>'Gagal menulis file ke disk (permission?)',8=>'Upload diblok oleh PHP extension'];
        $msg='Upload gagal: '.($em[$ec]??'Error tidak dikenal, kode #'.$ec);
        $isJson?jsonOut(false,$msg):($error=$msg);break;
      }
      if($_FILES['file']['size']>$uploadMaxSize){
        $msg='File terlalu besar, maksimum yang diizinkan adalah 3GB';
        $isJson?jsonOut(false,$msg):($error=$msg);break;
      }
      $origName=basename($_FILES['file']['name']);
      $dest=$fullPath.'/'.$origName;
      if(!move_uploaded_file($_FILES['file']['tmp_name'],$dest)){
        $msg='Gagal menyimpan file ke server — periksa permission folder tujuan';
        $isJson?jsonOut(false,$msg):($error=$msg);break;
      }
      $ext=strtolower(pathinfo($dest,PATHINFO_EXTENSION));
      if(in_array($ext,['zip','mcworld'])){
        [$xok,$xmsg]=doExtract($dest,$fullPath);
        if($xok){
          $msg='Upload & auto-extract berhasil: '.$xmsg;
          $isJson?jsonOut(true,$msg):($message=$msg);
        }else{
          $msg='Upload berhasil ('.$origName.') tetapi auto-extract gagal: '.$xmsg;
          $isJson?jsonOut(false,$msg):($error=$msg);
        }
      }elseif($ext==='7z'){
        $msg='Upload berhasil: '.$origName.' (format 7z tidak didukung untuk auto-extract, gunakan zip atau mcworld)';
        $isJson?jsonOut(true,$msg):($message=$msg);
      }else{
        $msg='Upload berhasil: '.$origName;
        $isJson?jsonOut(true,$msg):($message=$msg);
      }
      break;

    case 'mkdir':
      if(!isW($fullPath,$basePath,$allowedWritable)){$error='Permission Denied';break;}
      $n=preg_replace('/[^a-zA-Z0-9_\-\.]/','',$_POST['name']??'');
      if(!$n){$error='Nama folder tidak valid';break;}
      if(file_exists($fullPath.'/'.$n)){$error='Nama sudah digunakan';break;}
      @mkdir($fullPath.'/'.$n)?$message='Folder dibuat: '.$n:($error='Gagal membuat folder — cek permission');
      break;

    case 'mkfile':
      if(!isW($fullPath,$basePath,$allowedWritable)){$error='Permission Denied';break;}
      $n=preg_replace('/[^a-zA-Z0-9_\-\.]/','',$_POST['name']??'');
      if(!$n){$error='Nama file tidak valid';break;}
      if(file_exists($fullPath.'/'.$n)){$error='Nama sudah digunakan';break;}
      file_put_contents($fullPath.'/'.$n,'')!==false?$message='File dibuat: '.$n:($error='Gagal membuat file — cek permission');
      break;

    case 'delete':
      $tp=realpath($basePath.'/'.ltrim($_POST['target']??'','/'));
      if(!$tp||strpos($tp,$basePath)!==0){$error='Invalid path — akses ditolak';break;}
      if(!isW($tp,$basePath,$allowedWritable)){$error='Permission Denied: hanya worlds/ dan server.properties yang bisa dihapus';break;}
      if(is_dir($tp)){rmRec($tp);$message='Folder berhasil dihapus';}
      elseif(is_file($tp)){@unlink($tp);$message='File berhasil dihapus';}
      else{$error='Target tidak ditemukan di filesystem';}
      break;

    case 'rename':
      $op=realpath($basePath.'/'.ltrim($_POST['old']??'','/'));
      $nn=preg_replace('/[^a-zA-Z0-9_\-\.]/','',$_POST['new']??'');
      if(!$op||strpos($op,$basePath)!==0){$error='Invalid path sumber';break;}
      if(!$nn){$error='Nama baru tidak valid (gunakan huruf, angka, _, -, .)';break;}
      if(!isW(dirname($op),$basePath,$allowedWritable)&&!isW($op,$basePath,$allowedWritable)){$error='Permission Denied untuk operasi rename';break;}
      $np=dirname($op).'/'.$nn;
      if(file_exists($np)){$error='Nama "'.$nn.'" sudah ada di folder ini';break;}
      rename($op,$np)?$message='Renamed: '.basename($op).' → '.$nn:($error='Rename gagal — cek permission filesystem');
      break;

    case 'move':
      $sp=realpath($basePath.'/'.ltrim($_POST['src']??'','/'));
      $ddRaw=str_replace(['..','//'],'',$_POST['dst_dir']??'');
      $df=strlen(trim($ddRaw))?realpath($basePath.'/'.ltrim($ddRaw,'/')):$basePath;
      if(!$sp||strpos($sp,$basePath)!==0){$error='Invalid path sumber';break;}
      if(!$df||!is_dir($df)||strpos($df,$basePath)!==0){$error='Folder tujuan tidak valid atau tidak ditemukan';break;}
      if(strpos($df.'/',$sp.'/')===0){$error='Tidak bisa memindahkan folder ke dalam dirinya sendiri';break;}
      if($df===dirname($sp)){$error='File sudah berada di folder tersebut';break;}
      if(!isW($df,$basePath,$allowedWritable)&&!isW($sp,$basePath,$allowedWritable)){$error='Permission Denied: sumber atau tujuan harus berada di worlds/ atau server.properties';break;}
      $np=$df.'/'.basename($sp);
      if(file_exists($np)){$error='Nama yang sama sudah ada di folder tujuan';break;}
      if(rename($sp,$np)){
        $nd=ltrim(substr(dirname($np),strlen($basePath)),'/');
        safeRedirect('dir='.urlencode($nd).'&msg='.urlencode('Dipindahkan ke: /'.($nd?$nd.'/':'').basename($np)));
      }else{$error='Move gagal — cek permission filesystem';}
      break;

    case 'unzip':
      if(!isW($fullPath,$basePath,$allowedWritable)){$error='Permission Denied untuk extract di direktori ini';break;}
      $tp=realpath($basePath.'/'.ltrim($_POST['file']??'','/'));
      if(!$tp||strpos($tp,$basePath)!==0){$error='Invalid path file zip';break;}
      [$xok,$xmsg]=doExtract($tp,$fullPath);
      $xok?($message=$xmsg):($error=$xmsg);
      break;

    case 'savefile':
      $tp=realpath($basePath.'/'.ltrim($_POST['target']??'','/'));
      if(!$tp||strpos($tp,$basePath)!==0){$error='Invalid path file';break;}
      file_put_contents($tp,$_POST['content']??'')!==false?$message='File berhasil disimpan':($error='Gagal menyimpan file — cek permission');
      break;
  }
}

if(isset($_GET['msg'])&&empty($message))$message=htmlspecialchars(urldecode($_GET['msg']));

function getAllDirs($base,$cur='',$depth=0){
  if($depth>6)return[];
  $out=[];
  $path=$base.($cur?'/'.$cur:'');
  if(!is_dir($path))return[];
  $items=@scandir($path);
  if(!$items)return[];
  sort($items);
  foreach($items as $i){
    if($i==='.'||$i==='..') continue;
    $fp=$path.'/'.$i;
    if(is_dir($fp)){
      $rel=$cur?$cur.'/'.$i:$i;
      $out[]=['rel'=>$rel,'label'=>str_repeat('  ',$depth).'📁 '.$rel];
      $out=array_merge($out,getAllDirs($base,$rel,$depth+1));
    }
  }
  return $out;
}
$allDirsJson=json_encode(array_values(array_map(fn($d)=>['rel'=>$d['rel'],'label'=>$d['label']],getAllDirs($basePath))));

$dirs=[];$files=[];
if($dh=@opendir($fullPath)){
  while(($f=readdir($dh))!==false){
    if($f==='.'||$f==='..') continue;
    is_dir($fullPath.'/'.$f)?$dirs[]=$f:$files[]=$f;
  }
  closedir($dh);
}
sort($dirs);sort($files);

$pageUrl=($isHttps?'https':'http').'://'.$_SERVER['HTTP_HOST'].strtok($_SERVER['REQUEST_URI'],'?');
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Console</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#1a1a1a;color:#fff;font-family:'Courier New',monospace;padding:20px}
.hdr{background:#000;border:3px solid #fff;padding:18px 20px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
.hdr h1{text-transform:uppercase;letter-spacing:2px;font-size:22px}
.logout{background:#FFE66D;color:#000;padding:9px 14px;border:2px solid #000;font-weight:bold;text-decoration:none;font-family:monospace;font-size:13px}
.logout:hover{background:#ffd700}
.msg{background:#7ED321;border:3px solid #000;padding:14px;margin:10px 0;font-weight:bold;color:#000}
.errd{background:#FF6B6B;border:3px solid #000;padding:14px;margin:10px 0;font-weight:bold;color:#000}
.bc{background:#2a2a2a;padding:13px;border:2px solid #444;margin-bottom:8px;word-break:break-all}
.bc a{color:#4ECDC4;text-decoration:none;margin:0 3px}
.bc a:hover{text-decoration:underline}
.dev{background:#111;border:1px solid #2a2a2a;padding:7px 15px;margin-bottom:15px;font-size:11px;color:#555}
.dev span{color:#4ECDC4}
.ctrl{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:18px}
.btn{padding:11px;background:#4ECDC4;border:3px solid #000;font-family:monospace;font-weight:bold;cursor:pointer;text-transform:uppercase;text-align:center;text-decoration:none;display:inline-block;font-size:13px}
.btn:hover{transform:translate(2px,2px);box-shadow:4px 4px 0 #000}
.btn-ok{background:#7ED321;color:#000}
.dz{border:3px dashed #4ECDC4;padding:36px 20px;text-align:center;cursor:pointer;background:#222;margin-bottom:18px;transition:background .2s,border-color .2s;position:relative}
.dz.ov{background:#0d2a2a;border-color:#7ED321}
.dz p{margin-bottom:6px;font-size:15px;pointer-events:none}
.dz small{color:#888;font-size:11px;pointer-events:none;display:block;margin-top:4px}
.pw{display:none;margin-top:14px;pointer-events:none}
.pb{width:100%;height:22px;background:#333;border:2px solid #fff;overflow:hidden}
.pf{height:100%;background:#7ED321;width:0%;transition:width .12s}
.pt{text-align:center;font-size:12px;color:#aaa;margin-top:4px}
.notif{display:none;padding:14px;margin:10px 0;border:3px solid #000;font-weight:bold;font-size:14px}
.notif.ok{background:#7ED321;color:#000}
.notif.fail{background:#FF6B6B;color:#000}
.files{display:grid;grid-template-columns:repeat(auto-fill,minmax(255px,1fr));gap:14px}
.fi{background:#2a2a2a;border:2px solid #3a3a3a;padding:14px;display:flex;flex-direction:column;transition:border-color .2s}
.fi:hover{border-color:#555}
.fn{font-weight:bold;margin-bottom:9px;word-break:break-all;font-size:13px}
.fi-info{font-size:11px;color:#777;margin-bottom:9px}
.fa{display:flex;gap:5px;flex-wrap:wrap}
.bs{padding:5px 9px;font-size:11px;border:2px solid #000;cursor:pointer;flex:1;min-width:46px;text-align:center;text-decoration:none;display:inline-block;font-family:monospace;background:#4ECDC4;color:#000;font-weight:bold}
.bs:hover{filter:brightness(1.15)}
.del{background:#FF6B6B;color:#000}
.ed{background:#FFE66D;color:#000}
.dl{background:#7ED321;color:#000}
.mv{background:#c678dd;color:#fff}
.lk{background:#3a3a3a;color:#555;cursor:not-allowed;border-color:#3a3a3a}
.empty{text-align:center;padding:50px;color:#555;font-size:15px}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.88);justify-content:center;align-items:center;z-index:1000}
.mc{background:#222;border:3px solid #fff;padding:28px;max-width:430px;width:93%;max-height:82vh;overflow-y:auto}
.mc h3{margin-bottom:14px;text-transform:uppercase;font-size:16px}
.mc input,.mc select{width:100%;padding:9px;margin-bottom:14px;background:#1a1a1a;border:2px solid #444;color:#fff;font-family:monospace;font-size:13px}
.mc button{width:100%;padding:10px;margin-bottom:9px;border:2px solid #000;font-family:monospace;font-weight:bold;cursor:pointer;font-size:14px;background:#4ECDC4;color:#000}
.mc button:hover{filter:brightness(1.1)}
.mc .cn{background:#FF6B6B;color:#000}
.mc label{color:#aaa;font-size:11px;display:block;margin-bottom:5px}
.mc select option{background:#1a1a1a;color:#fff}
</style>
</head>
<body>

<div class="hdr">
  <h1>&gt; CONSOLE_</h1>
  <a href="?logout=1" class="logout">LOGOUT</a>
</div>

<?php if($message):?><div class="msg">✓ <?=htmlspecialchars($message)?></div><?php endif?>
<?php if($error):?><div class="errd">✗ <?=htmlspecialchars($error)?></div><?php endif?>

<div class="bc">
  Location: <a href="?" style="color:#fff;font-weight:bold">/mcserver</a>
  <?php if($currentDir){
    $parts=explode('/',$currentDir);
    foreach($parts as $i=>$pt){
      if(!$pt)continue;
      $rp=implode('/',array_slice($parts,0,$i+1));
      echo ' / <a href="?dir='.urlencode($rp).'">'.htmlspecialchars($pt).'</a>';
    }
  }?>
</div>

<div class="dev">For Developer : <span>RIZZXD</span> &nbsp;|&nbsp; WA : <span>081224595908</span></div>

<div class="ctrl">
  <button class="btn btn-ok" onclick="showModal('folder')">+ FOLDER</button>
  <button class="btn btn-ok" onclick="showModal('file')">+ FILE</button>
</div>

<div class="dz" id="dz">
  <p>📥 Drag &amp; drop atau klik untuk upload</p>
  <small>Max 3GB &nbsp;·&nbsp; ZIP &amp; MCWORLD = upload lalu auto-extract &amp; hapus zip &nbsp;·&nbsp; 7z = upload saja</small>
  <input type="file" id="fi" style="display:none" tabindex="-1">
  <div class="pw" id="pw">
    <div class="pb"><div class="pf" id="pf"></div></div>
    <div class="pt" id="pt">Uploading...</div>
  </div>
</div>

<div class="notif" id="notif"></div>

<?php if(empty($dirs)&&empty($files)):?>
<div class="empty">📂 Direktori kosong</div>
<?php else:?>
<div class="files">

<?php foreach($dirs as $dir):
  $p=$currentDir?$currentDir.'/'.$dir:$dir;
  $fw=isW($fullPath.'/'.$dir,$basePath,$allowedWritable);
?>
<div class="fi">
  <div class="fn">📁 <?=htmlspecialchars($dir)?>/</div>
  <div class="fi-info">Folder</div>
  <div class="fa">
    <a href="?dir=<?=urlencode($p)?>" class="bs">OPEN</a>
    <button class="bs" onclick="renameItem('<?=addslashes($p)?>','<?=addslashes($dir)?>')">REN</button>
    <button class="bs mv" onclick="moveItem('<?=addslashes($p)?>','<?=addslashes($dir)?>')">MOVE</button>
    <?php if($fw):?>
    <form method="POST" action="" style="display:contents" onsubmit="return confirm('Hapus folder <?=htmlspecialchars($dir)?> beserta seluruh isinya?')">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="target" value="<?=htmlspecialchars($p)?>">
      <button type="submit" class="bs del">DEL</button>
    </form>
    <?php else:?>
    <button class="bs lk" disabled title="Hanya worlds/ yang bisa dihapus">LOCKED</button>
    <?php endif?>
  </div>
</div>
<?php endforeach?>

<?php foreach($files as $file):
  $p=$currentDir?$currentDir.'/'.$file:$file;
  $fullFp=$fullPath.'/'.$file;
  $fw=isW($fullFp,$basePath,$allowedWritable);
  $sz=fmtSz(filesize($fullFp));
  $ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));
  $isZip=in_array($ext,['zip','mcworld']);
  $is7z=($ext==='7z');
?>
<div class="fi">
  <div class="fn"><?=$isZip||$is7z?'📦':'📄'?> <?=htmlspecialchars($file)?></div>
  <div class="fi-info"><?=$sz?></div>
  <div class="fa">
    <a href="?edit=<?=urlencode($p)?>&amp;dir=<?=urlencode($currentDir)?>" class="bs ed">EDIT</a>
    <a href="?download=<?=urlencode($p)?>&amp;dir=<?=urlencode($currentDir)?>" class="bs dl">DL</a>
    <button class="bs" onclick="renameItem('<?=addslashes($p)?>','<?=addslashes($file)?>')">REN</button>
    <button class="bs mv" onclick="moveItem('<?=addslashes($p)?>','<?=addslashes($file)?>')">MOVE</button>
    <?php if($fw):?>
    <form method="POST" action="" style="display:contents" onsubmit="return confirm('Hapus file <?=htmlspecialchars($file)?>?')">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="target" value="<?=htmlspecialchars($p)?>">
      <button type="submit" class="bs del">DEL</button>
    </form>
    <?php if($isZip):?>
    <form method="POST" action="" style="display:contents" onsubmit="return confirm('Extract <?=htmlspecialchars($file)?> ke folder ini?\nZip akan dihapus setelah extract.')">
      <input type="hidden" name="action" value="unzip">
      <input type="hidden" name="file" value="<?=htmlspecialchars($p)?>">
      <button type="submit" class="bs">UNZIP</button>
    </form>
    <?php endif?>
    <?php else:?>
    <button class="bs lk" disabled title="Hanya worlds/ &amp; server.properties yang bisa dihapus">LOCKED</button>
    <?php endif?>
  </div>
</div>
<?php endforeach?>

</div>
<?php endif?>

<div class="modal" id="modal">
  <div class="mc">
    <h3 id="mt">New Item</h3>
    <form method="POST" action="">
      <input type="hidden" name="action" id="ma">
      <label>Nama (huruf, angka, _, -, .)</label>
      <input type="text" name="name" id="mi" placeholder="Nama..." required autocomplete="off">
      <button type="submit">CREATE</button>
      <button type="button" class="cn" onclick="cm('modal')">CANCEL</button>
    </form>
  </div>
</div>

<div class="modal" id="renameModal">
  <div class="mc">
    <h3>Rename</h3>
    <form method="POST" action="">
      <input type="hidden" name="action" value="rename">
      <input type="hidden" name="old" id="ro">
      <label>Nama baru (huruf, angka, _, -, .)</label>
      <input type="text" name="new" id="rn" placeholder="Nama baru..." required autocomplete="off">
      <button type="submit">RENAME</button>
      <button type="button" class="cn" onclick="cm('renameModal')">CANCEL</button>
    </form>
  </div>
</div>

<div class="modal" id="moveModal">
  <div class="mc">
    <h3>Pindahkan File/Folder</h3>
    <form method="POST" action="">
      <input type="hidden" name="action" value="move">
      <input type="hidden" name="src" id="ms">
      <label>Pindahkan <strong id="mn" style="color:#4ECDC4"></strong> ke:</label>
      <select name="dst_dir" id="md">
        <option value="">/mcserver (root)</option>
      </select>
      <button type="submit">PINDAHKAN</button>
      <button type="button" class="cn" onclick="cm('moveModal')">CANCEL</button>
    </form>
  </div>
</div>

<script>
var currentDir='<?=addslashes($currentDir)?>';
var allDirs=<?=$allDirsJson?>;
var maxSize=<?=$uploadMaxSize?>;
var pageUrl='<?=addslashes($pageUrl)?>';
var _busy=false;

var dz=document.getElementById('dz');
var fi=document.getElementById('fi');
var pw=document.getElementById('pw');
var pf=document.getElementById('pf');
var pt=document.getElementById('pt');
var notif=document.getElementById('notif');

function showNotif(msg,ok,dur){
  notif.className='notif '+(ok?'ok':'fail');
  notif.textContent=(ok?'✓ ':'✗ ')+msg;
  notif.style.display='block';
  clearTimeout(notif._tid);
  notif._tid=setTimeout(function(){notif.style.display='none';},dur||8000);
}

dz.addEventListener('dragover',function(e){e.preventDefault();if(!_busy)dz.classList.add('ov');});
dz.addEventListener('dragleave',function(){dz.classList.remove('ov');});
dz.addEventListener('drop',function(e){
  e.preventDefault();dz.classList.remove('ov');
  if(_busy){showNotif('Sedang upload, tunggu selesai.',false);return;}
  var f=e.dataTransfer.files[0];
  if(f)doUpload(f);
});
dz.addEventListener('click',function(e){
  if(e.target===fi||_busy)return;
  fi.value='';
  fi.click();
});
fi.addEventListener('change',function(e){
  var f=e.target.files[0];
  if(f)doUpload(f);
});

function doUpload(file){
  if(_busy){showNotif('Sedang upload, tunggu selesai.',false);return;}
  if(file.size>maxSize){showNotif('File terlalu besar! Maksimum 3GB.',false,10000);return;}
  var ext=(file.name.split('.').pop()||'').toLowerCase();
  var isArchive=(ext==='zip'||ext==='mcworld'||ext==='7z');
  _busy=true;
  notif.style.display='none';
  pw.style.display='block';
  pf.style.width='0%';
  pt.textContent='Mempersiapkan: '+file.name;
  var fd=new FormData();
  fd.append('action','upload');
  fd.append('file',file);
  var xhr=new XMLHttpRequest();
  xhr.upload.addEventListener('progress',function(e){
    if(!e.lengthComputable)return;
    var p=Math.round(e.loaded/e.total*100);
    pf.style.width=p+'%';
    var extra=(isArchive&&p===100)?' — Mengekstrak, mohon tunggu...':'';
    pt.textContent=p+'% — '+fmtB(e.loaded)+' / '+fmtB(e.total)+extra;
  });
  xhr.addEventListener('load',function(){
    _busy=false;
    pw.style.display='none';
    fi.value='';
    var ok=false,msg='Selesai';
    try{
      var res=JSON.parse(xhr.responseText);
      ok=!!res.ok;
      msg=res.msg||msg;
    }catch(ex){
      ok=(xhr.status>=200&&xhr.status<300);
      msg=ok?'Upload selesai':'Server error (HTTP '+xhr.status+')';
    }
    showNotif(msg,ok,ok?8000:12000);
    if(ok)setTimeout(function(){location.reload();},2500);
  });
  xhr.addEventListener('error',function(){
    _busy=false;pw.style.display='none';fi.value='';
    showNotif('Upload gagal — periksa koneksi internet atau cek log server.',false,12000);
  });
  xhr.addEventListener('abort',function(){
    _busy=false;pw.style.display='none';fi.value='';
    showNotif('Upload dibatalkan.',false);
  });
  xhr.open('POST',pageUrl+'?dir='+encodeURIComponent(currentDir)+'&json=1');
  xhr.send(fd);
}

function fmtB(b){
  if(b>1073741824)return(b/1073741824).toFixed(1)+' GB';
  if(b>1048576)return(b/1048576).toFixed(1)+' MB';
  return(b/1024).toFixed(0)+' KB';
}

function showModal(t){
  var el=document.getElementById('modal');
  document.getElementById('ma').value=t==='folder'?'mkdir':'mkfile';
  document.getElementById('mt').textContent=t==='folder'?'Buat Folder':'Buat File';
  document.getElementById('mi').value='';
  el.style.display='flex';
  setTimeout(function(){document.getElementById('mi').focus();},80);
}

function cm(id){document.getElementById(id).style.display='none';}

function renameItem(path,name){
  document.getElementById('ro').value=path;
  document.getElementById('rn').value=name;
  document.getElementById('renameModal').style.display='flex';
  setTimeout(function(){document.getElementById('rn').focus();},80);
}

function moveItem(path,name){
  document.getElementById('ms').value=path;
  document.getElementById('mn').textContent=name;
  var sel=document.getElementById('md');
  sel.innerHTML='<option value="">/mcserver (root)</option>';
  allDirs.forEach(function(d){
    if(d.rel===path||d.rel.indexOf(path+'/')===0)return;
    var o=document.createElement('option');
    o.value=d.rel;
    o.textContent=d.label;
    sel.appendChild(o);
  });
  document.getElementById('moveModal').style.display='flex';
}

document.addEventListener('keydown',function(e){
  if(e.key==='Escape'){cm('modal');cm('renameModal');cm('moveModal');}
});
window.addEventListener('click',function(e){
  ['modal','renameModal','moveModal'].forEach(function(id){
    var el=document.getElementById(id);
    if(e.target===el)cm(id);
  });
});
</script>
</body>
</html>
