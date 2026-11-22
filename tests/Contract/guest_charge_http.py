"""Actual charge/cash-refund screens; only on the marked synthetic test service."""
import pathlib,re,json,subprocess,datetime,html,urllib.parse,urllib.error
ROOT=pathlib.Path(__file__).resolve().parents[2]
definitions=(ROOT/'tests/Contract/receipt_corrections_http.py').read_text(encoding='utf-8').split('\ncount=0')[0];scope={'__file__':str(ROOT/'tests/Contract/receipt_corrections_http.py')};exec(definitions,scope);Browser=scope['Browser'];count=0
def check(ok,label):
 global count
 assert ok,label
 count+=1;print('PASS '+label,flush=True)
check(Browser().request('/auth/login.php')[0]==200,'synthetic web marker checked before fixtures')
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/guest_charge_http_seed.php')],cwd=ROOT,check=True,capture_output=True)
fixture=json.loads((ROOT/'.local-setup/guest-charge-http-fixture.json').read_text(encoding='utf-8'));owner=Browser();staff=Browser();other=Browser();admin=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
for client,key in [(staff,'staff'),(other,'other'),(admin,'admin')]:client.login(fixture['users'][key]['email'],'Synthetic Guest Charge Passphrase 123!')
base='/financial-corrections.php?area=charges';booking=fixture['booking'];source=base+'&booking_id='+str(booking)+'&charge_type=room&source_id='+str(booking)
check(Browser().request(base)[0]==302,'charge workspace requires login')
try:direct=staff.op.open('http://127.0.0.1:8099/guest-charge-corrections.php')
except urllib.error.HTTPError as response:direct=response
check(direct.code==403 and direct.read()==b'','internal controller stops before bootstrap; no authentication bypass')
check('Guest Charges &amp; Refunds' in staff.request('/financial-corrections.php')[2],'guest charges use existing correction tabs')
check(staff.request(base+'&page=review')[0]==403 and admin.request(base+'&page=review')[0]==403,'review queue restricted to configured owner')
check('Synthetic charge HTTP guest' in staff.request(source)[2],'request shows actual guest and original charge')
p=staff.fields(source,'request')|dict(amount='250.00',reason='Synthetic private guest correction reason',confirmed='1');check(staff.request(source,p|dict(csrf='bad'))[0]==403,'forged charge request denied');check(staff.request(source,p|dict(operation_key='bad'))[0]==400,'malformed key cannot create a fresh operation')
proof=b'%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF';code,headers,_=staff.request(source,p,proof);check(code==302,'supported charge request with private evidence submitted');cid=int(re.search(r'id=(\d+)',headers['Location'])[1]);detail=base+'&id='+str(cid)
check(staff.request(source,p,proof)[0]==302,'request retry keeps one identity');check(other.request(detail)[0]==403 and admin.request(detail)[0]==403,'private charge case restricted to requester and owner')
body=owner.request(detail)[2];check('GHS 300.00' in body and 'GHS 250.00' in body and '-GHS 50.00' in body and 'Actual Money Moved' in body,'owner sees exact charge, revenue and credit effects')
link=html.unescape(re.search(r'href="([^"]*finance-evidence.php[^"]+)"',body)[1]);evidence='/finance-evidence.php?'+urllib.parse.urlsplit(link).query;check(owner.request(evidence)[0]==200 and staff.request(evidence)[0]==200 and other.request(evidence)[0]==403 and admin.request(evidence)[0]==403,'charge evidence access independently enforced')
check('Synthetic private guest correction reason' not in owner.request('/finance.php?page=activity')[2],'generic activity excludes private charge reasons')
p=owner.fields(detail,'post')|dict(reason='Synthetic actual charge independently verified',verified='1');check(owner.request(detail,p)[0]==302 and owner.request(detail,p)[0]==302,'owner posts charge adjustment once across retries')
body=staff.request(detail)[2];check('Posted Charge Adjustment CADJ-' in body and 'Record Actual Completed Refund' not in body,'staff sees posted history without owner payout action')
check('Record Actual Completed Refund' in owner.request(detail)[2],'owner receives separate actual refund handoff')
notice=staff.request(detail+'&page=notice')[2];check('Synthetic private guest correction reason' not in notice and 'Synthetic actual charge independently verified' not in notice,'guest notice excludes private reasons')
refund='/occupancy.php?booking_id='+str(booking)+'&form=refund';check(staff.request(refund)[0]==403 and admin.request(refund)[0]==403,'refund form restricted to configured owner')
body=owner.request(refund)[2];check('Actual Refund Method' in body and 'Actual Refund Time' in body and 'Actual Payer' in body,'refund captures method, actual time and payer')
p=owner.fields(refund,'refund')|dict(payment_id=str(fixture['payment']),amount='50.00',method='cash',proof='HTTP-CASH-REFUND-'+fixture['tag'],reason='Synthetic completed excess refund',actual_at=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S'),actual_payer='Synthetic actual owner',completed='1')
check(owner.request(refund,p|dict(completed='0'))[0]==400,'refund completion must be attested')
check(owner.request(refund,p)[0]==302 and owner.request(refund,p)[0]==302,'actual cash refund records once across retries')
body=owner.request('/finance.php?page=payments&start='+datetime.date.today().isoformat()+'&end='+datetime.date.today().isoformat())[2];check('HTTP-CASH-REFUND-'+fixture['tag'] in body,'actual refund appears in payment log')
fixture['case']=cid;(ROOT/'.local-setup/guest-charge-http-fixture.json').write_text(json.dumps(fixture),encoding='utf-8');print('TOTAL '+str(count)+' guest charge HTTP assertions passed.')
