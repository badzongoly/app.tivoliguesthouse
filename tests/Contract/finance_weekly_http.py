import urllib.request as u,urllib.error as e,urllib.parse as p,http.cookiejar,re,json,secrets,pathlib
class NR(u.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
class Browser:
 def __init__(self):self.o=u.build_opener(NR(),u.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def get(self,path,data=None):
  try:r=self.o.open(u.Request('http://127.0.0.1:8099'+path,data=p.urlencode(data).encode() if data is not None else None),timeout=10)
  except e.HTTPError as x:r=x
  assert r.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified endpoint'
  b=r.read().decode('utf-8');assert not any(s in b for s in ['Fatal error','Warning:','Parse error','SQLSTATE[']),'Page error'
  return r.code,b,r.headers
 def login(self,email,password):
  b=self.get('/auth/login.php')[1];t=re.search('name="csrf" value="([^"]+)"',b)[1]
  r=self.get('/auth/login.php',dict(csrf=t,email=email,password=password));assert r[0]==302,('login',r[0]);print('Login redirect',r[2].get('Location'))
a=Browser();a.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
s,b,h=a.get('/finance.php?page=weekly');assert s==200,(s,h.get('Location'))
assert all(t in b for t in ['Review Totals','Lock With Open Exceptions','Keep Week Open','MoMo Receipts'])
print('PASS owner weekly view and three decisions')
path='/finance.php?page=weekly&start=2002-01-07'
s,b,_=a.get(path);assert s==200
f=dict(action='week_decide',start='2002-01-07',outcome='open',comment='Synthetic HTTP '+secrets.token_hex(4),operation_key=secrets.token_hex(16))
assert a.get(path,f)[0]==403
f['csrf']=re.search('name="csrf" value="([^"]+)"',b)[1]
assert a.get(path,f)[0]==302
assert f['comment'] in a.get(path)[1]
print('PASS CSRF enforcement, keep-open decision and retained history')
fixture=json.loads((pathlib.Path(__file__).resolve().parents[2]/'.local-setup/finance-fixture.json').read_text(encoding='utf-8'))
c=Browser();c.login(fixture['clerk_email'],'Synthetic Finance Passphrase 123!')
assert c.get('/finance.php?page=weekly')[0]==403
print('PASS clerk denied weekly access')
