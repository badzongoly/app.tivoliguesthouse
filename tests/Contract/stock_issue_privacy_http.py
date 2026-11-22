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


seed=subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/stock_issue_http_seed.php')],cwd=ROOT,capture_output=True,text=True,encoding='utf-8');assert seed.returncode==0,seed.stderr
fixture=__import__('json').loads((ROOT/'.local-setup/stock-issue-http-fixture.json').read_text(encoding='utf-8'));staff=Browser();staff.login(fixture['users']['staff']['email'],'Synthetic Stock Passphrase 123!');viewer=Browser();viewer.login(fixture['users']['viewer']['email'],'Synthetic Stock Passphrase 123!')
form=staff.fields('/stock-issues.php?page=new&extra_id='+str(fixture['extra']),'report');marker='SYNTHETIC-INVENTORY-REVIEW-'+secrets.token_hex(8);form.update(qty='1',kind='damage',position='held',actual_at=__import__('datetime').datetime.now(__import__('datetime').timezone.utc).strftime('%Y-%m-%dT%H:%M:%S'),verified='1',comment=marker)
result=staff.request('/stock-issues.php?page=new',form,b'%PDF-1.4\n1 0 obj <<>> endobj\n%%EOF');assert result[0]==302
url=result[1]['Location'].split('#')[0];assert viewer.request(url)[0]==403;print('PASS finance-only viewer cannot inspect inventory source')
body=staff.request(url)[2];match=re.search(r'href="([^" ]*finance-evidence.php[^" ]*)',body);assert match
assert viewer.request(html.unescape(match[1]))[0]==403;print('PASS finance-only viewer cannot retrieve inventory evidence')
code,_,body=viewer.request('/finance.php?page=activity');assert code==403
assert marker not in body,'Finance activity must not expose an inventory note to a user without stock authority';print('PASS finance activity respects inventory source access')
