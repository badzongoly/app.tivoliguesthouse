<?php
namespace Tivoli\Service;
use Tivoli\Security\PermissionDenied;

trait ReminderActivity
{
    private function activityRows(array $events):array{
        usort($events,fn($a,$b)=>$b['id']<=>$a['id']);$rows=[];
        foreach($events as $e){$d=$e['data'];$label=match($e['kind']){'nt_notice'=>'Reminder Created','nt_ping'=>'Daily Reminder Recorded','nt_read'=>'Reminder Marked Read · Original Task Unchanged','nt_delivery'=>'External Delivery Prepared','nt_send_intent'=>'Delivery Attempt Recorded','nt_delivery_outcome'=>'Provider Outcome Recorded','nt_callback'=>($d['applied']??true)?'Provider Receipt Recorded':'Earlier Attempt Receipt Retained · Not Applied To Current Attempt','nt_delivery_reply'=>'Provider Reply Retained','nt_delivery_members'=>'Unsent Summary Membership Refreshed','nt_delivery_cancelled'=>'Unsent Delivery Cancelled',default=>'Delivery Status Updated'};
            $name=$this->access->repo->run('SELECT name FROM users WHERE id=?',[$e['actor_id']])->fetchColumn();$rows[]=['id'=>$e['id'],'label'=>$label,'state'=>$d['state']??'','at'=>$e['created_at'],'actor'=>$name?:'Original Account #'.$e['actor_id']];
        }return $rows;
    }
    public function noticeActivity(int $actor,int $id):array{
        $this->permit($actor);$root=$this->journal->find($id);if($root['kind']!=='nt_notice'||(int)$root['data']['recipient']!==$actor)throw new PermissionDenied('Only your own reminder history is available.');$this->sources->reset();$this->sources->inspect($actor,$root['data']['source']);return $this->activityRows([$root,...$this->journal->events('n_ping',(string)$id),...$this->journal->events('n_read',(string)$id)]);
    }
    public function deliveryActivity(int $actor,int $id):array{
        $this->permit($actor);$root=$this->journal->find($id);if($root['kind']!=='nt_delivery'||(int)$root['data']['recipient']!==$actor)throw new PermissionDenied('Only your own delivery history is available.');return $this->activityRows([$root,...$this->journal->events('n_delivery_state',(string)$id),...$this->journal->events('n_delivery_receipt',(string)$id)]);
    }
}
