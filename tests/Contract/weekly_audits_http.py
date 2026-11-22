"""Real workflow on the guarded synthetic HTTP instance only."""
import html,http.cookiejar,json,pathlib,re,secrets,subprocess,urllib.request,urllib.error,urllib.parse
ROOT=pathlib.Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:8099'
class NR(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
class Browser:
 def __init__(self):self.op=urllib.request.build_opener(NR(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,path,data=None,file=None):
  headers={};body=urllib.parse.urlencode(data).encode() if data is not None else None
  if file:
   boundary='Audit'+secrets.token_hex(8);parts=[]
   for k,v in data.items():parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"\r\n\r\n'+str(v)+'\r\n').encode())
   parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="evidence"; filename="proof.pdf"\r\nContent-Type: application/pdf\r\n\r\n').encode()+file+b'\r\n');parts.append(('--'+boundary+'--\r\n').encode());body=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
  try:r=self.op.open(urllib.request.Request(BASE+path,data=body,headers=headers),timeout=40)
  except urllib.error.HTTPError as e:r=e
  raw=r.read();text=raw.decode('utf-8');assert r.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified service';assert not re.search(r'Fatal error|Warning:|Parse error|SQLSTATE\[',text),'Application output error';return r.code,r.headers,text
 def fields(self,path,action):
  code,_,text=self.request(path);assert code==200,(path,code,text[:180]);forms=re.findall(r'<form\b[^>]*>(.*?)</form>',text,re.S);f=next(x for x in forms if 'name="action" value="'+action+'"' in x);return {k:html.unescape(v) for k,v in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',f)}
 def login(self,email,password):
  text=self.request('/auth/login.php')[2];csrf=html.unescape(re.search(r'name="csrf" value="([^"]+)"',text)[1]);assert self.request('/auth/login.php',dict(csrf=csrf,email=email,password=password))[0]==302
n=0
def check(value,label):
 global n
 assert value,label
 n+=1;print('PASS '+label,flush=True)
root='/weekly-audits.php';anon=Browser();check(anon.request(root)[0]==302,'Login required on direct audit endpoint')
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/weekly_audit_http_seed.php')],cwd=ROOT,check=True,capture_output=True)
fixture=json.loads((ROOT/'.local-setup/weekly-audit-http-fixture.json').read_text(encoding='utf-8'));owner=Browser();staff=Browser();other=Browser();admin=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
for b,name in [(staff,'staff'),(other,'other'),(admin,'admin')]:b.login(fixture['users'][name]['email'],'Synthetic Audit Passphrase 123!')
detail=root+'?id='+str(fixture['id']);check(staff.request(detail)[0]==200 and other.request(detail)[0]==403,'Only assigned or source staff reads task');check(staff.request(root+'?page=duty')[0]==403 and admin.request(root+'?page=duty')[0]==403,'Audit delegation page owner-only');check(other.request(root+'?page=room&form=new')[0]==403,'Unassigned clerk cannot record audits')
for page in ['room','area','followups','duty','legacy']:check(owner.request(root+'?page='+page+'&range=all')[0]==200,'Owner '+page+' view renders')
fields=staff.fields(detail,'done');check(staff.request(detail,fields|{'csrf':'bad'})[0]==403,'Forged completion rejected');proof=b'%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF';code,headers,_=staff.request(detail,fields,proof);check(code==302 and '#audit-action' in headers.get('Location',''),'Completion redirects focus to task');check(staff.request(detail,fields,proof)[0]==302,'Same-key same-file HTTP retry accepted once');check('Awaiting Verification' in owner.request(detail)[2],'Staff completion requires verification');body=staff.request(detail)[2];download=html.unescape(re.search(r'href="([^"]*weekly-audits.php\?download=[^"]+)"',body)[1]);path='/weekly-audits.php?'+urllib.parse.urlsplit(download).query;check(staff.request(path)[2]==proof.decode() and owner.request(path)[0]==200 and other.request(path)[0]==403,'Private download enforces task readers')
close=owner.fields(detail,'close');check(admin.request(detail,close)[0]==403,'Non-owner cannot verify even with copied form');check(owner.request(detail,close)[0]==400,'Owner must attest completed-work review');close=owner.fields(detail,'close')|{'verified':'1'};check(owner.request(detail,close)[0]==302 and 'Verified &amp; Closed' in owner.request(detail)[2],'Owner verification closes task')
check('name="action" value="done"' not in staff.request(detail)[2],'Closed action offers no completion writer');check('Follow-Ups' in owner.request('/occupancy.php?section=rooms')[2],'Existing Property page links follow-ups')
source=re.search(r'href="([^"]*page=room&amp;source_id=[^"]+)"',staff.request(detail)[2]);check(bool(source),'Task links original room audit');sourcepath='/weekly-audits.php?'+urllib.parse.urlsplit(html.unescape(source[1])).query;check(staff.request(sourcepath)[0]==200,'Assigned staff can inspect original source')
history_path=root+'?page=area&source_id='+fixture['history_key'];body=owner.request(history_path)[2];link=html.unescape(re.search(r'href="([^"]*history_before=[^"]+)"',body)[1]);older='/weekly-audits.php?'+urllib.parse.urlsplit(link).query;check('Original Area Audit History' in owner.request(older)[2] and 'Synthetic original progress 0' in owner.request(older)[2],'Original history remains accessible beyond fifty events')
print(f'Weekly audit HTTP: {n} assertions passed')
