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

fixture=json.loads((ROOT/'.local-setup/stock-issue-http-fixture.json').read_text(encoding='utf-8'))
count=0
def check(ok,label):
 global count
 assert ok,label
 count+=1;print('PASS '+label,flush=True)
owner=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
staff=Browser();staff.login(fixture['users']['staff']['email'],'Synthetic Stock Passphrase 123!')
cases=[('/occupancy.php?section=extras','Manage Extras'),('/occupancy.php?section=extras&tab=stock','Stock Movements'),('/extra_sales.php','Extra Sales'),('/stock-counts.php','Stock Counts'),('/stock-counts.php?page=new','Stock Counts'),('/stock-issues.php','Non-Sale Issues'),('/stock-issues.php?page=new','Non-Sale Issues')]
for b,role in [(owner,'owner'),(staff,'clerk')]:
 for path,active in cases:
  code,_,body=b.request(path);check(code==200,role+' '+path+' renders')
  navs=re.findall(r'<nav\b[^>]*aria-label="Extras &amp; Stock"[^>]*>(.*?)</nav>',body,re.S) or re.findall(r'<nav\b[^>]*aria-label="Extras & Stock"[^>]*>(.*?)</nav>',body,re.S)
  check(len(navs)==1,role+' has one shared Extras & Stock navigation')
  allnav=''.join(re.findall(r'<nav\b[^>]*>(.*?)</nav>',body,re.S))
  check(len(re.findall(r'>Non-Sale Issues</a>',allnav))==1,role+' Non-Sale Issues appears once in navigation')
  current=re.findall(r'<a\b[^>]*aria-current="page"[^>]*>(.*?)</a>',navs[0],re.S)
  check(current==[active],role+' correct active tab '+active)
  check('>Stock Counts</a>' in navs[0] and '>Non-Sale Issues</a>' in navs[0],role+' linked count and issue workflows reachable')
  check(('>Stock Costing</a>' in navs[0])==(role=='owner'),role+' costing remains owner-only')
body=owner.request('/occupancy.php?section=extras&form=extra_save')[2]
check('extras-navigation' in body and 'name="action" value="extra_save"' in body,'Catalogue form keeps shared navigation and existing writer')
check(staff.request('/stock-issues.php')[2].count('>Report / Request Issue</a>')==1,'Non-sale register has exactly one report action')
check('Start Count' in staff.request('/stock-counts.php')[2],'Count register keeps its start action')
print('TOTAL '+str(count)+' extras navigation checks passed')
