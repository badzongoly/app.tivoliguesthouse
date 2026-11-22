"""Dependent recording corrections, only against the marked synthetic server."""
import runpy,pathlib,re,json,subprocess
ROOT=pathlib.Path(__file__).resolve().parents[2]
# Reuse HTTP client definitions without running the other suite.
source=(ROOT/'tests/Contract/receipt_corrections_http.py').read_text(encoding='utf-8').split('\ncount=0')[0]
scope={'__file__':str(ROOT/'tests/Contract/receipt_corrections_http.py')};exec(source,scope);Browser=scope['Browser'];n=0
def check(ok,label):
 global n
 assert ok,label
 n+=1;print('PASS '+label,flush=True)
check(Browser().request('/auth/login.php')[0]==200,'synthetic web target verified before fixtures')
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/receipt_reconciliation_http_seed.php')],cwd=ROOT,check=True,capture_output=True)
fixture=json.loads((ROOT/'.local-setup/receipt-reconciliation-http-fixture.json').read_text(encoding='utf-8'));staff=Browser();owner=Browser();other=Browser();admin=Browser()
owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
for client,key in [(staff,'staff'),(other,'other'),(admin,'admin')]:client.login(fixture['users'][key]['email'],'Synthetic Reconciliation Passphrase 123!')
root='/financial-corrections.php';path=root+'?payment_id='+str(fixture['receipts']['cash']);body=staff.request(path)[2]
check('Reconcile Linked Money' in body and 'cash_support_id' in body,'existing correction page includes supporting cash selection')
check(other.request(path)[0]==403,'another collector cannot inspect private source')
base=staff.fields(path,'request')|dict(type='amount',amount='480.00',reason='Synthetic private funding proof',confirmed='1',cash_support_id=str(fixture['receipts']['cash_support']))
check(staff.request(path,base)[0]==400,'linked evidence acknowledgement required')
base['reconcile']='1';check(staff.request(path,base|dict(csrf='bad'))[0]==403,'forged linked correction rejected')
code,headers,_=staff.request(path,base);check(code==302,'dependent cash correction submitted');cid=int(re.search(r'id=(\d+)',headers['Location'])[1]);detail=root+'?id='+str(cid)
check(staff.request(path,base)[0]==302,'duplicate request retains original result')
check(other.request(detail)[0]==403 and admin.request(detail)[0]==403,'reconciliation proposal remains requester and owner only')
body=owner.request(detail)[2];check('480.00' in body and '20.00' in body and '500.00' in body,'owner preview shows immutable movement and exact funding allocations')
check('Synthetic private funding proof' not in owner.request('/finance.php?page=activity')[2],'general finance activity excludes private reconciliation reason')
post=owner.fields(detail,'post')|dict(reason='Synthetic independent funding and deposit evidence checked',verified='1')
check(staff.request(detail,post)[0]==403,'requester cannot approve owner reconciliation')
check(owner.request(detail,post)[0]==302 and owner.request(detail,post)[0]==302,'owner posts one reconciliation across retries')
body=staff.request(detail)[2];check('Posted Adjustment ADJ-' in body and 'Actual Money Moved' in body,'posted view labels recording-only adjustment')
notice=staff.request(detail+'&page=notice')[2];check('Synthetic private funding proof' not in notice and 'Synthetic independent funding' not in notice,'guest notice excludes private reasons')
path=root+'?payment_id='+str(fixture['receipts']['wallet']);base=staff.fields(path,'request')|dict(type='amount',amount='480.00',reason='Synthetic existing merchant proof allocation',confirmed='1',reconcile='1',wallet_support_id=str(fixture['receipts']['wallet_support']))
code,headers,_=staff.request(path,base);check(code==302,'verified wallet correction submitted');wid=int(re.search(r'id=(\d+)',headers['Location'])[1]);detail=root+'?id='+str(wid);post=owner.fields(detail,'post')|dict(reason='Synthetic merchant evidence checked once',verified='1');check(owner.request(detail,post)[0]==302,'wallet proof reallocated atomically')
code,headers,_=owner.request('/finance.php?page=daily&form=receipt_match&type=payment&id='+str(fixture['receipts']['wallet_support']));check(code==302 and 'page=discrepancies' in headers['Location'],'old full matcher routes allocated proof to linked resolution')
path=root+'?payment_id='+str(fixture['receipts']['increase']);base=staff.fields(path,'request')|dict(type='amount',amount='520.00',reason='Synthetic larger actual receipt with partial independent proof',confirmed='1',reconcile='1')
code,headers,_=staff.request(path,base);check(code==302,'matched receipt increase needs no invented wallet credit');iid=int(re.search(r'id=(\d+)',headers['Location'])[1]);detail=root+'?id='+str(iid);post=owner.fields(detail,'post')|dict(reason='Synthetic initial proof retained and residual still unverified',verified='1');check(owner.request(detail,post)[0]==302,'increase preserves bounded original verification')
code,headers,_=owner.request('/finance.php?page=daily&form=receipt_match&type=payment&id='+str(fixture['receipts']['increase']));check(code==302 and 'page=discrepancies' in headers['Location'],'previously matched increase routes to residual resolution without allocation history')
fixture['cash_case']=cid;fixture['wallet_case']=wid;(ROOT/'.local-setup/receipt-reconciliation-http-fixture.json').write_text(json.dumps(fixture),encoding='utf-8')
print('TOTAL '+str(n)+' reconciliation HTTP assertions passed.')
