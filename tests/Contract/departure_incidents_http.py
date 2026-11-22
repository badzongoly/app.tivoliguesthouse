"""Departure incident workflow against only the marked synthetic HTTP service."""
import csv,datetime,html,http.cookiejar,io,json,pathlib,re,secrets,urllib.error,urllib.parse,urllib.request
BASE='http://127.0.0.1:8099';ROOT=pathlib.Path(__file__).resolve().parents[2]
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
class Browser:
    def __init__(self):self.o=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(self,path,data=None,file=None):
        headers={};body=urllib.parse.urlencode(data).encode() if data is not None else None
        if file:
            boundary='IncidentBoundary'+secrets.token_hex(12)
            parts=[]
            for k,v in data.items():parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"\r\n\r\n'+str(v)+'\r\n').encode())
            parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="evidence"; filename="incident.pdf"\r\nContent-Type: application/pdf\r\n\r\n').encode()+file+b'\r\n')
            parts.append(('--'+boundary+'--\r\n').encode());body=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
        try:r=self.o.open(urllib.request.Request(BASE+path,data=body,headers=headers),timeout=20)
        except urllib.error.HTTPError as e:r=e
        assert r.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified service'
        text=r.read().decode('utf-8')
        assert not re.search(r'Fatal error|Warning:|Parse error|SQLSTATE\[',text),'Application error'
        return r.code,r.headers,text
    def fields(self,path,action=None):
        body=self.request(path)[2]
        if action:
            forms=re.findall(r'<form\b[^>]*>(.*?)</form>',body,re.S)
            body=next(f for f in forms if 'name="action" value="'+action+'"' in f)
        return {k:html.unescape(v) for k,v in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',body)}
    def login(self,email,password):
        assert self.request('/auth/login.php',self.fields('/auth/login.php')|dict(email=email,password=password))[0]==302
count=0
def check(ok,label):
    global count
    assert ok,label
    count+=1;print('PASS '+label,flush=True)
fixture=json.loads((ROOT/'.local-setup/stays-http-fixture.json').read_text(encoding='utf-8'))
finance=json.loads((ROOT/'.local-setup/finance-fixture.json').read_text(encoding='utf-8'))
import subprocess
setup="""putenv('TIVOLI_TEST_MODE=1');require 'bootstrap/security.php';$r=access_service()->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','300.00','vacant')",['Incident HTTP '.bin2hex(random_bytes(8))]);$room=(int)$r->db->lastInsertId();
$r->run("INSERT INTO stay_room_settings(room_id,short_rate,short_enabled,ready_state) VALUES(?,'150.00',1,'ready')",[$room]);
$r->run("INSERT INTO extras(name,price,stock_qty,active) VALUES(?,'10.00','10.00',1)",['Incident HTTP extra '.bin2hex(random_bytes(8))]);$extra=(int)$r->db->lastInsertId();
echo json_encode(['room_id'=>$room,'extra_id'=>$extra],JSON_THROW_ON_ERROR);"""
created=json.loads(subprocess.run(['C:/xampp/php/php.exe','-r',setup],cwd=ROOT,capture_output=True,text=True,check=True).stdout)
fixture.update(created)

owner=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
clerk=Browser();clerk.login(fixture['clerk_email'],fixture['clerk_password'])
room=fixture['room_id']
path='/occupancy.php?form=checkin&room_id='+str(room)
arrival=clerk.fields(path,'checkin')|dict(room_id=room,guest_name='Synthetic incident HTTP '+secrets.token_hex(4),phone='0000000000',gender='Female',nationality='Ghana',stay_type='short',expected_rate='150.00',amount='150.00',payment_type='cash',identity_verified='1',payment_attested='1',room_checked='1')
status,h,_=clerk.request(path,arrival);check(status==302,'synthetic paid arrival before incident')
booking=int(re.search(r'booking_id=(\d+)',h['Location'])[1]);detail='/occupancy.php?booking_id='+str(booking)
extra=detail+'&form=extra'
check(clerk.request(extra,clerk.fields(extra,'extra')|dict(extra_id=fixture['extra_id'],qty='1',settlement='room',method='cash'))[0]==302,'guest extra establishes receivable')
report=detail+'&form=report_departure';body=clerk.request(report)[2]
check('Discovered At' in body and 'Actual Departure At' in body,'staff report exposes known and unknown times')
discovered=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%S')
data=clerk.fields(report,'report_departure')|dict(discovered_at=discovered,actual_departed_at='',reason='Guest absent; room vacancy and keys not yet verified')
check(clerk.request(report,{k:v for k,v in data.items() if k!='csrf'})[0]==403,'incident write requires CSRF')
proof=b'%PDF-1.4\nSynthetic departure incident evidence'
check(clerk.request(report,data,file=proof)[0]==302,'staff can report unknown departure with optional private evidence')
body=clerk.request(detail)[2]
check('Departure Reported' in body and 'Provisional Stay Bill' in body and 'Room Blocked' in body,'reported departure is provisional and unsecured room blocked')
case_link=re.search(r'finance-evidence.php\?kind=incident&amp;id=(\d+)',owner.request(detail)[2])
assert case_link
download='/finance-evidence.php?kind=incident&id='+case_link[1]
check(clerk.request(download)[0]==403,'clerk cannot download private incident evidence')
check(owner.request(download)[2].encode()==proof,'owner downloads exact private evidence')
check(arrival['guest_name'] in clerk.request('/occupancy.php?section=departures')[2],'open incident register retains guest')
today=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d')
export=clerk.request('/occupancy.php?export=csv&start='+today+'&end='+today)[2]
pending_row=next(row for row in csv.DictReader(io.StringIO(export)) if row['Guest']==arrival['guest_name'])
check(pending_row['Review Status']=='Provisional - Awaiting Owner','CSV marks unfinalised charges as provisional')
check('Review Departure Incidents' in owner.request('/owner_reports.php')[2],'owner home surfaces departure follow-up alert')
review=detail+'&form=review_departure'
forged=clerk.fields(detail)|dict(action='preview_departure',operation_key=secrets.token_hex(16),booking_id=booking,actual_departed_at=discovered)
check(clerk.request(review,forged)[0]==403,'clerk cannot preview final owner bill')
secure=detail+'&form=secure_departure_room'
check(clerk.request(secure,clerk.fields(secure,'secure_departure_room')|dict(vacant='1',secured='1',reason='Room checked vacant and access secured'))[0]==302,'staff security verification permits cleaning')
house='/housekeeping.php?room_id='+str(room)
code,h,_=owner.request(house,owner.fields(house,'start'))
assert code==302
attempt=int(re.search(r'id=(\d+)',h['Location'])[1]);clean='/housekeeping.php?id='+str(attempt)+'&form=clean'
body=owner.request(clean)[2];items=re.findall(r'name="done\[\]" value="([^"]+)"',body);steps=len(re.findall('data-clean-step=',body))
for step in range(steps):
    fields=owner.fields(clean,'progress')|dict(direction='complete' if step==steps-1 else 'next',done=json.dumps(items),verified='1')
    assert owner.request(clean,fields)[0]==302
check('Awaiting Inspection' in owner.request('/housekeeping.php?id='+str(attempt))[2],'normal cleaning works while debt case remains open')
inspect='/housekeeping.php?id='+str(attempt)+'&form=inspect';body=clerk.request(inspect)[2];items=re.findall(r'name="result_([^"]+)" value="pass"',body)
check(clerk.request(inspect,clerk.fields(inspect,'inspect')|dict(results=json.dumps(dict.fromkeys(items,'pass')),decision='release',verified='1'))[0]==302,'independent inspection works while debt case remains open')
new_arrival=clerk.fields(path,'checkin')|dict(arrival,operation_key=secrets.token_hex(16),guest_name='Synthetic new room occupant')
status,h,_=clerk.request(path,new_arrival)
check(status==302,'room can be reused independently of outstanding debt')
new_booking=int(re.search(r'booking_id=(\d+)',h['Location'])[1])
preview=owner.fields(review,'preview_departure')|dict(actual_departed_at=discovered)
status,_,body=owner.request(review,preview);check(status==200 and 'Confirm Final Departure Bill' in body,'owner previews final charges before confirming')
final_form=next(f for f in re.findall(r'<form\b[^>]*>(.*?)</form>',body,re.S) if 'name="decision" value="confirm"' in f)
final={k:html.unescape(v) for k,v in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',final_form)}
due=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d')
final|=dict(reason='Owner checked actual departure evidence',owner_id=finance['clerk'],due=due)
check(owner.request(review,final)[0]==302,'owner finalizes original case after room reuse')
check('Synthetic new room occupant' in clerk.request('/occupancy.php')[2],'owner finalization preserves new room occupant')
collect=detail+'&form=collect_outstanding'
check('Record Outstanding Collection' not in clerk.request(collect)[2],'clerk has no departed collection action')
payment=owner.fields(collect,'collect_outstanding')|dict(amount='4.00',method='momo',reference='INCIDENT-HTTP-'+secrets.token_hex(6),reason='Partial payment received from departed guest')
check(owner.request(collect,payment)[0]==302,'owner can record new partial collection')
check(owner.request(collect,payment)[0]==302,'same collection retry is idempotent')
statement=clerk.request(detail+'&tab=statement')[2]
check('Final Guest Statement' in statement and '₵6.00' in statement and payment['reference'] in clerk.request(detail+'&tab=payments')[2],'final account retains six cedis due and linked receipt')
status,_,export=clerk.request('/occupancy.php?export=csv&start='+due+'&end='+due)
row=next(r for r in csv.DictReader(io.StringIO(export)) if r['Guest']==arrival['guest_name'])
check(row['Paid']=='154.00' and row['Balance / evidence']=='6.00' and row['Review Status']=='Final','CSV agrees with partial recovered balance')
check('Outstanding Collection' in clerk.request(detail+'&tab=payments')[2],'newly received collections are distinguished from late discoveries')
check('Open Departure Incidents' in owner.request('/finance.php?page=weekly')[2],'weekly review retains visible unresolved cases')
(ROOT/'.local-setup/departure-browser-fixture.json').write_text(json.dumps(dict(booking=booking,new_booking=new_booking,room=room,clerk_email=fixture['clerk_email'],clerk_password=fixture['clerk_password'])),encoding='utf-8')
print('TOTAL',count,'departure incident HTTP assertions passed.')
