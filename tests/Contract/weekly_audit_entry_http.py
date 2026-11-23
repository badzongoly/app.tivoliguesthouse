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
ROOT=pathlib.Path(__file__).resolve().parents[2]
PROBE=ROOT/'tests/Integration/weekly_audit_state_probe.php'
f=json.loads((ROOT/'.local-setup/weekly-audit-http-fixture.json').read_text(encoding='utf-8'));ui=json.loads((ROOT/'.local-setup/weekly-ui-fixture.json').read_text(encoding='utf-8'))
n=0
def check(ok,label):
 global n
 assert ok,label
 n+=1;print('PASS '+label,flush=True)
def state(id):
 return json.loads(subprocess.run(['C:/xampp/php/php.exe',str(PROBE),str(id),str(f['users']['other']['id'])],cwd=ROOT,check=True,capture_output=True,text=True).stdout)
def target(response,label):
 check(response[0]==302,label);return int(re.search(r'[?&]id=(\d+)',response[1]['Location'])[1])
owner=Browser();auditor=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!');auditor.login(f['users']['other']['email'],'Synthetic Audit Passphrase 123!');root='/weekly-audits.php'
check(auditor.request(root+'?page=room&form=new')[0]==403,'Staff cannot record an audit before named duty is granted')
duty=owner.fields(root+'?page=duty','duty')|dict(user_id=f['users']['other']['id'],enabled='1',reason='Synthetic end-to-end audit review')
check(owner.request(root+'?page=duty',duty|dict(csrf='invalid'))[0]==403,'Audit duty rejects forged CSRF')
check(owner.request(root+'?page=duty',duty)[0]==302 and owner.request(root+'?page=duty',duty)[0]==302,'Owner grants audit duty with duplicate-safe HTTP retry')
check(auditor.request(root+'?page=room&form=new')[0]==200,'Delegated auditor can open original room-audit form')
new=root+'?page=room&form=new';p=auditor.fields(new,'room_audit')|dict(room_id=ui['room'],result='pass',reason='',owner='',followup_id='',followup_version='')
response=auditor.request(new,p);check(response[0]==302 and 'page=room' in response[1]['Location'] and not re.search(r'[?&]id=',response[1]['Location']),'Passing audit records the source without creating a follow-up')
p=auditor.fields(new,'room_audit')|dict(room_id=ui['room'],result='corrective',reason='Synthetic HTTP room follow-up',owner='Original free-text note',followup_id='',followup_version='')
id=target(auditor.request(new,p),'Needs Attention creates a follow-up through actual room-audit endpoint');detail=root+'?id='+str(id)
check(state(id)['status']=='unassigned','Original free-text note is not mistaken for a named assignment')
check('No follow' not in owner.request(detail)[2] and 'Assign Follow-Up' in owner.request(detail)[2],'Owner can reach named assignment from the new source')
assign=owner.fields(detail,'assign')|dict(assignee=f['users']['other']['id'],due_date=ui['due'])
check(owner.request(detail,assign)[0]==302,'Owner assigns a responsible account and deadline')
st=state(id);check(st['overdue_future'] and st['open_next_week'],'Unresolved assigned work becomes overdue and carries across weeks')
check(st['reminder']['actionable'] and st['reminder']['staff_receives'] and st['reminder']['path'].startswith('weekly-audits.php?id='+str(id)),'Assigned in-app reminder opens the original actionable task')
done=auditor.fields(detail,'done');check(auditor.request(detail,done)[0]==302,'Assignee marks work done without requiring an optional attachment')
st=state(id);check(st['status']=='review' and st['reminder']['owner_receives'] and not st['reminder']['actionable'],'Completion awaits owner verification and shifts action to owner')
version=owner.fields(detail,'close')['version'];repeat=auditor.fields(new,'room_audit')|dict(room_id=ui['room'],result='corrective',reason='Synthetic same finding repeated',owner='',followup_id=str(id),followup_version=version)
check(target(auditor.request(new,repeat),'Explicit repeated finding links to the existing action')==id,'Repeated finding never creates a second task')
st=state(id);check(st['status']=='assigned' and st['sources']==2 and st['history']['wa_done']==1,'Repeated finding returns work for attention while retaining earlier completion')
done=auditor.fields(detail,'done');check(auditor.request(detail,done)[0]==302,'Assignee completes work including the newly linked finding')
returned=owner.fields(detail,'return');check(owner.request(detail,returned|dict(comment=''))[0]==400,'Returning work requires an explanation')
returned=owner.fields(detail,'return')|dict(comment='Synthetic verify curtain rail again');check(owner.request(detail,returned)[0]==302,'Owner returns completed work for rework')
st=state(id);check(st['status']=='assigned' and st['history']['wa_done']==2,'Rework preserves previous completion history')
check(auditor.request(detail,done|dict(operation_key=secrets.token_hex(16)))[0]==400,'Stale pre-return completion cannot overwrite owner decision')
check(auditor.request(detail,auditor.fields(detail,'done'))[0]==302,'Assignee submits the corrected work')
closed=owner.fields(detail,'close')|dict(verified='1');check(owner.request(detail,closed)[0]==302 and owner.request(detail,closed)[0]==302,'Owner verification closes once and safely replays')
st=state(id);check(st['status']=='closed' and not st['reminder']['staff_receives'] and st['reminder']['resolved'],'Closed follow-up stops requiring an active reminder')
new_area=root+'?page=area&form=new';area=auditor.fields(new_area,'area_audit')|dict(area_id=ui['area'],result='unsafe',reason='Synthetic unsafe common area',owner='',followup_id='',followup_version='')
area_id=target(auditor.request(new_area,area),'Unsafe area audit creates its linked follow-up');area_detail=root+'?id='+str(area_id)
assign=owner.fields(area_detail,'assign')|dict(assignee=f['users']['other']['id'],due_date=ui['due']);check(owner.request(area_detail,assign)[0]==302,'Unsafe area work receives named responsibility')
check(auditor.request(area_detail,auditor.fields(area_detail,'done'))[0]==302,'Assignee submits unsafe-area completion for verification')
closed=owner.fields(area_detail,'close')|dict(verified='1');check(owner.request(area_detail,closed)[0]==400,'Unsafe area cannot close without explicit safe-or-blocked attestation')
closed=owner.fields(area_detail,'close')|dict(verified='1',safe='1');check(owner.request(area_detail,closed)[0]==302,'Owner can close area finding after explicit safety review')
print('TOTAL '+str(n)+' weekly audit entry/reminder HTTP assertions passed')
