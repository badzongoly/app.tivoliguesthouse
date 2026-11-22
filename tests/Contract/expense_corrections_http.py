"""Acceptance only against the marked synthetic test web service."""
import datetime,html,http.cookiejar,json,pathlib,re,secrets,subprocess,urllib.request,urllib.error,urllib.parse
ROOT=pathlib.Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:8099'
class NR(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*a,**kw):return None
class Browser:
 def __init__(self):self.op=urllib.request.build_opener(NR(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,path,data=None,file=None):
  headers={};body=urllib.parse.urlencode(data).encode() if data is not None else None
  if file:
   boundary='Stock'+secrets.token_hex(8);parts=[]
   for k,v in data.items():parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"\r\n\r\n'+str(v)+'\r\n').encode())
   parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="evidence"; filename="proof.pdf"\r\nContent-Type: application/pdf\r\n\r\n').encode()+file+b'\r\n');parts.append(('--'+boundary+'--\r\n').encode());body=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
  try:r=self.op.open(urllib.request.Request(BASE+path,data=body,headers=headers),timeout=30)
  except urllib.error.HTTPError as e:r=e
  text=r.read().decode('utf-8');assert r.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified service'
  assert not re.search(r'Fatal error|Warning:|Parse error|SQLSTATE\[',text),'Application output error'
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
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/expense_corrections_http_seed.php')],cwd=ROOT,check=True,capture_output=True)
fixture=json.loads((ROOT/'.local-setup/expense-corrections-http-fixture.json').read_text(encoding='utf-8'))
root='/financial-corrections.php?area=expenses';owner=Browser();staff=Browser();other=Browser();admin=Browser();outsider=Browser()
owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
for b,k in [(staff,'staff'),(other,'other'),(admin,'admin'),(outsider,'outsider')]:b.login(fixture['users'][k]['email'],'Synthetic Correction Passphrase 123!')
check(Browser().request(root)[0]==302,'Expense corrections require login')
check('Expense Corrections' in owner.request(root)[2],'Approved expense workspace exists')
check(outsider.request(root)[0]==403,'Expense permission required directly')
check(staff.request(root+'&page=review')[0]==403 and admin.request(root+'&page=review')[0]==403,'Owner review restricted to configured owner')
source=root+'&expense_id='+str(fixture['expenses']['amount']['id'])
check(other.request(source)[0]==403,'Other recorder source denied')
body=staff.request(source)[2]
check('Correct Total Cost Incurred' in body and 'Total Actually Paid' in body and 'ec-request' in body,'Simple correction form separates cost and actual payout')
check('Supplier Returned / Owes Money' in body and staff.request(source+'&form=supplier')[0]==200,'Actual supplier recovery is a separate non-posting handoff')
p=staff.fields(source,'request')|dict(type='amount',amount='400.00',paid='400.00',reason='Synthetic incurred cost and actual payout independently evidenced.',confirmed='1')
check(staff.request(source,p|dict(csrf='bad'))[0]==403,'CSRF denied')
check(staff.request(source,p|dict(operation_key='bad'))[0]==400,'Invalid key cannot become a new action')
check(staff.request(source,p|dict(paid='600.00'))[0]==400,'Actual overpayment cannot be hidden as a recording correction')
proof=b'%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF'
code,headers,_=staff.request(source,p,proof);check(code==302,'Request with optional private evidence recorded')
id=int(re.search(r'id=(\d+)',headers['Location'])[1]);detail=root+'&id='+str(id)
check(staff.request(source,p,proof)[0]==302,'Request retry safely returns original case')
check(other.request(detail)[0]==403 and admin.request(detail)[0]==403,'Private case requester and owner only')
body=owner.request(detail)[2]
evidence=html.unescape(re.search(r'href="([^"]*finance-evidence.php[^"]+)"',body)[1]);evidence='/finance-evidence.php?'+urllib.parse.urlsplit(evidence).query
check(owner.request(evidence)[0]==200 and staff.request(evidence)[0]==200 and other.request(evidence)[0]==403,'Evidence access stays bound to private case')
check('Synthetic incurred cost and actual payout independently evidenced.' not in owner.request('/finance.php?page=activity')[2],'General finance activity excludes private expense notes')
p=owner.fields(detail,'post')|dict(verified='1',reason='')
code,_,body=owner.request(detail,p);check(code==400 and 'Eugene' in body and 'Approve &amp; Post' in body,'Validation preserves owner form')
p=owner.fields(detail,'post')|dict(verified='1',reason='Actual ordinary cost, payer, funding and payout independently verified')
check(owner.request(detail,p)[0]==302 and owner.request(detail,p)[0]==302,'Owner posting is confirmed and idempotent')
body=staff.request(detail)[2];check('Posted Adjustment EADJ-' in body and 'GHS -200.00' in body and 'GHS 400.00' in body,'Original and effective expense with exact adjustment shown')
body=staff.request(detail+'&page=notice')[2];check('Expense Adjustment Notice' in body and 'Actual Money Moved GHS 0.00' in body and 'Actual ordinary cost, payer, funding and payout independently verified' not in body and 'Download Private Supporting Evidence' not in body,'Notice excludes private reasoning and evidence')
check(owner.request(root+'&page=period')[0]==200 and staff.request(root+'&page=period')[0]==403,'Period review owner-only')
check('Expense Recording Adjustment' in owner.request('/finance.php?page=payments&direction=outgoing')[2],'Payment log includes no-money expense adjustments')
method=root+'&expense_id='+str(fixture['expenses']['method']['id'])
p=staff.fields(method,'request')|dict(type='method',method='momo',reference='HTTP-EC-METHOD-'+fixture['tag'],reason='Synthetic payout was actually MoMo',confirmed='1')
code,headers,_=staff.request(method,p);check(code==302,'Method correction request captured')
mid=int(re.search(r'id=(\d+)',headers['Location'])[1]);mdetail=root+'&id='+str(mid)
p=owner.fields(mdetail,'return')|dict(reason='Recheck payout reference',owner_decision='return');check(owner.request(mdetail,p)[0]==302,'Owner returns without posting')
revise=mdetail+'&form=revise';p=staff.fields(revise,'revise')|dict(type='method',method='momo',reference='HTTP-EC-REVISED-'+fixture['tag'],reason='Independent payout reference rechecked',confirmed='1');check(staff.request(revise,p)[0]==302,'Requester revises retained proposal')
body=owner.request(mdetail)[2];check('Proposal Versions' in body and 'HTTP-EC-METHOD-'+fixture['tag'] in body and 'HTTP-EC-REVISED-'+fixture['tag'] in body,'Old and current proposal facts retained')
p=owner.fields(mdetail,'post')|dict(verified='1',reason='Verified MoMo payout identity and separate cash funding');check(owner.request(mdetail,p)[0]==302,'Owner posts reviewed method')
unpaid=root+'&expense_id='+str(fixture['expenses']['unpaid']['id']);p=staff.fields(unpaid,'request')|dict(type='amount',amount='400.00',paid='400.00',reason='Synthetic no actual settlement source',confirmed='1');code,headers,_=staff.request(unpaid,p);uid=int(re.search(r'id=(\d+)',headers['Location'])[1]);udetail=root+'&id='+str(uid)
check(code==302 and re.search(r'<button disabled>Approve &amp; Post',owner.request(udetail)[2]),'Unpaid expense cannot masquerade as settled expense correction')
p=owner.fields(udetail,'return')|dict(reason='Establish actual settlement first',owner_decision='reject');check(owner.request(udetail,p)[0]==302,'Owner rejects without any financial posting')
try:
 internal=urllib.request.urlopen(BASE+'/expense-corrections.php',timeout=10);internal_status=internal.code;internal.close()
except urllib.error.HTTPError as e:internal_status=e.code
check(internal_status in [403,404],'Internal controller denies before loading application state')
audit=subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/expense_correction_audit_probe.php'),str(id)],cwd=ROOT,check=True,capture_output=True,text=True)
check(int(audit.stdout.strip())>0,'Expense case reads have correctly classified durable audit')
print('TOTAL '+str(count)+' expense correction HTTP assertions passed.')
