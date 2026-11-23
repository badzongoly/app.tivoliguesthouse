"""Merged workflow acceptance against the explicitly marked synthetic server."""
import html,http.cookiejar,json,pathlib,re,secrets,subprocess,urllib.request,urllib.error,urllib.parse
ROOT=pathlib.Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:8099'
class NR(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*a,**kw):return None
class Browser:
 def __init__(self):self.op=urllib.request.build_opener(NR(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,path,data=None):
  try:r=self.op.open(urllib.request.Request(BASE+path,data=urllib.parse.urlencode(data).encode() if data is not None else None),timeout=60)
  except urllib.error.HTTPError as e:r=e
  text=r.read().decode('utf-8');assert r.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified service';assert not re.search(r'Fatal error|Warning:|Parse error|SQLSTATE\[',text),'Application output error'
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
root='/occupancy.php?section=housekeeping';anon=Browser();check(anon.request(root)[0]==302,'Merged housekeeping requires login')
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/housekeeping_http_seed.php')],cwd=ROOT,check=True,capture_output=True)
f=json.loads((ROOT/'.local-setup/housekeeping-http-fixture.json').read_text(encoding='utf-8'));staff=Browser();inspector=Browser();outsider=Browser()
for b,k in [(staff,'staff'),(inspector,'inspector'),(outsider,'outsider')]:b.login(f['users'][k]['email'],'Synthetic Housekeeping Passphrase 123!')
check(outsider.request(root)[0]==403,'Merged route preserves housekeeping permission denial')
check(staff.request(root+'&page=owner')[0]==403,'Owner review remains restricted')
for selected in ['needs_cleaning','cleaning','inspection']:
 body=staff.request('/occupancy.php?preparation='+selected)[2];states=re.findall(r'data-preparation="([^"]+)"',body)
 check(all(s==selected for s in states),'Room Board filter '+selected+' returns only matching states')
 check('title="Housekeeping"' not in body and 'Room Preparation Filters' in body,'Room Board hosts preparation without separate sidebar entry '+selected)
room=root+'&room_id='+str(f['room']);code,_,body=staff.request(room)
check(code==200 and '<h1>Check-In &amp; Occupancy</h1>' in body and 'Guest Stay Account' in body,'Cleaning opens in the common occupancy workspace')
p=staff.fields(room,'start');check(staff.request(room,p|dict(csrf='invalid'))[0]==403,'Merged start rejects forged CSRF')
code,headers,_=staff.request(room,p);id=int(re.search(r'[?&]id=(\d+)',headers.get('Location',''))[1])
check(code==302 and 'occupancy.php?section=housekeeping' in headers['Location'],'Start redirects within occupancy to captured attempt')
code,headers,_=staff.request(room,p);check(code==302 and int(re.search(r'[?&]id=(\d+)',headers['Location'])[1])==id,'Repeated start reuses operation identity')
detail=root+'&id='+str(id);clean=detail+'&form=clean';check(inspector.request(clean)[0]==403,'Only assigned cleaner opens cleaning wizard')
body=staff.request(clean)[2];items=re.findall(r'name="done\[\]" value="([^"]+)"',body);steps=len(re.findall(r'data-clean-step=',body));check(bool(items) and 'data-select-clean="all"' in body,'Existing captured wizard and Select All remain available')
for step in range(steps):
 p=staff.fields(clean,'progress')|dict(done=json.dumps(items),direction='complete' if step==steps-1 else 'next',verified='1')
 code,headers,_=staff.request(clean,p);check(code==302 and 'occupancy.php?section=housekeeping' in headers['Location'],'Cleaning step '+str(step+1)+' stays in occupancy')
check(staff.request(detail+'&form=inspect')[0]==403,'Cleaner still requires independent inspection or owner exception')
inspect=detail+'&form=inspect';body=inspector.request(inspect)[2];ids=re.findall(r'name="result_([^"]+)" value="pass"',body);p=inspector.fields(inspect,'inspect')|dict(results=json.dumps({k:'pass' for k in ids}),decision='release',verified='1')
check(inspector.request(inspect,p|dict(results=json.dumps({k:'fail' for k in ids})))[0]==400,'Failed inspection cannot release merged room')
check(inspector.request(inspect,p)[0]==302 and inspector.request(inspect,p)[0]==302,'Independent release and durable replay remain available')
legacy=staff.request('/housekeeping.php?id='+str(id));check(legacy[0]==200 and 'Check-In &amp; Occupancy' in legacy[2],'Existing housekeeping links remain compatible')
m=json.loads((ROOT/'.local-setup/housekeeping-merge-fixture.json').read_text(encoding='utf-8'));reader=Browser();reader.login(m['email'],'Synthetic Visibility Passphrase 123!')
body=reader.request('/occupancy.php?booking_id='+str(m['booking']))[2]
check('from_stay='+str(m['booking']) in html.unescape(body) and 'housekeeping.php?room_id=' not in body,'Old guest account contains only its explicitly linked cleaning record')
record=root+'&id='+str(m['attempt'])+'&from_stay='+str(m['booking']);code,_,body=reader.request(record)
check(code==200 and 'Current Attempt</a>' not in body and not re.search(r'name="action" value="(?:start|progress|inspect|restart)"',body),'Historical guest cleaning view has no room-latest shortcut or write form')
check(reader.request(record,dict(action='restart',csrf=p['csrf'],operation_key=secrets.token_hex(16)))[0]==403,'Historical cleaning view denies POST actions')
check(reader.request(root+'&id='+str(m['new_attempt'])+'&from_stay='+str(m['booking']))[0]==403,'Forged stay-to-attempt association denied')
check(reader.request(record+'&form=clean')[0]==403,'Historical record cannot open cleaning form')
rooms=Browser();rooms.login(m['rooms_email'],'Synthetic Rooms Only Passphrase 123!')
check(rooms.request(root)[0]==200,'Room-only staff retain access to integrated housekeeping')
check(rooms.request('/occupancy.php')[0]==403,'Room-only access does not grant guest management')
check(rooms.request(root+'&page=owner')[0]==403,'Room-only staff cannot open owner review')
body=rooms.request(root)[2];check('title="Check-In &amp; Occupancy"' in body and 'title="Housekeeping"' not in body,'Room-only navigation uses the consolidated menu')
print('TOTAL '+str(count)+' merged housekeeping HTTP assertions passed')
