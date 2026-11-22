<?php
require_once dirname(__DIR__).'/src/Security/require_login.php';
$q=['page'=>'payments'];foreach(['start','end'] as $key)if(isset($_GET[$key])&&is_string($_GET[$key]))$q[$key]=$_GET[$key];if(isset($_GET['page'])&&is_string($_GET['page'])&&ctype_digit($_GET['page']))$q['log_page']=$_GET['page'];header('Location: '.access_url('finance.php?'.http_build_query($q)));exit;
