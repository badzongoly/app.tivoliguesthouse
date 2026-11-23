"""Acceptance only against the marked synthetic test web service."""
import datetime,html,http.cookiejar,json,pathlib,re,secrets,subprocess,urllib.request,urllib.error,urllib.parse
ROOT=pathlib.Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:8099'
class NR(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*a,**kw):return None
class Browser:
 def __init__(self):self.op=urllib.request.build_opener(NR(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,path,data=None,file=None):
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
anon=Browser();check(anon.request('/housekeeping.php')[0]==302,'Housekeeping requires login')
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/housekeeping_http_seed.php')],cwd=ROOT,check=True,capture_output=True)
fixture=json.loads((ROOT/'.local-setup/housekeeping-http-fixture.json').read_text(encoding='utf-8'))
staff=Browser();inspector=Browser();admin=Browser();outsider=Browser();owner=Browser()
for b,key in [(staff,'staff'),(inspector,'inspector'),(admin,'admin'),(outsider,'outsider')]:b.login(fixture['users'][key]['email'],'Synthetic Housekeeping Passphrase 123!')
owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
root='/housekeeping.php';room=root+'?room_id='+str(fixture['room'])
check(outsider.request(root)[0]==403,'Account without housekeeping privileges denied')
check(staff.request(root+'?page=owner')[0]==403 and admin.request(root+'?page=owner')[0]==403,'Only designated owner opens exception queue')
start=staff.fields(room,'start')
check(staff.request(room,start|dict(csrf='invalid'))[0]==403,'Forged housekeeping request denied')
code,headers,_=staff.request(room,start);check(code==302,'Assigned cleaning starts with redirect')
id=int(re.search(r'id=(\d+)',headers['Location'])[1]);detail=root+'?id='+str(id);clean=detail+'&form=clean'
check(inspector.request(clean)[0]==403,'Other staff cannot edit assigned cleaning')
body=staff.request(clean)[2];check('data-select-clean="all"' in body and 'Check All Cleaning Steps' in body,'Cleaning Select All available')
items=re.findall(r'name="done\[\]" value="([^"]+)"',body);steps=len(re.findall(r'data-clean-step=',body));check(len(items)>0,'Captured checklist rendered')
pause=staff.fields(clean,'progress')|dict(done='[]',direction='pause');code,headers,_=staff.request(clean,pause);check(code==302 and 'form=clean' not in headers.get('Location',''),'Save & Return opens room detail')
p=staff.fields(clean,'progress');check(staff.request(clean,p|dict(done='[]',direction='next'))[0]==400,'Missing step items rejected')
check(staff.request(clean,p|dict(done='[["invalid"]]',direction='next'))[0]==400,'Nested checklist selection rejected without warnings')
for step in range(steps):
 p=staff.fields(clean,'progress')|dict(done=json.dumps(items),direction='complete' if step==steps-1 else 'next',verified='1',comment='Synthetic cleaning evidence')
 result=staff.request(clean,p,b'%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF' if step==steps-1 else None)
 check(result[0]==302,'Cleaning step '+str(step+1)+' recorded')
check('Awaiting Inspection' in staff.request(detail)[2],'Cleaning completion remains unavailable')
check(staff.request(detail+'&form=inspect')[0]==403,'Cleaner cannot open self-inspection without grant')
prooflink=html.unescape(re.search(r'href="([^"]*finance-evidence.php[^"]+)"',staff.request(detail)[2])[1]);proofpath='/finance-evidence.php?'+urllib.parse.urlsplit(prooflink).query
check(inspector.request(proofpath)[0]==200 and outsider.request(proofpath)[0]==403,'Room evidence restricted to authorized housekeeping team')
p=staff.fields(detail+'&form=exception','request_exception')|dict(reason='No independent inspector currently present')
check(staff.request(detail+'&form=exception',p)[0]==302,'Cleaner requests named one-attempt exception')
check(staff.request(detail+'&form=inspect')[0]==403,'Request alone does not grant inspection')
check(admin.request(detail+'&form=decision')[0]==403,'Other administrator cannot authorize exception')
p=owner.fields(detail+'&form=decision','decide_exception')|dict(decision='approve',reason='One attempt authorized, follow-up due')
check(owner.request(detail+'&form=decision',p)[0]==302,'Owner grants exact attempt')
inspect=detail+'&form=inspect';body=staff.request(inspect)[2]
check('data-select-inspect="all"' in body,'Inspection Select All available')
ids=re.findall(r'name="result_([^"]+)" value="pass"',body);results={k:'pass' for k in ids};failed=results|{ids[0]:'fail'}
p=staff.fields(inspect,'inspect')|dict(results=json.dumps(failed),decision='release',verified='1')
check(staff.request(inspect,p)[0]==400,'Failed inspection cannot release')
p=staff.fields(inspect,'inspect')|dict(results=json.dumps(failed),decision='rework',verified='1',comment='Room returned, comment optional')
check(staff.request(inspect,p)[0]==302,'Failed inspection returns room for cleaning')
check(staff.request(inspect)[0]==403,'Consumed grant cannot be used again')
queue=owner.request(root+'?page=owner')[2];follow=re.search(r'href="([^"]*id='+str(id)+r'&form=followup[^"]*)"',html.unescape(queue))[1];follow=root+'?'+urllib.parse.urlsplit(follow).query
p=owner.fields(follow,'followup')|dict(reason='Failure and required rework reviewed')
check(owner.request(follow,p)[0]==302,'Owner records follow-up against failed self-inspection')
p=inspector.fields(room,'start');code,headers,_=inspector.request(room,p);check(code==302,'Fresh attempt starts after rework')
newid=int(re.search(r'id=(\d+)',headers['Location'])[1]);new=root+'?id='+str(newid);clean=new+'&form=clean'
body=inspector.request(clean)[2];items=re.findall(r'name="done\[\]" value="([^"]+)"',body);steps=len(re.findall('data-clean-step=',body))
for step in range(steps):
 p=inspector.fields(clean,'progress')|dict(done=json.dumps(items),direction='complete' if step==steps-1 else 'next',verified='1')
 assert inspector.request(clean,p)[0]==302
inspect=new+'&form=inspect';body=staff.request(inspect)[2];ids=re.findall(r'name="result_([^"]+)" value="pass"',body)
p=staff.fields(inspect,'inspect')|dict(results=json.dumps({k:'pass' for k in ids}),decision='release',verified='1')
check(staff.request(inspect,p)[0]==302,'Different staff member releases new attempt')
check(staff.request(inspect,p)[0]==302,'Identical release retry remains idempotent')
check('Returned For Cleaning' in staff.request(detail)[2] and 'Earlier Attempt' in staff.request(new)[2],'Old returned attempt and link remain readable')
check(staff.request('/occupancy.php?form=clean&room_id='+str(fixture['room']))[0]==302,'Old clean GET redirects to Housekeeping')
csrf=p['csrf']
for action in ['clean','inspect']:
 check(staff.request('/occupancy.php',dict(csrf=csrf,operation_key=secrets.token_hex(16),action=action,room_id=fixture['room']))[0]==409,'Old public '+action+' POST denied')
check('Housekeeping History' in staff.request(root+'?page=activity')[2],'Housekeeping history remains reachable')
print('TOTAL '+str(count)+' housekeeping HTTP checks passed.')
