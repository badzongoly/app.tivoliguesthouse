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
root='/owner-exceptions.php';anon=Browser();check(anon.request(root)[0]==302,'Owner register requires login')
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/weekly_audit_http_seed.php')],cwd=ROOT,check=True,capture_output=True)
fixture=json.loads((ROOT/'.local-setup/weekly-audit-http-fixture.json').read_text(encoding='utf-8'));owner=Browser();staff=Browser();admin=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
for b,name in [(staff,'staff'),(admin,'admin')]:b.login(fixture['users'][name]['email'],'Synthetic Audit Passphrase 123!')
check(staff.request(root)[0]==403 and admin.request(root)[0]==403,'Clerk and ordinary admin denied direct register')
body=owner.request(root)[2];check('Owner Exceptions' in body and 'Open Work' in body,'Owner register renders source queue')
check('Some sources are unavailable' in body,'Missing historical attribution visibly limits coverage')
for query in ['?view=resolved','?view=activity','?filter=overdue','?filter=decision&workflow=Property','?page=2']:
 check(owner.request(root+query)[0]==200,'Owner view '+query+' renders')
check(owner.request(root,{'action':'pay','operation_key':'0'*32})[0]==405,'Register rejects business POST actions')
for query in ['?view=invalid','?page[]=1','?key=weekly:2147483647','?key=%3Cscript%3E']:
 check(owner.request(root+query)[0]==400,'Invalid or missing source refused '+query)
code,_,body=owner.request(root+'?key=weekly:'+str(fixture['id']));check(code==200 and 'Open Existing Workflow' in body and 'Synthetic HTTP curtain repair' in body,'Source detail retains original findings and link')
check(re.search(r'href="[^"]*weekly-audits.php\?id='+str(fixture['id'])+'#audit-action"',body) is not None,'Detail points to actual original action')
body=body.split('<section id="exception-action"',1)[1].split('</section>',1)[0]
check(not re.search(r'<form[^>]+method="post"',body,re.I) and 'name="operation_key"' not in body,'Read-only detail has no posting form or competing operation key')
check('owner-exceptions.php' in owner.request('/owner_reports.php')[2] and 'owner-exceptions.php' not in admin.request('/owner_reports.php')[2],'Overview entry is configured-owner only')
print(f'Owner register HTTP: {n} assertions passed')
