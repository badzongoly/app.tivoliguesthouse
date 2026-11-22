<?php
if(PHP_SAPI!=='cli')exit(1);putenv('TIVOLI_TEST_MODE=1');require dirname(__DIR__,2).'/bootstrap/stays.php';
use Tivoli\Service\StockCountService;
$a=access_service();$r=$a->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
if(!class_exists(StockCountService::class))throw new RuntimeException('Approved stock count workflow is missing');

$n=0;$tag=bin2hex(random_bytes(6));$clock='2052-08-05 14:00:00';$owner=(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn();putenv('TIVOLI_FINANCE_OWNER_USER_ID='.$owner);
function sk():string{return bin2hex(random_bytes(16));}
function sa(bool $ok,string $label):void{global $n;if(!$ok)throw new RuntimeException($label);$n++;echo "PASS $label\n";}
function sd(callable $f,string $label):void{try{$f();}catch(Throwable $e){sa(true,$label);return;}throw new RuntimeException('Expected denial: '.$label);}
function su(string $role='clerk'):int{global $r;$r->run('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Synthetic stock counter',sk().'@example.invalid',password_hash('Synthetic Stock Passphrase 123!',PASSWORD_BCRYPT),$role]);$id=(int)$r->db->lastInsertId();$r->run('INSERT INTO security_accounts(user_id,must_change_password) VALUES(?,0)',[$id]);$r->run('INSERT INTO security_user_roles SELECT ?,id FROM security_roles WHERE name=?',[$id,$role]);return $id;}
$staff=su();$other=su();$admin=su('admin');$svc=new StockCountService($a,$clock);
function sx(string $stock='48.00'):int{global $r;$r->run("INSERT INTO extras(name,price,active,stock_qty) VALUES(?,1,1,?)",['Synthetic count '.sk(),$stock]);$id=(int)$r->db->lastInsertId();$r->run("INSERT INTO stay_extra_settings(extra_id,category,tracked,low_stock) VALUES(?,'General',1,0)",[$id]);return $id;}
function sc(int $who,string $action,array $in=[],?string $key=null):array{global $svc;return $svc->execute($who,$action,$in,$key??sk());}
function sv(int $id,int $actor):array{global $svc;return ['id'=>(string)$id,'version'=>$svc->detail($actor,$id)['version']];}
function start(int $actor,array $items,?int $parent=null):int{return sc($actor,'start',['items'=>json_encode($items),'purpose'=>'Weekly Stock Count','comment'=>'Synthetic full count']+($parent?['parent'=>(string)$parent]:[]))['id'];}
function countLine(int $actor,int $id,int $extra,string $qty):void{sc($actor,'begin',sv($id,$actor)+['extra_id'=>(string)$extra]);sc($actor,'finish',sv($id,$actor)+['extra_id'=>(string)$extra,'qty'=>$qty,'confirmed'=>'1','note'=>'Synthetic physical count']);}
function send(int $actor,int $id):void{global $svc;$c=$svc->detail($actor,$id);$reasons=[];foreach($c['lines'] as $key=>$line)$reasons[$key]=['reason'=>'Missing / Unexplained','note'=>'Checked both store and shelf'];sc($actor,'submit',sv($id,$actor)+['reasons'=>json_encode($reasons),'confirmed'=>'1']);}
function approve(int $id):array{global $svc,$owner;return sc($owner,'approve',sv($id,$owner)+['review_token'=>$svc->detail($owner,$id)['review_token'],'decision'=>'Physical count and records checked','verified'=>'1','authorized'=>'1']);}
function move(int $extra,string $qty,string $type='out'):void{global $a,$r,$clock;$a->transaction(function()use($r,$extra,$qty,$type,$clock){$r->run('UPDATE extras SET stock_qty=stock_qty+? WHERE id=?',[$qty,$extra]);$r->run('INSERT INTO stock_movements(extra_id,movement_type,qty,note,ref_type,ref_id,created_by,created_at) VALUES(?,?,?,?,?,?,?,?)',[$extra,$type,$type==='out'?ltrim($qty,'-'):$qty,'Synthetic intervening movement','synthetic_count',0,(int)$r->run("SELECT id FROM users WHERE email='admin@example.invalid'")->fetchColumn(),$clock]);});}
