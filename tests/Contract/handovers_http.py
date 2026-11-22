"""Approved handover HTTP contract. Only the marked synthetic server is accepted."""
import datetime,html,http.cookiejar,json,pathlib,re,secrets,subprocess,urllib.request,urllib.error,urllib.parse
ROOT=pathlib.Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:8099'
class NR(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kw):return None
class Browser:
 def __init__(self):self.op=urllib.request.build_opener(NR(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def req(self,path,data=None):
  try:r=self.op.open(urllib.request.Request(BASE+path,data=urllib.parse.urlencode(data).encode() if data is not None else None),timeout=30)
  except urllib.error.HTTPError as err:r=err
  assert r.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified HTTP service'
  text=r.read().decode('utf-8')
  assert not re.search(r'Fatal error|Warning:|Parse error|SQLSTATE\[',text),'Application error'
  return r.code,r.headers,text
 def fields(self,path,action):
  text=self.req(path)[2];form=next(x for x in re.findall(r'<form\b[^>]*>(.*?)</form>',text,re.S) if 'name="action" value="'+action+'"' in x)
  return {k:html.unescape(v) for k,v in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',form)}
 def login(self,email,password):
  text=self.req('/auth/login.php')[2];csrf=html.unescape(re.search(r'name="csrf" value="([^"]+)"',text)[1])
  assert self.req('/auth/login.php',dict(csrf=csrf,email=email,password=password))[0]==302
count=0
def check(ok,label):
 global count
 assert ok,label
 count+=1;print('PASS '+label,flush=True)
anon=Browser();check(anon.req('/handover.php')[0]==302,'Anonymous handover access requires login')
setup=r"""putenv('TIVOLI_TEST_MODE=1');require 'bootstrap/security.php';$r=access_service()->repo;if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$out=[];foreach(['sender','receiver','third','other_admin'] as $label){$email='handover-'.$label.'-'.bin2hex(random_bytes(6)).'@example.invalid';$r->run('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',['Synthetic '.$label,$email,password_hash('Synthetic Handover Passphrase 123!',PASSWORD_BCRYPT),$label==='other_admin'?'admin':'clerk']);$id=(int)$r->db->lastInsertId();$r->run('INSERT INTO security_accounts(user_id,must_change_password) VALUES(?,0)',[$id]);$r->run("INSERT INTO security_user_roles(user_id,role_id) SELECT ?,id FROM security_roles WHERE name=?",[$id,$label==='other_admin'?'admin':'clerk']);$out[$label]=['id'=>$id,'email'=>$email];}echo json_encode($out);"""
fixture=json.loads(subprocess.run(['C:/xampp/php/php.exe','-r',setup],cwd=ROOT,capture_output=True,text=True,encoding='utf-8',check=True).stdout)
sender=Browser();receiver=Browser();third=Browser();owner=Browser();other_admin=Browser()
for label,browser in [('sender',sender),('receiver',receiver),('third',third),('other_admin',other_admin)]:browser.login(fixture[label]['email'],'Synthetic Handover Passphrase 123!')
owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
subprocess.run(['C:/xampp/php/php.exe','tests/Integration/handover_http_seed.php',str(fixture['sender']['id'])],cwd=ROOT,capture_output=True,text=True,encoding='utf-8',check=True)
path='/handover.php'
body=sender.req(path)[2];check('Prepare Handover' in body and 'Duty Started' in body,'Staff sees live review and actual duty form')
check('Shift Handover' in sender.req('/occupancy.php')[2],'Handover is in shared navigation')
private_marker='HANDOVER-PRIVATE-'+secrets.token_hex(16)
now=datetime.datetime.now(datetime.timezone.utc);base=sender.fields(path,'submit')
data=base|dict(recipient_id=fixture['receiver']['id'],duty_start=(now-datetime.timedelta(hours=1)).strftime('%Y-%m-%dT%H:%M:%S'),duty_end=now.strftime('%Y-%m-%dT%H:%M:%S'),review_guests='1',review_cash='1',review_rooms='1',review_tasks='1',confirmed='1',notes=private_marker+' <script>not code</script>')
check(sender.req(path,{k:v for k,v in data.items() if k!='csrf'})[0]==403,'Handover POST requires CSRF')
status,headers,_=sender.req(path,data);check(status==302,'Outgoing submits without waiting for incoming')
detail=urllib.parse.urlsplit(headers['Location']);url=detail.path+'?'+detail.query
id=int(urllib.parse.parse_qs(detail.query)['id'][0])
check(third.req(url)[0]==403,'Unrelated staff direct object access denied')
body=receiver.req(url)[2];check(private_marker in body and '&lt;script&gt;not code&lt;/script&gt;' in body,'Original notes are retained and escaped')
check('Awaiting Acknowledgement' in body,'Submitted state awaits acknowledgement')
check(sender.req(url, sender.fields(url,'submit') if 'name="action" value="submit"' in sender.req(url)[2] else {'csrf':base['csrf'],'operation_key':secrets.token_hex(16),'action':'ack','id':str(id),'version':'invalid'})[0] in (400,403),'Sender cannot acknowledge for another account')
ack=receiver.fields(url,'ack')|dict(reviewed='1',flag='1',discrepancy='Synthetic room discrepancy',comment='Incoming checked')
check(receiver.req(url,ack)[0]==302,'Incoming acknowledges with discrepancy')
body=owner.req('/handover.php?page=owner')[2];check('Synthetic room discrepancy' in body or str(id) in body,'Owner sees discrepancy')
review=owner.fields(url,'review')|dict(comment='Synthetic owner assigned follow-up')
check(owner.req(url,review)[0]==302,'Owner records discrepancy review')
check('Synthetic room discrepancy' in owner.req(url)[2],'Original discrepancy retained after review')
check('handover.php' in owner.req('/owner_reports.php')[2],'Owner home links handover attention')
check(third.req('/handover.php?page=owner')[0]==403,'Staff cannot open owner register')
check('name="amount"' in sender.req('/handover.php?page=cash')[2],'Separate physical cash transfer form')
check('physical cash-count' not in sender.req('/handover.php?page=cash')[2].lower(),'Cash transfer has no cash-count form')
check('Handover Submitted' in owner.req(url)[2],'Audit history contains original submission')

cash='/handover.php?page=cash'
send=sender.fields(cash,'cash_send')|dict(recipient_id=fixture['receiver']['id'],amount='60.00',passed='1')
status,headers,_=sender.req(cash,send);check(status==302,'Physical cash transfer recorded separately')
transfer='/handover.php?'+urllib.parse.urlsplit(headers['Location']).query
check('60.00' in receiver.req(transfer)[2],'Recipient sees exact cash transfer')
check(third.req(transfer)[0]==403,'Unrelated staff cannot inspect cash transfer')
check('40.00' in sender.req(cash)[2] and '60.00' in sender.req(cash)[2],'Pending cash reserved from sender availability')
ack=receiver.fields(transfer,'cash_ack')|dict(received='1')
check(receiver.req(transfer,{k:v for k,v in ack.items() if k!='csrf'})[0]==403,'Cash acknowledgement requires CSRF')
check(receiver.req(transfer,ack)[0]==302,'Recipient acknowledges actual full cash receipt')
check('60.00' in receiver.req('/finance.php?page=deposits')[2],'Finance sees recipient custody')
check(private_marker not in other_admin.req('/finance.php?page=activity')[2],'Other administrators cannot read private handover snapshots through finance')
check(other_admin.req(url)[0]==403,'Other administrators cannot read handover directly')

(ROOT/'.local-setup/handover-http-fixture.json').write_text(json.dumps(fixture|{'handover_id':id}),encoding='utf-8')
print(f'TOTAL {count} handover HTTP checks passed.')
