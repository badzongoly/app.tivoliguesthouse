import urllib.request,urllib.parse,urllib.error,http.cookiejar,re,html,secrets,json,subprocess
BASE='http://127.0.0.1:8099'
ROOT='C:/xampp/htdocs/app.tivoliguesthouse'
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
class Browser:
 def __init__(self):self.opener=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,path,data=None):
  req=urllib.request.Request(BASE+path,data=urllib.parse.urlencode(data).encode() if data is not None else None)
  try:r=self.opener.open(req,timeout=20)
  except urllib.error.HTTPError as e:r=e
  body=r.read().decode('utf-8')
  assert r.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unsafe HTTP server'
  assert not any(x in body for x in ['Fatal error','Parse error','Warning:','SQLSTATE[']),'Application error'
  return r.code,r.headers,body
 def login(self,email,password):
  body=self.request('/auth/login.php')[2];csrf=re.search('name="csrf" value="([a-f0-9]{64})"',body).group(1)
  return self.request('/auth/login.php',{'csrf':csrf,'email':email,'password':password})
n=0
def check(ok,label):
 global n
 assert ok,label
 n+=1;print('PASS '+label,flush=True)
b=Browser();check(b.request('/finance.php?page=profit')[0]==302,'anonymous costing access requires login')
check(b.login('admin@example.invalid','Synthetic Admin Passphrase 123!')[0]==302,'synthetic owner login')
code,_,body=b.request('/finance.php?page=profit');check(code==200 and 'Stock Costing &amp; Profit Accuracy' in body,'cost bridge lives in existing Profit Review')
check('Recorded-Cost Result' in body and 'Incomplete' in body,'legacy recorded result and incomplete coverage disclosed')
check('Stock Costing' in b.request('/occupancy.php?section=extras')[2],'owner costing link reuses Extras and Stock navigation')
check(b.request('/finance.php?page=profit',{'action':'costing_start','operation_key':secrets.token_hex(16),'extra_id':'1'})[0]==403,'costing POST requires CSRF')
seed=__import__('pathlib').Path(ROOT)/'tests/Integration/stock_costing_http_seed.php'
run=subprocess.run(['C:/xampp/php/php.exe',str(seed)],cwd=ROOT,capture_output=True,text=True,encoding='utf-8',check=True);fixture=json.loads(run.stdout)
path='/finance.php?page=profit&costing_id='+str(fixture['extra'])
def fields(body,action):
 for form in re.findall(r'<form\b[^>]*>(.*?)</form>',body,re.S):
  if f'name="action" value="{action}"' in form:
   return {html.unescape(k):html.unescape(v) for k,v in re.findall(r'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"',form)}
 raise AssertionError('Missing action form '+action)
body=b.request(path)[2];form=fields(body,'costing_start');form.update(extra_id=str(fixture['extra']),count_id=str(fixture['count']),total='',evidence='Synthetic private opening HTTP proof',verified='1')
bad=dict(form);bad.pop('action');bad['action[]']='costing_start';check(b.request(path,bad)[0]==400,'malformed action array is rejected without application warnings')
check(b.request(path,form)[0]==302,'owner starts unknown-cost item from existing approved count')
check(b.request(path,form)[0]==302,'identical owner setup POST safely replays')
body=b.request(path)[2];check(len(re.findall(r'· Opening</td>',body))==1,'only one opening history row after repeated submission')
changed=dict(form,total='24');check(b.request(path,changed)[0]==400,'same key cannot change original opening details')
body=b.request(path)[2];evidence=fields(body,'costing_evidence');evidence.update(total='24',evidence='Synthetic private independently verified receipt',verified='1')
check(b.request(path,evidence)[0]==302 and b.request(path,evidence)[0]==302,'supported evidence saves and replays without a second entry')
body=b.request(path)[2];check('₵24.00' in body and 'Supported Sources' in body,'owner sees supported existing inventory value')
clerk=Browser();check(clerk.login(fixture['staff'],'Synthetic Stock Passphrase 123!')[0]==302,'synthetic clerk login')
check(clerk.request(path)[0]==403 and clerk.request('/finance.php?page=profit',evidence)[0]==403,'clerk cannot view or submit costing evidence')
admin=Browser();check(admin.login(fixture['admin'],'Synthetic Stock Passphrase 123!')[0]==302,'synthetic second administrator login')
check(admin.request(path)[0]==403,'other administrator cannot open private owner cost profile')
body=admin.request('/finance.php?page=activity')[2];check('Synthetic private opening HTTP proof' not in body and 'Synthetic private independently verified receipt' not in body,'cost evidence is excluded from other administrators finance audit view')
print(f'TOTAL {n} stock costing HTTP assertions passed.')
