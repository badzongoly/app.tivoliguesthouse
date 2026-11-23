"""One continuous, guarded synthetic room turnover through actual endpoints."""
import html,http.cookiejar,json,pathlib,re,secrets,subprocess,urllib.request,urllib.error,urllib.parse
ROOT=pathlib.Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:8099';PHP='C:/xampp/php/php.exe';FIXTURE=ROOT/'tests/Integration/turnover_fixture.php'
class NR(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*a,**k):return None
class Browser:
 def __init__(self):self.op=urllib.request.build_opener(NR(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,path,data=None):
  try:r=self.op.open(urllib.request.Request(BASE+path,data=urllib.parse.urlencode(data).encode() if data is not None else None),timeout=45)
  except urllib.error.HTTPError as e:r=e
  assert r.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified HTTP target'
  text=r.read().decode('utf-8');assert not re.search(r'Fatal error|Parse error|Warning:|SQLSTATE\[',text),'Application error'
  return r.code,r.headers,text
 def fields(self,path,action):
  text=self.request(path)[2];forms=re.findall(r'<form\b[^>]*>(.*?)</form>',text,re.S);form=next(x for x in forms if 'name="action" value="'+action+'"' in x)
  return {k:html.unescape(v) for k,v in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',form)}
 def login(self,email):
  text=self.request('/auth/login.php')[2];csrf=html.unescape(re.search(r'name="csrf" value="([^"]+)"',text)[1]);assert self.request('/auth/login.php',dict(csrf=csrf,email=email,password='Synthetic Turnover Passphrase 123!'))[0]==302
def php(mode):return subprocess.run([PHP,str(FIXTURE),mode],cwd=ROOT,capture_output=True,text=True,check=True).stdout
def probe():return json.loads(php('probe'))
count=0;results=[]
def check(ok,label):
 global count
 assert ok,label
 count+=1;results.append(dict(scenario=label,result='PASS'));print('PASS '+label,flush=True)
def relative(url):
 p=urllib.parse.urlsplit(url);return p.path+('?' +p.query if p.query else '')
def redirected(result,label):
 check(result[0]==302,label);return relative(result[1]['Location'])
anon=Browser();check(anon.request('/occupancy.php')[0]==302,'Anonymous guest operations require login')
print(php('seed').strip(),flush=True);f=json.loads((ROOT/'.local-setup/turnover-fixture.json').read_text(encoding='utf-8'))
staff=Browser();inspector=Browser();outsider=Browser()
for b,k in [(staff,'staff'),(inspector,'inspector'),(outsider,'outsider')]:b.login(f['users'][k]['email'])
arrival='/occupancy.php?form=checkin&room_id='+str(f['room']);p=staff.fields(arrival,'checkin')|dict(room_id=f['room'],guest_name='Synthetic turnover first '+f['tag'],phone='0000000000',gender='Female',nationality='Ghana',stay_type='short',amount='100.00',expected_rate='100.00',payment_type='cash',identity_verified='1',payment_attested='1',room_checked='1')
check(staff.request(arrival,p|dict(amount='99.99'))[0]==400,'Underpaid check-in rejected')
target=redirected(staff.request(arrival,p),'First paid guest checks in');first=int(re.search(r'booking_id=(\d+)',target)[1]);detail='/occupancy.php?booking_id='+str(first)
check(staff.request(arrival,p)[0]==302 and probe()['payments'][str(first)]['count']==1,'Check-in retry creates no second receipt')
next_payload=p|dict(operation_key=secrets.token_hex(16),guest_name='Synthetic turnover next '+f['tag'])
check(staff.request(arrival,next_payload)[0]==400,'Occupied room cannot accept another guest')
root='/occupancy.php?section=housekeeping';room=root+'&room_id='+str(f['room'])
check(outsider.request(room)[0]==403,'Unauthorized housekeeping account denied')
csrf=p['csrf'];check(staff.request(room,dict(csrf=csrf,operation_key=secrets.token_hex(16),action='start',room_id=f['room'],room_token='stale'))[0]==400,'Occupied room cannot start cleaning')
extra=detail+'&form=extra';ep=staff.fields(extra,'extra')|dict(extra_id=f['extra'],qty='1',settlement='room',method='cash');redirected(staff.request(extra,ep),'Room extra creates an outstanding balance')
checkout=detail+'&form=checkout';cp=staff.fields(checkout,'checkout')|dict(extras_checked='1',keys_returned='1')
check(staff.request(checkout,cp)[0]==400 and probe()['departures']==0,'Unpaid guest cannot check out without authority')
clear=detail+'&form=clear_balance';pay=staff.fields(clear,'clear_balance')|dict(method='cash');confirm=redirected(staff.request(clear,pay),'Full payment opens confirmation')
body=staff.request(confirm)[2];check(body.count('data-stay-confirmation')==1,'Full payment has one server confirmation modal')
staff.request(clear);check(probe()['payments'][str(first)]['count']==1,'Cancelling full-payment preview records no receipt')
confirmed=staff.fields(confirm,'confirm_action');check(staff.request(confirm,confirmed|dict(csrf='invalid'))[0]==403,'Full payment rejects forged CSRF')
redirected(staff.request(confirm,confirmed),'Full payment confirmed once');check(staff.request(confirm,confirmed)[0]==400 and probe()['payments'][str(first)]['count']==2,'Consumed payment confirmation cannot duplicate receipt')
lookup='/occupancy.php?payment_status='+confirmed['operation_key']
saved=json.loads(staff.request(lookup)[2])
check(saved['status']=='recorded' and saved['booking_id']==first and saved['receipt']>0,'Committed receipt can be found after losing the original payment response')
check(json.loads(inspector.request(lookup)[2])=={'status':'unconfirmed'},'Another cashier cannot read this receipt outcome')
check(outsider.request(lookup)[0]==403,'Receipt lookup requires booking authority')
check(anon.request(lookup)[0]==302,'Receipt lookup requires authentication')
check(staff.request('/occupancy.php?payment_status=invalid')[0]==400,'Receipt lookup rejects an invalid operation key')
check(json.loads(staff.request(lookup)[2])==saved and probe()['payments'][str(first)]['count']==2,'Repeated status checks preserve the original receipt and count')
cp=staff.fields(checkout,'checkout')|dict(extras_checked='1',keys_returned='1');confirm=redirected(staff.request(checkout,cp),'Departure opens confirmation')
check(staff.request(confirm)[2].count('data-stay-confirmation')==1,'Departure has one server confirmation modal')
staff.request(checkout);check(probe()['departures']==0 and probe()['state']['status']=='occupied','Cancelling departure leaves guest and room occupied')
confirmed=staff.fields(confirm,'confirm_action');foreign=inspector.fields(extra,'extra')['csrf'];check(inspector.request(confirm,confirmed|dict(csrf=foreign))[0]==400 and probe()['departures']==0,'Another account cannot submit someone else’s departure confirmation')
redirected(staff.request(confirm,confirmed),'Confirmed checkout records actual departure');check(staff.request(confirm,confirmed)[0]==400 and probe()['departures']==1,'Repeated departure confirmation creates no second departure')
check(probe()['state']=={'status':'dirty','ready_state':'cleaning'},'Checkout marks room dirty and needing cleaning')
check(staff.request(arrival,next_payload)[0]==400,'Dirty room cannot accept next check-in')
payment=detail+'&form=payment';pp=dict(csrf=csrf,action='payment',booking_id=first,operation_key=secrets.token_hex(16),amount='1.00',method='cash');check(staff.request(payment,pp)[0]==400 and probe()['payments'][str(first)]['count']==2,'Departed guest cannot receive ordinary new payment')
sp=staff.fields(room,'start');check(staff.request(room,sp|dict(csrf='invalid'))[0]==403,'Cleaning start rejects invalid CSRF')
target=redirected(staff.request(room,sp),'Assigned cleaner starts room preparation');attempt=int(re.search(r'[?&]id=(\d+)',target)[1]);case=root+'&id='+str(attempt);clean=case+'&form=clean'
check(staff.request(room,sp)[0]==302 and probe()['attempt']['id']==attempt,'Cleaning start retry returns original assignment')
check(probe()['attempt']['source_booking_id']==first,'Cleaning captures the original departed stay')
check(inspector.request(clean)[0]==403,'Different staff cannot edit assigned cleaning')
body=staff.request(clean)[2];items=re.findall(r'name="done\[\]" value="([^"]+)"',body);steps=len(re.findall(r'data-clean-step=',body));check(bool(items) and 'data-select-clean="all"' in body,'Captured checklist and Select All are available')
progress=staff.fields(clean,'progress');check(staff.request(clean,progress|dict(done='[]',direction='next'))[0]==400 and probe()['attempt']['step']==0,'Incomplete checklist cannot advance')
check(inspector.request(clean,progress|dict(csrf=foreign,done=json.dumps(items),direction='next'))[0]==403,'Direct POST cannot bypass assigned cleaner')
for step in range(steps):
 progress=staff.fields(clean,'progress')|dict(done=json.dumps(items),direction='complete' if step==steps-1 else 'next',verified='1')
 redirected(staff.request(clean,progress),'Captured cleaning step '+str(step+1)+' completes')
 if step==0:
  check(staff.request(clean,progress|dict(operation_key=secrets.token_hex(16)))[0]==400,'Stale cleaning version rejected')
  check(staff.request(clean,progress)[0]==302,'Same cleaning operation replays safely')
  check(staff.request(clean,progress|dict(done='[]'))[0]==400,'Reused operation key with changed details rejected')
check(staff.request(clean,progress)[0]==302 and probe()['attempt']['completed']==1,'Cleaning completion retry creates no second completion')
check(probe()['state']=={'status':'dirty','ready_state':'awaiting_inspection'},'Completed cleaning does not release room')
check(staff.request(arrival,next_payload)[0]==400,'Awaiting-inspection room cannot accept next check-in')
inspect=case+'&form=inspect';check(staff.request(inspect)[0]==403,'Cleaner cannot self-inspect without owner authority')
body=inspector.request(inspect)[2];keys=re.findall(r'name="result_([^"]+)" value="pass"',body);ip=inspector.fields(inspect,'inspect')|dict(results=json.dumps({k:'pass' for k in keys}),decision='release',verified='1')
check(staff.request(inspect,ip|dict(csrf=csrf))[0]==403,'Direct self-inspection POST is denied')
check(inspector.request(inspect,ip|dict(results=json.dumps({k:'fail' for k in keys})))[0]==400,'Failed inspection cannot release room')
check(inspector.request(inspect,ip|dict(verified='0'))[0]==400,'Inspection requires personal verification attestation')
redirected(inspector.request(inspect,ip),'Independent inspector releases the room');check(inspector.request(inspect,ip)[0]==302 and probe()['attempt']['inspections']==1,'Release retry creates no second inspection')
check(probe()['state']=={'status':'vacant','ready_state':'ready'},'Passing inspection makes room Ready / Available')
board=staff.request('/occupancy.php')[2];card=next(x for x in re.findall(r'<article\b[^>]*data-room-id="'+str(f['room'])+r'".*?</article>',board,re.S));check('Check In</a>' in card,'Room Board offers next Check In only after release')
target=redirected(staff.request(arrival,next_payload),'Next paid guest checks into the same room');second=int(re.search(r'booking_id=(\d+)',target)[1]);check(second!=first,'Next guest receives a separate stay identity')
check(staff.request(arrival,next_payload)[0]==302,'Next check-in retry reuses the original booking')
check(inspector.request(inspect,ip)[0]==302 and probe()['state']['status']=='occupied','Old release replay cannot vacate next guest’s room')
check(inspector.request(inspect,ip|dict(operation_key=secrets.token_hex(16)))[0] in (400,403) and probe()['state']['status']=='occupied','Fresh stale release cannot alter occupied room')
old=staff.request(detail)[2];check('from_stay='+str(first) in html.unescape(old) and 'housekeeping.php?room_id=' not in old,'Original stay still links its own cleaning record after reuse')
historical=case+'&from_stay='+str(first);check(staff.request(historical,dict(csrf=csrf,action='restart',operation_key=secrets.token_hex(16)))[0]==403,'Historical cleaning link cannot perform current room actions')
check(probe()['payments'][str(first)]['paid']=='110.00' and probe()['payments'][str(second)]['paid']=='100.00','Original and next receipts remain separately reconciled')
print(php('verify'),end='',flush=True)
(ROOT/'.local-setup/turnover-scenarios.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
print('TOTAL '+str(count)+' continuous HTTP turnover assertions passed',flush=True)
