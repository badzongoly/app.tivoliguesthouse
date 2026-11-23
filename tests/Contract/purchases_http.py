"""Purchase acceptance against the marked synthetic HTTP service only."""
import datetime,html,http.cookiejar,json,pathlib,re,secrets,subprocess,urllib.request,urllib.error,urllib.parse
ROOT=pathlib.Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:8099'
class NR(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
class Browser:
 def __init__(self):self.op=urllib.request.build_opener(NR(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,path,data=None,file=None):
  headers={};body=urllib.parse.urlencode(data).encode() if data is not None else None
  if file:
   boundary='Purchase'+secrets.token_hex(8);parts=[]
   for k,v in data.items():parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"\r\n\r\n'+str(v)+'\r\n').encode())
   parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="evidence"; filename="proof.pdf"\r\nContent-Type: application/pdf\r\n\r\n').encode()+file+b'\r\n');parts.append(('--'+boundary+'--\r\n').encode());body=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
  try:r=self.op.open(urllib.request.Request(BASE+path,data=body,headers=headers),timeout=30)
  except urllib.error.HTTPError as ex:r=ex
  text=r.read().decode('utf-8')
  if r.code!=404:assert r.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified service'
  assert not re.search(r'Fatal error|Warning:|Parse error|SQLSTATE\[',text),'Application error'
  return r.code,r.headers,text
 def fields(self,path,action):
  text=self.request(path)[2];forms=re.findall(r'<form\b[^>]*>(.*?)</form>',text,re.S);f=next(x for x in forms if 'name="action" value="'+action+'"' in x)
  return {k:html.unescape(v) for k,v in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',f)}
 def login(self,email,password):
  text=self.request('/auth/login.php')[2];csrf=html.unescape(re.search(r'name="csrf" value="([^"]+)"',text)[1]);assert self.request('/auth/login.php',dict(csrf=csrf,email=email,password=password))[0]==302
count=0
def check(ok,label):
 global count
 assert ok,label
 count+=1;print('PASS '+label,flush=True)
anon=Browser();assert anon.request('/auth/login.php')[0]==200
check(anon.request('/purchases.php')[0]==302,'New purchases endpoint requires login')
fixture=json.loads((ROOT/'.local-setup/handover-http-fixture.json').read_text(encoding='utf-8'));clerk=Browser();other=Browser();owner=Browser()
clerk.login(fixture['sender']['email'],'Synthetic Handover Passphrase 123!');other.login(fixture['third']['email'],'Synthetic Handover Passphrase 123!');owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
new='/purchases.php?page=new';body=clerk.request(new)[2];cat=re.search(r'<select name="category_id"[^>]*>.*?<option value="(\d+)"',body,re.S)[1];tag=secrets.token_hex(6)
request=clerk.fields(new,'request')|dict(type='expense',category_id=cat,title='Synthetic purchase HTTP '+tag,supplier='Sample supplier '+tag,phone='0000000000',purpose='ORIGINAL-SCOPE-'+tag,estimate='100.00',method='momo')
check(clerk.request(new,{k:v for k,v in request.items() if k!='csrf'})[0]==403,'Purchase write requires CSRF')
status,headers,_=clerk.request(new,request);check(status==302,'Staff submits prospective purchase')
id=int(re.search(r'id=(\d+)',headers['Location'])[1]);detail='/purchases.php?id='+str(id)
check(other.request(detail)[0]==403,'Other staff denied purchase details')
check('Awaiting Approval' in clerk.request(detail)[2],'Pending request awaits owner')
approve=detail+'&form=approve';data=owner.fields(approve,'approve')|dict(decision='approve',limit='100.00')
check(clerk.request(approve)[0]==403,'Staff cannot open owner approval form')
body=owner.request(approve)[2]
check('Approval / Rejection Reason (Optional)<textarea name="reason" maxlength="2000" rows="3">' in body,'Spending decision reason is optional in the form')
check(owner.request(approve,data)[0]==302,'Owner approves limit without a reason')
amend=detail+'&form=amend';changed=clerk.fields(amend,'amend')|dict(limit='90.00',reason='PROPOSED-CHANGE-'+tag,supplier='Changed supplier '+tag,scope='Revised proposed scope')
check(clerk.request(amend,changed)[0]==302,'Requester can propose a revised scope before commitment')
history=owner.request(detail+'&tab=activity')[2]
check('Proposed Total GHS 90.00' in history and 'Approved Total GHS 90.00' not in history,'Audit distinguishes proposed limits from approved authority')
check('ORIGINAL-SCOPE-'+tag in history,'Original scope remains visible after amendment')
reject=owner.fields(approve,'approve')|dict(decision='reject',limit='90.00',reason='')
check(owner.request(approve,reject)[0]==302,'Rejected amendment with a blank reason retains original spending authority')
now=lambda:datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%S')
commit=detail+'&form=commit';data=clerk.fields(commit,'commit')|dict(amount='101.00',committed_at=now(),confirmed='1')
check(clerk.request(commit,data)[0]==400,'Over-limit commitment rejected')
data=clerk.fields(commit,'commit')|dict(amount='100.00',committed_at=now(),confirmed='1');check(clerk.request(commit,data)[0]==302,'Within-limit commitment recorded')
receive=detail+'&form=receive';proof=('%PDF-1.4\nSynthetic purchase receipt '+tag).encode();data=clerk.fields(receive,'receive')|dict(actual_cost='100.00',delivered_at=now(),payment_status='unpaid',method='momo',received='1')
body=clerk.request(receive)[2]
check('Goods Received / Work Completed (Optional)<textarea name="description" maxlength="2000" rows="3">' in body,'Delivery description is optional in the form')
check(clerk.request(receive,data|dict(received='0'),file=proof)[0]==400,'Delivery still requires complete receipt confirmation')
check(clerk.request(receive,data)[0]==400,'Delivery still requires receipt or alternative evidence')
check(clerk.request(receive,data,file=proof)[0]==302,'Delivery without a description accepts private receipt evidence')
body=owner.request(detail+'&tab=delivery')[2];evidence=re.search(r'finance-evidence.php\?kind=purchase&amp;id=(\d+)',body)[1];download='/finance-evidence.php?kind=purchase&id='+evidence
check(other.request(download)[0]==403,'Unrelated staff denied evidence')
check(clerk.request(download)[2].encode()==proof,'Requester downloads exact private evidence')
post=detail+'&form=post';data=owner.fields(post,'post')|dict(verified='1',reason='Actual delivery and cost checked')
check(owner.request(post,data)[0]==302,'Owner records one canonical cost')
check('Expense #' in owner.request(detail+'&tab=delivery')[2],'Posted expense link shown')
pay=detail+'&form=pay';data=owner.fields(pay,'pay')|dict(amount='40.00',method='momo',payer_id=fixture['sender']['id'],paid_at=now(),reference='PURCH-PART-'+tag,evidence='PURCHASE-PRIVATE-'+tag,paid='1')
check(owner.request(pay,data)[0]==302,'Partial completed payment recorded')
check('60.00' in owner.request(detail+'&tab=payment')[2],'Remaining amount shown after partial payment')
nonowner=Browser();nonowner.login(fixture['other_admin']['email'],'Synthetic Handover Passphrase 123!')
check('PURCHASE-PRIVATE-'+tag in owner.request(detail+'&tab=activity')[2],'Owner sees protected purchase payment evidence')
finance_history=nonowner.request('/finance.php?page=activity')[2]
check('PURCHASE-PRIVATE-'+tag not in finance_history,'Other admins cannot read purchase payment evidence through finance events')
expense_id=re.search(r'Expense #(\d+)',owner.request(detail+'&tab=delivery')[2])[1]
check(any('Cost Paid' in row and 'expense #'+expense_id in row for row in re.findall(r'<tr\b[^>]*>(.*?)</tr>',finance_history,re.S)),'Finance history retains payment amount and expense link without private evidence')
check(nonowner.request(download)[0]==403,'Other admins cannot download purchase files')
check('purchases.php' in owner.request('/owner_reports.php')[2],'Owner home links purchasing follow-up')
check('Purchase Requests' in clerk.request('/finance.php')[2],'Finance navigation exposes purchasing')
csrf=clerk.fields(new,'request')['csrf']
check(clerk.request('/expenses.php',dict(action='save_expense',csrf=csrf,operation_key=secrets.token_hex(16)))[0]==409,'Obsolete expense creation denied')
check(clerk.request('/finance.php',dict(action='provider_request',csrf=csrf,operation_key=secrets.token_hex(16)))[0]==409,'Obsolete provider request denied')
check(owner.request('/occupancy.php',dict(action='stock_in',csrf=owner.fields(new,'request')['csrf'],operation_key=secrets.token_hex(16)))[0]==409,'Obsolete stock receipt denied')
(ROOT/'.local-setup/purchase-http-fixture.json').write_text(json.dumps({'id':id,'sender':fixture['sender'],'other':fixture['third']}),encoding='utf-8')

fault=clerk.fields(new,'request')|dict(request,operation_key=secrets.token_hex(16),title='Synthetic uncertain purchase '+tag)
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/audit_fault.php'),'enable'],check=True)
try:
 status,_,text=clerk.request(new,fault)
 check(status==503 and ('value="'+fault['operation_key']+'"') in text,'Failed purchase audit preserves operation identity for safe retry')
finally:subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/audit_fault.php'),'disable'],check=True)
check(clerk.request(new,fault)[0]==302,'Retry after audit recovery creates one purchase')
check(clerk.request(new,fault)[0]==302,'Retry of recovered purchase retains original result')
print(f'TOTAL {count} purchase HTTP checks passed.')

