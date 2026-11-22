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
anon=Browser();check(anon.request('/stock-counts.php')[0]==302,'Anonymous stock count access requires login')
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/stock_count_http_seed.php')],cwd=ROOT,check=True,capture_output=True)
fixture=json.loads((ROOT/'.local-setup/stock-count-http-fixture.json').read_text(encoding='utf-8'));users=json.loads((ROOT/'.local-setup/handover-http-fixture.json').read_text(encoding='utf-8'))
staff=Browser();other=Browser();owner=Browser();admin=Browser()
staff.login(users['sender']['email'],'Synthetic Handover Passphrase 123!');other.login(users['third']['email'],'Synthetic Handover Passphrase 123!');owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!');admin.login(fixture['admin_email'],'Synthetic Stock Passphrase 123!')
path='/stock-counts.php';new=path+'?page=new'
check(staff.request(path+'?page=owner')[0]==403,'Staff cannot view owner queue')
check('Stock Counts & Variance Review' in html.unescape(staff.request('/occupancy.php?section=extras')[2]),'Extras links stock count workspace')
data=staff.fields(new,'start')|dict(items=json.dumps([fixture['extra']]),purpose='Weekly Stock Count',comment='PRIVATE-STOCK-'+secrets.token_hex(8))
check(staff.request(new,data|dict(csrf='invalid'))[0]==403,'Forged count denied by CSRF')
status,headers,_=staff.request(new,data);check(status==302,'Start count redirects after audited creation')
id=int(re.search(r'id=(\d+)',headers['Location'])[1]);detail=path+'?id='+str(id)
check(other.request(detail)[0]==403 and admin.request(detail)[0]==403,'Private count denied to other staff and administrators')
check('Expected At Count' not in staff.request(detail)[2],'Blind count hides expected quantity')
check(staff.request(detail,staff.fields(detail,'begin'))[0]==302,'Counter opens item window')
check(staff.request(detail,staff.fields(detail,'finish')|dict(qty='46.00',note='Two missing after full store check',confirmed='1'))[0]==302,'Counter saves physical count')
check('Expected At Count' in staff.request(detail)[2],'Completed count reveals comparison')
proof=b'%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF'
submit=staff.fields(detail,'submit')|dict(reasons=json.dumps({str(fixture['extra']):dict(reason='Missing / Unexplained',note='PRIVATE-STOCK-NOTE full store checked')}),confirmed='1')
check(staff.request(detail,submit,proof)[0]==302,'Count submits with optional protected evidence')
body=staff.request(detail)[2];file=html.unescape(re.search(r'href="([^"]*finance-evidence.php[^"]+)"',body)[1]);file='/finance-evidence.php?'+urllib.parse.urlsplit(file).query
check(staff.request(file)[2].encode()==proof,'Counter retrieves original private evidence')
check(admin.request(file)[0]==403 and other.request(file)[0]==403,'Other accounts cannot download evidence')
check('PRIVATE-STOCK-NOTE' not in admin.request('/finance.php?page=activity')[2],'Other admins cannot read private count notes through Finance')
check(staff.request(detail+'&form=approve')[0]==403,'Staff cannot load approval form')
check(admin.request(detail+'&form=approve')[0]==403,'Non-owner admin cannot load approval form')
p=detail+'&form=approve';ap=owner.fields(p,'approve')|dict(decision='Exact differences verified',verified='1',authorized='1')
check(owner.request(p,ap)[0]==302,'Owner approves exact difference')
check(owner.request(p,ap)[0]==302,'Identical HTTP retry remains idempotent')
body=owner.request(detail)[2]
check('Approved' in body and '46.00' in body and 'Approval Snapshot' in body,'Approved evidence and resulting quantity displayed')
check(owner.request(p)[0]==403,'Final count cannot reopen approval form')
check(staff.request('/occupancy.php?section=extras&form=stock_count')[0]==302,'Old count GET redirects to new workflow')
csrf=staff.fields(new,'start')['csrf']
check(staff.request('/occupancy.php',dict(csrf=csrf,operation_key=secrets.token_hex(16),action='stock_count',extra_id=fixture['extra'],qty='100'))[0]==409,'Old immediate-count POST rejected')
check(staff.request('/stock.php',dict(csrf=csrf,action='stock_count'))[0] in (403,409),'Legacy stock writer remains denied')
check('Stock Counts' in owner.request(path+'?page=owner')[2],'Owner queue remains reachable')
check('Count History' in staff.request(path+'?page=activity')[2],'Retained history remains reachable')
print('TOTAL '+str(count)+' stock-count HTTP checks passed.')
