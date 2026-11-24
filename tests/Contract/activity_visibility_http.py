import html,http.cookiejar,json,pathlib,re,urllib.request,urllib.parse,subprocess
base='http://127.0.0.1:8099'
with urllib.request.urlopen(base+'/auth/login.php') as guard:assert guard.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified service'
subprocess.run(['C:/xampp/php/php.exe',str(pathlib.Path(__file__).resolve().parents[2]/'tests/Integration/activity_visibility.php')],cwd=pathlib.Path(__file__).resolve().parents[2],check=True,capture_output=True)
fixture=json.loads((pathlib.Path(__file__).resolve().parents[2]/'.local-setup/activity-visibility-fixture.json').read_text(encoding='utf-8'))
count=0
class Browser:
 def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def get(self,path,data=None):
  try:r=self.opener.open(urllib.request.Request(base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None),timeout=45)
  except urllib.error.HTTPError as error:r=error
  assert r.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified server'
  body=r.read().decode('utf-8');assert not re.search(r'Fatal error|Parse error|Warning:|SQLSTATE',body)
  return r.status,body
 def login(self,email,password):
  body=self.get('/auth/login.php')[1];csrf=re.search(r'name="csrf" value="([^"]+)"',body)[1]
  assert self.get('/auth/login.php',dict(csrf=csrf,email=email,password=password))[0]==200
def check(ok,label):
 global count
 assert ok,label
 count+=1;print('PASS '+label,flush=True)
clerk=Browser();clerk.login(fixture['email'],'Synthetic Visibility Passphrase 123!')
admin=Browser();admin.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
def probes(body):
 return [json.loads(html.unescape(p)) for p in re.findall(r'<pre>(.*?)</pre>',body,re.S) if fixture['tag'] in p]
for who,browser,n in [('Non-admin',clerk,1),('Admin',admin,3)]:
 status,body=browser.get('/occupancy.php?booking_id='+str(fixture['booking'])+'&tab=activity')
 entries=probes(body)
 if who=='Non-admin':check(status==403 and not entries,'Direct Stay Activity is restricted to admins')
 else:check(status==200 and len(entries)==n,'Admin Stay Activity retains all recorded actors')
 status,body=browser.get('/occupancy.php?section=activity&actor='+str(fixture['admin']))
 actors=re.findall(r'<small>User #(\d+)</small>',body)
 check(status==200 and bool(actors) and (who=='Admin' or all(int(a)==fixture['clerk'] for a in actors)),who+' operational activity ignores forged actor scope')
 status,body=browser.get('/audit.php?action=stay.activity_scope_probe')
 entries=probes(body)
 check(status==200 and len(entries)==n,who+' Audit Trail has correct actor scope')
 if who=='Non-admin':check('name="actor"' not in body,'Non-admin audit form does not offer another user filter')
status,body=clerk.get('/audit.php?action=stay.activity_scope_probe&actor='+str(fixture['admin']))
check(status==200 and not probes(body),'Forged audit actor filter cannot reveal another user')
status,body=clerk.get('/finance.php?page=activity')
check(status==403 and not probes(body),'Finance Audit Trail remains admin-only')
for who,browser in [('Non-admin',clerk),('Admin',admin)]:
 status,body=browser.get('/housekeeping.php?page=activity&actor='+str(fixture['admin']))
 check(status==200 and ('#'+str(fixture['attempts'][0])) in body and (who=='Admin' or ('#'+str(fixture['attempts'][1])) not in body),who+' housekeeping history has correct actor scope')
print('TOTAL '+str(count)+' HTTP activity scope assertions passed')
