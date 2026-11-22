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
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/receipt_corrections_http_seed.php')],cwd=ROOT,check=True,capture_output=True)
fixture=json.loads((ROOT/'.local-setup/receipt-corrections-http-fixture.json').read_text(encoding='utf-8'))
root='/financial-corrections.php';owner=Browser();staff=Browser();other=Browser();admin=Browser();outsider=Browser()
owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
for b,k in [(staff,'staff'),(other,'other'),(admin,'admin'),(outsider,'outsider')]:b.login(fixture['users'][k]['email'],'Synthetic Correction Passphrase 123!')
check(Browser().request(root)[0]==302,'Corrections require login')
check(outsider.request(root)[0]==403,'Accounts without booking authority denied')
check(owner.request('/finance.php?page=corrections')[0]==302,'Finance correction alias routes to tab')
body=staff.request(root)[2];check('Financial Corrections' in body and 'Finance sections' in body,'Correction workspace uses Finance & Cash navigation')
check(staff.request(root+'?page=review')[0]==403 and admin.request(root+'?page=review')[0]==403,'Only configured owner opens review queue')
payment=fixture['receipts']['amount']['payment'];source=root+'?payment_id='+str(payment)
check(other.request(source)[0]==403,'Staff cannot view another collector source')
p=staff.fields(source,'request')|dict(type='amount',amount='300.00',reason='Synthetic actual collection independently evidenced.',confirmed='1')
check(staff.request(source,p|dict(csrf='bad'))[0]==403,'Forged correction request denied')
check(staff.request(source,p|dict(operation_key='invalid'))[0]==400,'Malformed operation key cannot become a fresh write')
proof=b'%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF'
code,headers,_=staff.request(source,p,proof);check(code==302,'Request with optional evidence records')
id=int(re.search(r'id=(\d+)',headers['Location'])[1]);detail=root+'?id='+str(id)
check(other.request(detail)[0]==403 and admin.request(detail)[0]==403,'Private request restricted to requester and owner')
check('Synthetic actual collection independently evidenced.' not in owner.request('/finance.php?page=activity')[2],'Generic finance activity excludes private correction case notes')
body=staff.request(detail)[2];check('Awaiting Owner' in body and 'Approve &amp; Post' not in body,'Requester cannot post correction')
retry=staff.request(source,p,proof)
if retry[0]!=302: print('Retry diagnostic',retry[0],re.findall(r'Action Needs Attention</h2><p>(.*?)</p>',retry[2]),flush=True)
check(retry[0]==302,'Request operation retry is idempotent')
new=staff.fields(source,'request')|dict(type='amount',amount='350.00',reason='Competing source request',confirmed='1')
check(staff.request(source,new)[0]==400,'Second active source request denied')
body=owner.request(detail)[2];check('-200.00' in body and 'GHS 200.00' in body,'Owner sees exact negative adjustment and positive guest debt')
link=html.unescape(re.search(r'href="([^"]*finance-evidence.php[^"]+)"',body)[1]);evidence='/finance-evidence.php?'+urllib.parse.urlsplit(link).query
check(owner.request(evidence)[0]==200 and staff.request(evidence)[0]==200 and other.request(evidence)[0]==403 and admin.request(evidence)[0]==403,'Correction evidence restricted to requester and owner')
p=owner.fields(detail,'post')|dict(reason='',verified='1')
code,_,body=owner.request(detail,p);check(code==400 and 'Owner Review' in body and 'Approve &amp; Post' in body,'Validation keeps owner action form')
p=owner.fields(detail,'post')|dict(reason='Independent collection evidence and original receipt verified',verified='1')
check(owner.request(detail,p)[0]==302 and owner.request(detail,p)[0]==302,'Owner posts once and safely retries')
body=staff.request(detail)[2];check('Posted Adjustment ADJ-' in body and 'Actual Money Moved' in body,'Posted detail labels recording adjustment')
check(owner.request(root+'?id='+str(id)+'&page=notice')[0]==200,'Printable adjustment notice available')
body=staff.request(root+'?id='+str(id)+'&page=notice')[2];check('Independent collection evidence and original receipt verified' not in body and 'Supporting Evidence' not in body,'Guest notice excludes private review reasons and files')
body=owner.request('/occupancy.php?booking_id='+str(fixture['receipts']['amount']['booking']))[2];check('200.00' in body and 'Recover Outstanding' in body,'Guest account retains checked-out stay with corrected recovery due')
body=owner.request('/finance.php?page=payments')[2];check('No Money Moved' in body and 'Recording Adjustment' in body,'Payment Log explicitly distinguishes adjustments')
check(owner.request(root+'?page=period')[0]==200 and staff.request(root+'?page=period')[0]==403,'Period effects owner-only')
methodSource=root+'?payment_id='+str(fixture['receipts']['method']['payment'])
p=staff.fields(methodSource,'request')|dict(type='method',method='momo',reference='HTTP-METHOD-'+fixture['tag'],reason='Actual collection used MoMo',confirmed='1')
code,headers,_=staff.request(methodSource,p);check(code==302,'Method correction requested')
mid=int(re.search(r'id=(\d+)',headers['Location'])[1]);mdetail=root+'?id='+str(mid)
p=owner.fields(mdetail,'return')|dict(action='return',reason='Recheck actual reference')
check(owner.request(mdetail,p)[0]==302,'Owner returns without posting')
revise=mdetail+'&form=revise';p=staff.fields(revise,'revise')|dict(type='method',method='momo',reference='HTTP-REVISED-'+fixture['tag'],reason='Reference independently rechecked',confirmed='1')
check(staff.request(revise,p)[0]==302,'Requester revises returned request')
body=owner.request(mdetail)[2];check('Earlier Proposed Versions' in body and 'HTTP-METHOD-'+fixture['tag'] in body and 'HTTP-REVISED-'+fixture['tag'] in body,'Prior and current proposals inspectable')
p=owner.fields(mdetail,'post')|dict(reason='Verified corrected method and reference',verified='1');check(owner.request(mdetail,p)[0]==302,'Owner posts exact revised method')
depSource=root+'?payment_id='+str(fixture['receipts']['deposit']['payment'])
p=staff.fields(depSource,'request')|dict(type='amount',amount='300.00',reason='Investigate deposited discrepancy',confirmed='1');code,headers,_=staff.request(depSource,p)
check(code==400,'Deposited source requires explicit linked evidence review before submission')

p=staff.fields(source,'request')|dict(type='refund',reason='Actual money returned',confirmed='1');check(staff.request(depSource,p)[0]==400,'Recording correction cannot masquerade as actual refund')
print('TOTAL '+str(count)+' receipt correction HTTP assertions passed.')
