<?php
$isHttps=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||(isset($_SERVER['HTTP_X_FORWARDED_PROTO'])&&$_SERVER['HTTP_X_FORWARDED_PROTO']==='https')||(isset($_SERVER['HTTP_X_FORWARDED_SSL'])&&$_SERVER['HTTP_X_FORWARDED_SSL']==='on')||($_SERVER['SERVER_PORT']??80)==443;
session_set_cookie_params(['lifetime'=>60*60*24*365,'path'=>'/','domain'=>'','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
session_start();
$basePath=realpath('/home/ubuntu/mcserver');if(!$basePath)exit('Invalid base path');
$allowedWritable=['worlds','server.properties'];
$uploadMaxSize=3*1024*1024*1024;
$username='koba';$password='console';
function safeRedirect($qs=''){global $isHttps;$s=$isHttps?'https':'http';$h=$_SERVER['HTTP_HOST'];$p=strtok($_SERVER['REQUEST_URI'],'?');header('Location: '.$s.'://'.$h.$p.($qs?'?'.$qs:''),true,302);exit;}
if(isset($_GET['logout'])){session_destroy();setcookie(session_name(),'',time()-3600,'/');safeRedirect();}
if(!isset($_SESSION['auth'])){
  $loginError='';
  if(isset($_POST['u'])&&isset($_POST['p'])){
    if($_POST['u']===$username&&$_POST['p']===$password){$_SESSION['auth']=true;$_SESSION['uid']=bin2hex(random_bytes(8));safeRedirect();}
    else{$loginError='Username atau password salah';}
  }
?><!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Console Login</title><style>*{margin:0;padding:0;box-sizing:border-box}body{background:#FF6B6B;font-family:'Courier New',monospace;display:flex;justify-content:center;align-items:center;height:100vh}.login-box{background:#FFE66D;border:4px solid #000;padding:40px;box-shadow:8px 8px 0 #000;max-width:400px;width:90%}h1{font-size:28px;margin-bottom:20px;text-transform:uppercase;letter-spacing:2px;border-bottom:4px solid #000;padding-bottom:10px}input[type=text],input[type=password]{width:100%;padding:12px;margin:10px 0;border:3px solid #000;font-family:inherit;font-size:16px;background:#fff;box-shadow:4px 4px 0 #000}button{width:100%;padding:15px;background:#4ECDC4;border:3px solid #000;font-family:inherit;font-size:18px;font-weight:bold;cursor:pointer;box-shadow:4px 4px 0 #000;text-transform:uppercase;margin-top:10px}button:hover{transform:translate(2px,2px);box-shadow:2px 2px 0 #000}.err{background:#FF6B6B;border:3px solid #000;padding:10px;margin-bottom:15px;font-weight:bold}.note{font-size:11px;color:#333;margin-top:12px;border-top:2px solid #000;padding-top:10px}</style></head><body><div class="login-box"><h1>&gt; CONSOLE_</h1><?php if($loginError):?><div class="err">! <?=htmlspecialchars($loginError)?></div><?php endif?><form method="POST"><input type="text" name="u" placeholder="Username" required autofocus><input type="password" name="p" placeholder="Password" required><button type="submit">LOGIN</button></form><p class="note">Dengan login, sesi Anda akan disimpan selama 1 tahun (cookie permanen).</p></div></body></html><?php exit;}

function isW($path,$base,$allowed){$r=realpath($path);if($r===false){$r=realpath(dirname($path));if($r===false||strpos($r,$base)!==0)return false;$rel=ltrim(substr($r,strlen($base)),'/').'/'.basename($path);}else{if(strpos($r,$base)!==0)return false;$rel=ltrim(substr($r,strlen($base)),'/');}if(empty($rel))return false;foreach($allowed as $a){if($rel===$a||strpos($rel,$a.'/')===0||strpos($rel,$a)===0)return true;}return false;}
function fmtSize($b){return $b>1073741824?round($b/1073741824,2).' GB':($b>1048576?round($b/1048576,2).' MB':round($b/1024,2).' KB');}
function rmRec($dir){if(!is_dir($dir))return;foreach(scandir($dir) as $i){if($i==='.'||$i==='..') continue;$p=$dir.'/'.$i;is_dir($p)?rmRec($p):unlink($p);}rmdir($dir);}
function doAutoUnzip($realTarget,$fullPath,$basePath,$allowedWritable){if(!extension_loaded('zip'))return['err'=>'PHP zip extension tidak tersedia'];$ext=strtolower(pathinfo($realTarget,PATHINFO_EXTENSION));if(!in_array($ext,['zip','mcworld']))return['err'=>'Hanya zip/mcworld yang bisa di-extract otomatis'];$zip=new ZipArchive();if($zip->open($realTarget)!==true)return['err'=>'Gagal membuka archive'];$totalUncomp=0;for($i=0;$i<$zip->numFiles;$i++){$s=$zip->statIndex($i);$totalUncomp+=$s['size'];}if($totalUncomp>9*1024*1024*1024){$zip->close();return['err'=>'Extract akan melebihi 9GB (Anti-Zip Bomb)'];}$rootNames=[];for($i=0;$i<$zip->numFiles;$i++){$n=$zip->getNameIndex($i);$first=explode('/',$n)[0];if(!empty($first))$rootNames[$first]=true;}if(count($rootNames)>30){$zip->close();return['err'=>'Dibatalkan: '.count($rootNames).' root item terdeteksi — terlalu banyak file di root zip'];}$zip->extractTo($fullPath);$zip->close();unlink($realTarget);return['ok'=>'Archive diekstrak & file dihapus ('.count($rootNames).' root item)'];}

$currentDir=isset($_GET['dir'])?$_GET['dir']:'';
$fullPath=realpath($basePath.($currentDir?'/'.$currentDir:''));
if($fullPath===false||strpos($fullPath,$basePath)!==0){$fullPath=$basePath;$currentDir='';}
$message='';$error='';

if(isset($_GET['download'])){$t=realpath($basePath.'/'.ltrim($_GET['download'],'/'));if(!$t||!is_file($t)||strpos($t,$basePath)!==0)exit('Invalid');header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.basename($t).'"');header('Content-Length: '.filesize($t));readfile($t);exit;}

if(isset($_GET['edit'])){$t=realpath($basePath.'/'.ltrim($_GET['edit'],'/'));if(!$t||!is_file($t)||strpos($t,$basePath)!==0)exit('Invalid');$ec=file_get_contents($t);$eDir=ltrim(substr(dirname($t),strlen($basePath)),'/');?><!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit - <?=htmlspecialchars(basename($t))?></title><style>*{margin:0;padding:0;box-sizing:border-box}body{background:#2a2a2a;color:#fff;font-family:monospace;padding:20px}textarea{width:100%;padding:15px;background:#1e1e1e;color:#00ff00;border:2px solid #444;font-family:monospace;font-size:14px;min-height:400px;resize:vertical}button{padding:10px 20px;border:2px solid #000;font-weight:bold;cursor:pointer;margin-right:10px;margin-top:10px}.sv{background:#7ED321}.bk{background:#FF6B6B;color:#fff}h1{margin-bottom:5px}p{color:#aaa;font-size:12px;margin-bottom:15px}a{text-decoration:none}</style></head><body><h1><?=htmlspecialchars(basename($t))?></h1><p><?=number_format(strlen($ec))?> bytes | <?=count(explode("\n",$ec))?> lines | <?=htmlspecialchars($t)?></p><form method="POST" action="?dir=<?=urlencode($eDir)?>"><input type="hidden" name="action" value="savefile"><input type="hidden" name="target" value="<?=htmlspecialchars($_GET['edit'])?>"><textarea name="content"><?=htmlspecialchars($ec)?></textarea><br><button type="submit" class="sv">SAVE</button><a href="?dir=<?=urlencode($eDir)?>"><button type="button" class="bk">BACK</button></a></form></body></html><?php exit;}

if(isset($_POST['action'])){
  switch($_POST['action']){
    case 'upload':
      if(!isW($fullPath,$basePath,$allowedWritable)){$error='Permission Denied: Hanya worlds/ yang bisa di-upload';break;}
      if(!isset($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK){
        $emap=[1=>'Terlalu besar (php.ini)',2=>'Terlalu besar (form)',3=>'Upload tidak lengkap',4=>'Tidak ada file',6=>'No tmp dir',7=>'Gagal tulis disk',8=>'Diblok extension'];
        $error='Upload gagal: '.($emap[$_FILES['file']['error']??4]??'Error #'.($_FILES['file']['error']??'?'));break;
      }
      if($_FILES['file']['size']>$uploadMaxSize){$error='File terlalu besar (Max 3GB)';break;}
      $dest=$fullPath.'/'.basename($_FILES['file']['name']);
      if(!move_uploaded_file($_FILES['file']['tmp_name'],$dest)){$error='Gagal menyimpan file — cek permission folder';break;}
      $ext=strtolower(pathinfo($dest,PATHINFO_EXTENSION));
      if(in_array($ext,['zip','7z','mcworld'])){
        if($ext==='7z'){$message='Upload berhasil: '.basename($dest).' (7z — extract manual karena PHP tidak support 7z native)';}
        else{$r=doAutoUnzip($dest,$fullPath,$basePath,$allowedWritable);if(isset($r['ok'])){$message='Upload berhasil & auto-extract: '.$r['ok'];}else{$error='Upload berhasil tapi extract gagal: '.$r['err'];}}
      }else{$message='Upload berhasil: '.basename($dest);}
      break;
    case 'mkdir':
      if(!isW($fullPath,$basePath,$allowedWritable)){$error='Permission Denied';break;}
      $n=preg_replace('/[^a-zA-Z0-9_\-\.]/','',$_POST['name']??'');
      if($n&&mkdir($fullPath.'/'.$n)){$message='Folder dibuat: '.$n;}else{$error='Gagal buat folder';}
      break;
    case 'mkfile':
      if(!isW($fullPath,$basePath,$allowedWritable)){$error='Permission Denied';break;}
      $n=preg_replace('/[^a-zA-Z0-9_\-\.]/','',$_POST['name']??'');
      if($n&&file_put_contents($fullPath.'/'.$n,'')!==false){$message='File dibuat: '.$n;}else{$error='Gagal buat file';}
      break;
    case 'delete':
      $tp=realpath($basePath.'/'.ltrim($_POST['target']??'','/'));
      if(!$tp||strpos($tp,$basePath)!==0){$error='Invalid path';break;}
      if(!isW($tp,$basePath,$allowedWritable)){$error='Permission Denied: hanya worlds/ & server.properties';break;}
      if(is_dir($tp)){rmRec($tp);$message='Folder dihapus';}elseif(is_file($tp)){unlink($tp);$message='File dihapus';}else{$error='Target tidak ditemukan';}
      break;
    case 'rename':
      $op=realpath($basePath.'/'.ltrim($_POST['old']??'','/'));
      $nn=preg_replace('/[^a-zA-Z0-9_\-\.]/','',$_POST['new']??'');
      if(!$op||strpos($op,$basePath)!==0){$error='Invalid path';break;}
      if(!$nn){$error='Nama baru tidak valid';break;}
      if(!isW(dirname($op),$basePath,$allowedWritable)&&!isW($op,$basePath,$allowedWritable)){$error='Permission Denied';break;}
      $np=dirname($op).'/'.$nn;
      if(file_exists($np)){$error='Nama sudah ada';break;}
      rename($op,$np)?$message='Renamed: '.basename($op).' → '.$nn:($error='Rename gagal');
      break;
    case 'move':
      $sp=realpath($basePath.'/'.ltrim($_POST['src']??'','/'));
      $dd=str_replace(['..','//'],'',$_POST['dst_dir']??'');
      $df=realpath($basePath.'/'.ltrim($dd,'/'));
      if(!$sp||strpos($sp,$basePath)!==0){$error='Invalid src';break;}
      if(!$df||!is_dir($df)||strpos($df,$basePath)!==0){$error='Invalid dst';break;}
      if(strpos($df.'/',$sp.'/')===0){$error='Tidak bisa move ke dalam dirinya sendiri';break;}
      if(!isW($df,$basePath,$allowedWritable)&&!isW($sp,$basePath,$allowedWritable)){$error='Permission Denied';break;}
      $np=$df.'/'.basename($sp);
      if(file_exists($np)){$error='Nama sudah ada di tujuan';break;}
      if(rename($sp,$np)){$nd=ltrim(substr(dirname($np),strlen($basePath)),'/');safeRedirect('dir='.urlencode($nd).'&msg='.urlencode('Dipindahkan ke: /'.($nd?$nd.'/':'').basename($np)));}
      else{$error='Move gagal';}
      break;
    case 'unzip':
      if(!isW($fullPath,$basePath,$allowedWritable)){$error='Permission Denied';break;}
      $tp=realpath($basePath.'/'.ltrim($_POST['file']??'','/'));
      if(!$tp||strpos($tp,$basePath)!==0){$error='Invalid path';break;}
      $r=doAutoUnzip($tp,$fullPath,$basePath,$allowedWritable);
      isset($r['ok'])?$message=$r['ok']:($error=$r['err']);
      break;
    case 'savefile':
      $tp=realpath($basePath.'/'.ltrim($_POST['target']??'','/'));
      if(!$tp||strpos($tp,$basePath)!==0){$error='Invalid path';break;}
      file_put_contents($tp,$_POST['content']??'')!==false?$message='File tersimpan':($error='Gagal simpan');
      break;
  }
}
if(isset($_GET['json'])&&$_GET['json']==='1'){
  header('Content-Type: application/json');
  if($error){echo json_encode(['ok'=>false,'msg'=>$error]);}
  else{echo json_encode(['ok'=>true,'msg'=>$message]);}
  exit;
}
if(isset($_GET['msg'])&&empty($message))$message=htmlspecialchars($_GET['msg']);

function getAllDirs($base,$cur='',$depth=0){if($depth>5)return[];$dirs=[];$path=$base.($cur?'/'.$cur:'');if(!is_dir($path))return[];foreach(scandir($path) as $i){if($i==='.'||$i==='..') continue;$full=$path.'/'.$i;if(is_dir($full)){$rel=$cur?$cur.'/'.$i:$i;$dirs[]=['rel'=>$rel,'label'=>str_repeat('  ',$depth).'📁 '.$rel];$dirs=array_merge($dirs,getAllDirs($base,$rel,$depth+1));}}return $dirs;}
$allDirsJson=json_encode(array_map(fn($d)=>['rel'=>$d['rel'],'label'=>$d['label']],getAllDirs($basePath)));
?><!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Console</title><style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#1a1a1a;color:#fff;font-family:monospace;padding:20px}
.hdr{background:#000;border:3px solid #fff;padding:20px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
.hdr h1{text-transform:uppercase;letter-spacing:2px;font-size:24px}
.logout{background:#FFE66D;color:#000;padding:10px 15px;border:2px solid #000;font-weight:bold;text-decoration:none;cursor:pointer;font-family:monospace}
.msg{background:#7ED321;border:3px solid #000;padding:15px;margin:10px 0;font-weight:bold;color:#000}
.err{background:#FF6B6B;border:3px solid #000;padding:15px;margin:10px 0;font-weight:bold;color:#000}
.bc{background:#2a2a2a;padding:15px;border:2px solid #444;margin-bottom:8px}
.bc a{color:#4ECDC4;text-decoration:none;margin:0 4px}
.dev{background:#111;border:1px solid #333;padding:7px 15px;margin-bottom:15px;font-size:11px;color:#555}
.dev span{color:#4ECDC4}
.ctrl{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:20px}
.btn{padding:12px;background:#4ECDC4;border:3px solid #000;font-family:monospace;font-weight:bold;cursor:pointer;text-transform:uppercase;text-align:center;text-decoration:none;display:inline-block}
.btn:hover{transform:translate(2px,2px);box-shadow:4px 4px 0 #000}
.btn-ok{background:#7ED321;color:#000}
.dz{border:3px dashed #4ECDC4;padding:40px;text-align:center;cursor:pointer;background:#2a2a2a;margin-bottom:20px;transition:all .3s}
.dz.ov{background:#1a3a3a;border-color:#7ED321}
.dz p{margin-bottom:8px;pointer-events:none}
.dz small{color:#aaa;pointer-events:none}
.pw{display:none;margin-top:15px}
.pb{width:100%;height:22px;background:#333;border:2px solid #fff;overflow:hidden}
.pf{height:100%;background:#7ED321;width:0%;transition:width .15s}
.pt{text-align:center;font-size:12px;color:#aaa;margin-top:4px}
.notif{display:none;padding:15px;margin:10px 0;border:3px solid #000;font-weight:bold}
.notif.ok{background:#7ED321;color:#000}
.notif.fail{background:#FF6B6B;color:#000}
.files{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:15px}
.fi{background:#2a2a2a;border:2px solid #444;padding:15px;display:flex;flex-direction:column}
.fn{font-weight:bold;margin-bottom:10px;word-break:break-all}
.fi-info{font-size:12px;color:#aaa;margin-bottom:10px}
.fa{display:flex;gap:5px;flex-wrap:wrap}
.bs{padding:6px 10px;font-size:11px;border:2px solid #000;cursor:pointer;flex:1;min-width:50px;text-align:center;text-decoration:none;display:inline-block;font-family:monospace}
.bs{background:#4ECDC4}
.del{background:#FF6B6B}
.ed{background:#FFE66D;color:#000}
.dl{background:#7ED321;color:#000}
.mv{background:#c678dd;color:#fff}
.lk{background:#555;color:#888;cursor:not-allowed;border-color:#555}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.85);justify-content:center;align-items:center;z-index:1000}
.mc{background:#2a2a2a;border:3px solid #fff;padding:30px;max-width:420px;width:90%;max-height:80vh;overflow-y:auto}
.mc h3{margin-bottom:15px;text-transform:uppercase}
.mc input,.mc select{width:100%;padding:10px;margin-bottom:15px;background:#1a1a1a;border:2px solid #444;color:#fff;font-family:monospace}
.mc button{width:100%;padding:10px;margin-bottom:10px;background:#4ECDC4;border:2px solid #000;font-family:monospace;font-weight:bold;cursor:pointer;font-size:14px}
.mc .cn{background:#FF6B6B}
.mc label{color:#aaa;font-size:12px;display:block;margin-bottom:5px}
</style></head><body>
<div class="hdr"><h1>&gt; CONSOLE_</h1><a href="?logout=1" class="logout">LOGOUT</a></div>
<?php if($message):?><div class="msg">✓ <?=htmlspecialchars($message)?></div><?php endif?>
<?php if($error):?><div class="err">✗ <?=htmlspecialchars($error)?></div><?php endif?>
<div class="bc">Location: <a href="?" style="color:#fff;font-weight:bold">/mcserver</a><?php if($currentDir){$parts=explode('/',$currentDir);foreach($parts as $i=>$p){if(!$p)continue;$rp=implode('/',array_slice($parts,0,$i+1));echo ' / <a href="?dir='.urlencode($rp).'">'.htmlspecialchars($p).'</a>';}}</div>
<div class="dev">For Developer : <span>RIZZXD</span> &nbsp;|&nbsp; WA : <span>081224595908</span></div>
<div class="ctrl">
  <button class="btn btn-ok" onclick="showModal('folder')">+ FOLDER</button>
  <button class="btn btn-ok" onclick="showModal('file')">+ FILE</button>
</div>
<div class="dz" id="dz">
  <p>📥 Drag & drop atau klik untuk upload</p>
  <small>Max 3GB | ZIP/MCWORLD = auto-extract & dihapus | 7z = upload saja</small>
  <input type="file" id="fi" style="display:none">
  <div class="pw" id="pw"><div class="pb"><div class="pf" id="pf"></div></div><div class="pt" id="pt"></div></div>
</div>
<div class="notif" id="notif"></div>
<?php
$dirs=[];$files=[];
if($h=opendir($fullPath)){while(($f=readdir($h))!==false){if($f==='.'||$f==='..') continue;is_dir($fullPath.'/'.$f)?$dirs[]=$f:$files[]=$f;}closedir($h);}
sort($dirs);sort($files);
if(empty($dirs)&&empty($files)):?><div style="text-align:center;padding:40px;color:#aaa">Empty directory</div><?php else:?>
<div class="files">
<?php foreach($dirs as $dir){$p=$currentDir?$currentDir.'/'.$dir:$dir;$fw=isW($fullPath.'/'.$dir,$basePath,$allowedWritable);?>
<div class="fi"><div class="fn">📁 <?=htmlspecialchars($dir)?>/</div><div class="fi-info">Folder</div><div class="fa">
<a href="?dir=<?=urlencode($p)?>" class="bs">OPEN</a>
<button class="bs" onclick="renameItem('<?=addslashes($p)?>','<?=addslashes($dir)?>')">REN</button>
<button class="bs mv" onclick="moveItem('<?=addslashes($p)?>','<?=addslashes($dir)?>')">MOVE</button>
<?php if($fw):?><form method="POST" style="display:contents" onsubmit="return confirm('Hapus folder <?=htmlspecialchars($dir)?> beserta isinya?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="target" value="<?=htmlspecialchars($p)?>"><button type="submit" class="bs del">DEL</button></form>
<?php else:?><button class="bs lk" disabled>LOCKED</button><?php endif?>
</div></div>
<?php }foreach($files as $file){$p=$currentDir?$currentDir.'/'.$file:$file;$fw=isW($fullPath.'/'.$file,$basePath,$allowedWritable);$sz=fmtSize(filesize($fullPath.'/'.$file));$ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));$isZ=in_array($ext,['zip','mcworld']);?>
<div class="fi"><div class="fn"><?=$isZ?'📦':'📄'?> <?=htmlspecialchars($file)?></div><div class="fi-info"><?=$sz?></div><div class="fa">
<a href="?edit=<?=urlencode($p)?>&dir=<?=urlencode($currentDir)?>" class="bs ed">EDIT</a>
<a href="?download=<?=urlencode($p)?>&dir=<?=urlencode($currentDir)?>" class="bs dl">DL</a>
<button class="bs" onclick="renameItem('<?=addslashes($p)?>','<?=addslashes($file)?>')">REN</button>
<button class="bs mv" onclick="moveItem('<?=addslashes($p)?>','<?=addslashes($file)?>')">MOVE</button>
<?php if($fw):?><form method="POST" style="display:contents" onsubmit="return confirm('Hapus <?=htmlspecialchars($file)?>?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="target" value="<?=htmlspecialchars($p)?>"><button type="submit" class="bs del">DEL</button></form>
<?php if($isZ):?><form method="POST" style="display:contents" onsubmit="return confirm('Extract & hapus zip <?=htmlspecialchars($file)?>?')"><input type="hidden" name="action" value="unzip"><input type="hidden" name="file" value="<?=htmlspecialchars($p)?>"><button type="submit" class="bs">UNZIP</button></form><?php endif?>
<?php else:?><button class="bs lk" disabled>LOCKED</button><?php endif?>
</div></div>
<?php }?></div><?php endif?>

<div class="modal" id="modal"><div class="mc"><h3 id="mt">New Item</h3><form method="POST"><input type="hidden" name="action" id="ma"><input type="text" name="name" id="mi" placeholder="Nama..." required><button type="submit">CREATE</button><button type="button" class="cn" onclick="cm('modal')">CANCEL</button></form></div></div>
<div class="modal" id="renameModal"><div class="mc"><h3>Rename</h3><form method="POST"><input type="hidden" name="action" value="rename"><input type="hidden" name="old" id="ro"><label>Nama baru (huruf, angka, _, -, .):</label><input type="text" name="new" id="rn" placeholder="Nama baru..." required><button type="submit">RENAME</button><button type="button" class="cn" onclick="cm('renameModal')">CANCEL</button></form></div></div>
<div class="modal" id="moveModal"><div class="mc"><h3>Pindahkan</h3><form method="POST"><input type="hidden" name="action" value="move"><input type="hidden" name="src" id="ms"><label>Pindahkan <strong id="mn"></strong> ke:</label><select name="dst_dir" id="md"><option value="">/mcserver (root)</option></select><button type="submit">PINDAHKAN</button><button type="button" class="cn" onclick="cm('moveModal')">CANCEL</button></form></div></div>

<script>
const currentDir='<?=addslashes($currentDir)?>';
const allDirs=<?=$allDirsJson?>;
const maxSize=<?=$uploadMaxSize?>;
const pageUrl='<?=($isHttps?'https':'http').'://'.$_SERVER['HTTP_HOST'].strtok($_SERVER['REQUEST_URI'],'?')?>';
const dz=document.getElementById('dz');
const fi=document.getElementById('fi');
const pw=document.getElementById('pw');
const pf=document.getElementById('pf');
const pt=document.getElementById('pt');
const notif=document.getElementById('notif');

function showNotif(msg,ok){notif.className='notif '+(ok?'ok':'fail');notif.textContent=(ok?'✓ ':'✗ ')+msg;notif.style.display='block';setTimeout(()=>{notif.style.display='none';},8000);}

dz.addEventListener('dragover',(e)=>{e.preventDefault();dz.classList.add('ov');});
dz.addEventListener('dragleave',()=>dz.classList.remove('ov'));
dz.addEventListener('drop',(e)=>{e.preventDefault();dz.classList.remove('ov');if(e.dataTransfer.files[0])doUpload(e.dataTransfer.files[0]);});
dz.addEventListener('click',(e)=>{if(e.target!==fi)fi.click();});
fi.addEventListener('change',(e)=>{if(e.target.files[0])doUpload(e.target.files[0]);});

function doUpload(file){
  if(file.size>maxSize){showNotif('File terlalu besar! Max 3GB.',false);return;}
  const fd=new FormData();
  fd.append('action','upload');
  fd.append('file',file);
  pw.style.display='block';
  pf.style.width='0%';
  pt.textContent='Uploading '+file.name+'...';
  notif.style.display='none';
  const xhr=new XMLHttpRequest();
  xhr.upload.addEventListener('progress',(e)=>{
    if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);pf.style.width=p+'%';pt.textContent=p+'% — '+fmtB(e.loaded)+' / '+fmtB(e.total);}
  });
  xhr.addEventListener('load',()=>{
    pw.style.display='none';
    try{
      const res=JSON.parse(xhr.responseText);
      if(res.ok){showNotif(res.msg,true);setTimeout(()=>location.reload(),2000);}
      else{showNotif(res.msg,false);}
    }catch(e){showNotif('Upload selesai (non-JSON response)',true);setTimeout(()=>location.reload(),2000);}
  });
  xhr.addEventListener('error',()=>{pw.style.display='none';showNotif('Upload gagal — network error atau server down.',false);});
  xhr.open('POST',pageUrl+'?dir='+encodeURIComponent(currentDir)+'&json=1');
  xhr.send(fd);
}

function fmtB(b){return b>1073741824?(b/1073741824).toFixed(1)+' GB':b>1048576?(b/1048576).toFixed(1)+' MB':(b/1024).toFixed(0)+' KB';}

function showModal(t){document.getElementById('modal').style.display='flex';document.getElementById('ma').value=t==='folder'?'mkdir':'mkfile';document.getElementById('mt').textContent=t==='folder'?'Buat Folder':'Buat File';document.getElementById('mi').value='';setTimeout(()=>document.getElementById('mi').focus(),80);}
function cm(id){document.getElementById(id).style.display='none';}

function renameItem(path,name){document.getElementById('ro').value=path;document.getElementById('rn').value=name;document.getElementById('renameModal').style.display='flex';setTimeout(()=>document.getElementById('rn').focus(),80);}

function moveItem(path,name){
  document.getElementById('ms').value=path;
  document.getElementById('mn').textContent=name;
  const sel=document.getElementById('md');
  sel.innerHTML='<option value="">/mcserver (root)</option>';
  allDirs.forEach(d=>{if(d.rel===path||d.rel.startsWith(path+'/'))return;const o=document.createElement('option');o.value=d.rel;o.textContent=d.label;sel.appendChild(o);});
  document.getElementById('moveModal').style.display='flex';
}

document.addEventListener('keydown',(e)=>{if(e.key==='Escape')['modal','renameModal','moveModal'].forEach(id=>cm(id));});
window.onclick=(e)=>['modal','renameModal','moveModal'].forEach(id=>{if(e.target===document.getElementById(id))cm(id);});
</script></body></html>
