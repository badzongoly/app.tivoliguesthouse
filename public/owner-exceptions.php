<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
use Tivoli\Service\OwnerExceptionService;
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit('Open the existing workflow to record an action.');}
$error='';$data=null;
try{$data=(new OwnerExceptionService(access_service()))->read((int)$user['id'],$_GET);}
catch(Throwable $e){$error=access_error($e);http_response_code($e instanceof \Tivoli\Security\PermissionDenied?403:($e instanceof PDOException?503:400));}
function oe_url(array $q=[]):string{return access_url('owner-exceptions.php'.($q?'?'.http_build_query($q):''));}
function oe_date(string $v):string{return $v!==''?display_date($v):'Not Set';}
$layoutTitle='Owner Exceptions';require dirname(__DIR__).'/templates/owner-exceptions/index.php';
