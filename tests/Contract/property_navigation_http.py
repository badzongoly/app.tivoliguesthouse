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

f=json.loads((ROOT/'.local-setup/weekly-audit-http-fixture.json').read_text(encoding='utf-8'));ui=json.loads((ROOT/'.local-setup/weekly-ui-fixture.json').read_text(encoding='utf-8'));n=0
def check(ok,label):
 global n
 assert ok,label
 n+=1;print('PASS '+label,flush=True)
owner=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!');staff=Browser();staff.login(f['users']['staff']['email'],'Synthetic Audit Passphrase 123!');other=Browser();other.login(f['users']['other']['email'],'Synthetic Audit Passphrase 123!')
paths=[('/occupancy.php?section=rooms','Room Master'),('/occupancy.php?section=rooms&tab=faults','Out-Of-Order Log'),('/occupancy.php?section=rooms&tab=audits','Room Audits'),('/occupancy.php?section=areas','Other Area Audits'),('/weekly-audits.php?page=room','Room Audits'),('/weekly-audits.php?page=area','Other Area Audits'),('/weekly-audits.php','Follow-Ups'),('/weekly-audits.php?page=duty','Audit Duty')]
for path,active in paths:
 code,_,body=owner.request(path);check(code==200,path+' renders')
 navs=re.findall(r'<nav[^>]*aria-label="Property & Audits"[^>]*>(.*?)</nav>',body,re.S) or re.findall(r'<nav[^>]*aria-label="Property &amp; Audits"[^>]*>(.*?)</nav>',body,re.S)
 check(len(navs)==1,path+' has one property navigation')
 labels=re.findall(r'<a[^>]*>(.*?)</a>',navs[0],re.S)
 check(labels==['Room Master','Out-Of-Order Log','Room Audits','Other Area Audits','Follow-Ups','Audit Duty'],path+' has consistent ordered owner tabs')
 check(re.findall(r'<a[^>]*aria-current="page"[^>]*>(.*?)</a>',navs[0],re.S)==[active],path+' has one correct selected tab')
 check(body.count('>Audit Duty</a>')==1,path+' owner duty entry is not repeated')
body=owner.request('/occupancy.php?section=rooms')[2]
check('>Cleaning Complete</a>' not in body and '>Inspect &amp; Release</a>' not in body,'Room Master no longer duplicates occupancy preparation actions')
for path,query in [('/occupancy.php?section=rooms&tab=audits','page=room&amp;form=new'),('/occupancy.php?section=areas','page=area&amp;form=new')]:
 body=owner.request(path)[2];check(query in body,'Legacy audit register offers canonical new audit entry '+path)
body=owner.request('/weekly-audits.php?page=area&source_id='+str(ui['legacy']))[2]
check('>Original Audit</a>' not in body,'Original audit page does not link to itself')
for browser,label in [(staff,'Delegated auditor'),(other,'Other clerk')]:
 body=browser.request('/weekly-audits.php')[2]
 check('>Room Master</a>' not in body and '>Out-Of-Order Log</a>' not in body and '>Audit Duty</a>' not in body,label+' is not offered administrator controls')
check(staff.request('/weekly-audits.php?page=duty')[0]==403,'Duty changes remain owner-only')
check('name="action" value="assign"' in owner.request('/weekly-audits.php?id='+str(ui['ids']['unassigned']))[2],'Owner can still assign a named person and deadline')
assignee=Browser();assignee.login(ui['email'],'Synthetic Audit Passphrase 123!')
check('name="action" value="done"' in assignee.request('/weekly-audits.php?id='+str(ui['ids']['assigned']))[2],'Actual assignee still has Mark Done')
check('name="action" value="close"' in owner.request('/weekly-audits.php?id='+str(ui['ids']['review']))[2],'Owner verification form remains')
print('TOTAL '+str(n)+' property navigation HTTP assertions passed')
