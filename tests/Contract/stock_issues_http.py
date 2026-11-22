"""Acceptance only against the marked synthetic test web service."""
import datetime,html,http.cookiejar,json,pathlib,re,secrets,subprocess,urllib.request,urllib.error,urllib.parse
ROOT=pathlib.Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:8099'
class NR(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*a,**kw):return None
class Browser:
 def __init__(self):self.op=urllib.request.build_opener(NR(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,path,data=None,file=None):
  if path.startswith(BASE):path=path[len(BASE):]
  assert path.startswith('/') and not path.startswith('//'),'Refusing non-test route'
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
seed=subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/stock_issue_http_seed.php')],cwd=ROOT,capture_output=True,text=True,encoding='utf-8');assert seed.returncode==0,seed.stderr
fixture=__import__('json').loads((ROOT/'.local-setup/stock-issue-http-fixture.json').read_text(encoding='utf-8'))
anonymous=Browser();check(anonymous.request('/stock-issues.php')[0]==302,'stock issue endpoint requires login')
staff=Browser();staff.login(fixture['users']['staff']['email'],'Synthetic Stock Passphrase 123!');owner=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
url='/stock-issues.php?id='+str(fixture['id'])
check(owner.request('/stock-issues.php')[0]==200,'owner can open stock issue register')
check(staff.request(url)[0]==200,'reporter can read original issue')
check(staff.request(url+'&form=approve')[0]==403,'direct clerk approval form denied')
f=owner.fields(url+'&form=approve','approve');check(bool(re.fullmatch('[a-f0-9]{32}',f['operation_key'])),'shared navigation retains fresh operation key')
check(owner.request(url,{**f,'verified':'1','csrf':'bad'})[0]==403,'approval requires valid CSRF')
check(owner.request(url,{**f,'verified':'1','operation_key':'bad'})[0]==400,'malformed durable operation key rejected')
check(owner.request(url,{**f,'verified':'1','comment':'Synthetic verified report'})[0]==302,'owner approves exact original issue')
check('Confirm Actual Disposal' in owner.request(url)[2],'approved held stock still requires physical disposition')
f=owner.fields(url+'&form=dispose','dispose');f.update(verified='1',actual_at=__import__('datetime').datetime.now(__import__('datetime').timezone.utc).strftime('%Y-%m-%dT%H:%M:%S'))
check(owner.request(url,f)[0]==302,'actual disposal records completed movement')
check(owner.request(url,f)[0]==302,'exact disposition retry returns original result')
check(owner.request(url,{**f,'actual_at':'2020-01-01T00:00:00'})[0]==400,'changed replay details rejected')
check('Completed' in staff.request(url)[2] and '22.00' in staff.request(url)[2],'completion retains accessible history and exact balance')
form=staff.fields('/stock-issues.php?page=new&extra_id='+str(fixture['extra']),'report')
form.update(extra_id=str(fixture['extra']),kind='complimentary',qty='1.25',position='held',actual_at=__import__('datetime').datetime.now(__import__('datetime').timezone.utc).strftime('%Y-%m-%dT%H:%M:%S'),verified='1',comment='Synthetic complimentary welcome')
pdf=b'%PDF-1.4\n1 0 obj <<>> endobj\n%%EOF'
result=staff.request('/stock-issues.php?page=new',form,pdf);check(result[0]==302,'optional private supporting PDF accepted')
proof_url=result[1]['Location'].split('#')[0];check(staff.request('/stock-issues.php?page=new',form,pdf)[0]==302,'same report and evidence retry is durable')
body=staff.request(proof_url)[2];match=re.search(r'(?:href=")([^" ]*finance-evidence.php[^" ]*)',body);check(bool(match),'retained supporting evidence link shown')
evidence=html.unescape(match[1]);check(staff.request(evidence)[0]==200,'authorized inventory staff can retrieve original encrypted evidence')
other=Browser();other.login(fixture['users']['other']['email'],'Synthetic Stock Passphrase 123!');check(other.request(proof_url+'&form=clarify')[0]==403,'other staff cannot clarify another reporter source')
check('Non-Sale Issues' in staff.request('/occupancy.php?section=extras')[2],'existing Extras and Stock navigation includes non-sale issues')

fault=str(ROOT/'tests/Integration/stock_issue_http_fault.php')
form=staff.fields('/stock-issues.php?page=new&extra_id='+str(fixture['extra']),'report');form.update(extra_id=str(fixture['extra']),kind='damage',qty='1',position='held',actual_at=__import__('datetime').datetime.now(__import__('datetime').timezone.utc).strftime('%Y-%m-%dT%H:%M:%S'),verified='1',comment='Synthetic retry after audit rollback')
subprocess.run(['C:/xampp/php/php.exe',fault,'on'],cwd=ROOT,check=True,capture_output=True)
try:
 code,_,body=staff.request('/stock-issues.php?page=new',form,pdf)
 check(code==503 and 'Retry Same Action' in body,'server failure presents identical-action retry')
 retry=re.search(r'<form[^>]*id="stockissue-retry"[^>]*>(.*?)</form>',body,re.S)[1];fields={k:html.unescape(v) for k,v in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',retry)}
 check(fields['operation_key']==form['operation_key'] and fields['actual_at']==form['actual_at'],'server-error form retains original operation key and actual facts')
finally:subprocess.run(['C:/xampp/php/php.exe',fault,'off'],cwd=ROOT,check=True,capture_output=True)
check(staff.request('/stock-issues.php?page=new',fields)[0]==400,'retry cannot silently omit originally attached evidence')
check(staff.request('/stock-issues.php?page=new',fields,pdf+b'\nDIFFERENT')[0]==400,'retry requires the original supporting-file contents')
check(staff.request('/stock-issues.php?page=new',fields,pdf)[0]==302,'same action key and original evidence safely succeed after required-audit rollback')
print(f'TOTAL {count} stock issue HTTP checks passed.')

