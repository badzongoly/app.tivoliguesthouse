<?php
/** Recreate an archived match and its claim for synthetic compatibility fixtures. */
function historicalMoMoMatch(\Tivoli\Repository\AccessRepository $repo,int $actor,array $input,string $at):int
{
    if(PHP_SAPI!=='cli'||(int)$repo->run('SELECT @@port')->fetchColumn()!==3307
        ||$repo->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'
        ||$repo->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1'){
        throw new RuntimeException('Unsafe historical finance fixture target.');
    }
    $repo->run("INSERT INTO finance_events(kind,target_type,target_id,amount_minor,effective_date,actor_id,payload,created_at)
        VALUES('receipt_matched',?,?,?,?,?,?,?)",[
        $input['type']??'payment',$input['id'],\Tivoli\Domain\Stay\StayRules::minor($input['amount']),
        substr($at,0,10),$actor,json_encode(['reference'=>$input['reference'],'evidence'=>$input['evidence'],'wallet'=>'Business MoMo'],JSON_THROW_ON_ERROR),$at,
    ]);
    $eventId=(int)$repo->db->lastInsertId();
    $repo->run("INSERT INTO finance_claims VALUES('business_momo',?,?)",[hash('sha256',mb_strtolower(trim($input['reference']),'UTF-8')),$eventId]);
    return $eventId;
}
