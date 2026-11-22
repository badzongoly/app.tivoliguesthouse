<?php $propertyTab=$section==='areas'?'areas':(in_array($tab,['faults','audits'],true)?$tab:'master'); ?>
<nav class="app-section-nav stay-property-nav" aria-label="Property & Audits">
<a href="<?=access_h(access_url('housekeeping.php'))?>">Housekeeping</a>
<?php foreach(['master'=>['Room Master',['section'=>'rooms','tab'=>'master']],'faults'=>['Out-Of-Order Log',['section'=>'rooms','tab'=>'faults']],'audits'=>['Room Audits',['section'=>'rooms','tab'=>'audits']],'areas'=>['Other Area Audits',['section'=>'areas']]] as $key=>[$label,$query]):?>
<a href="<?=in_array($key,['audits','areas'],true)?access_h(access_url('weekly-audits.php?page='.($key==='audits'?'room':'area'))):$url($query)?>#<?=in_array($key,['audits','areas'],true)?'audit-action':'stay-workspace'?>" <?=$propertyTab===$key?'aria-current="page"':''?>><?=access_h($label)?></a>
<?php endforeach?>
<a href="<?=access_h(access_url('weekly-audits.php'))?>#audit-action">Follow-Ups</a>
</nav>
