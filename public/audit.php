<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
$repo=access_service()->repo; $error=''; $events=[]; $values=[]; $where=[]; $canViewAllActivity=false;
try {
    $canViewAllActivity=$repo->isAdmin((int)$user['id']);
    if(!$canViewAllActivity){$where[]='e.actor_id=?';$values[]=(int)$user['id'];}
    foreach(['action','outcome','actor','before','start','end'] as $key){if(isset($_GET[$key]) && !is_string($_GET[$key])){throw new RuntimeException('Invalid filters.');}}
    foreach(['action','outcome'] as $key){if(($_GET[$key] ?? '')!==''){if(strlen($_GET[$key])>80){throw new RuntimeException('Invalid filters.');}$where[]='e.'.$key.'=?';$values[]=$_GET[$key];}}
    foreach(['actor'=>'e.actor_id=?','before'=>'e.id<?'] as $key=>$expression){if(($_GET[$key] ?? '')!==''){$number=filter_var($_GET[$key],FILTER_VALIDATE_INT);if($number===false || $number<1){throw new RuntimeException('Enter valid numeric identifiers.');}$where[]=$expression;$values[]=$number;}}
    foreach(['start'=>'>=','end'=>'<='] as $key=>$comparison){if(($_GET[$key] ?? '')!==''){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$_GET[$key],new DateTimeZone('UTC'));if(!$date || $date->format('Y-m-d')!==$_GET[$key]){throw new RuntimeException('Invalid date.');}$where[]='e.occurred_at'.$comparison.'?';$values[]=$_GET[$key].($key==='start'?' 00:00:00.000000':' 23:59:59.999999');}}
    $events=$repo->run('SELECT e.*,u.name AS actor_name FROM security_audit e LEFT JOIN users u ON u.id=e.actor_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY e.id DESC LIMIT 100',$values)->fetchAll();
    access_service()->audit((int)$user['id'],'audit.viewed','audit',null,'succeeded',['rows'=>count($events)]);
} catch(Throwable $exception){$events=[];$error=access_error($exception);http_response_code($exception instanceof PDOException?503:400);}
require dirname(__DIR__).'/templates/access/audit.php';