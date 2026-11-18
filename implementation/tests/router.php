<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path==='/design/js/config.js') { header('Content-Type: application/javascript'); readfile(__DIR__.'/fixtures/config.js'); return true; }
if ($path==='/preview') { require __DIR__.'/preview.php'; return true; }
if ($path==='/preview-rating') { require __DIR__.'/preview-rating.php'; return true; }
if ($path==='/preview-ratings') {
    header('Content-Type: application/json');
    $player=['name'=>'Asterion','class'=>'Duelist','clan'=>'Phoenix','level'=>80,'pvp'=>100,'pk'=>5,'exp'=>'9000000000000000','time'=>'12 d 5 h','online'=>true];
    $clan=['name'=>'Phoenix','alliance'=>'Sun','hall'=>'Emerald Hall','castle'=>'Giran','level'=>8,'members'=>40,'reputation'=>15000,'pvp'=>2000];
    $data=['pvp'=>[$player],'pk'=>[$player],'exp'=>[$player],'clans'=>[$clan],'clanpvp'=>[$clan],'alliances'=>[['name'=>'Sun','clans'=>2,'members'=>80,'reputation'=>25000,'pvp'=>4000]],'castles'=>[['id'=>1,'castle'=>'Gludio Castle','owner'=>'Phoenix','leader'=>'Asterion','tax'=>5,'siege'=>'19.12.2026 20:00 GMT+3','attackers'=>['Moon'],'defenders'=>['Phoenix']]]];
    echo json_encode(['ok'=>true,'data'=>$data,'states'=>array_fill_keys(array_keys($data),'ok'),'updatedAt'=>gmdate('c')]); return true;
}
if (strpos($path,'/images/castles/')===0) { header('Content-Type: image/jpeg'); readfile(__DIR__.'/fixtures/castle.jpg'); return true; }
return false;
