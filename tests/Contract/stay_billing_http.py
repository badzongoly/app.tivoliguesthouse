"""Paid check-in and owner authority against the marked synthetic HTTP server only."""
import csv, datetime, html, http.cookiejar, io, json, pathlib, re, secrets, urllib.error, urllib.parse, urllib.request
ROOT=pathlib.Path(__file__).resolve().parents[2]
BASE='http://127.0.0.1:8099'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None
class Browser:
    def __init__(self):
        self.opener=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(self,path,data=None):
        try: response=self.opener.open(urllib.request.Request(BASE+path,data=urllib.parse.urlencode(data).encode() if data is not None else None),timeout=15)
        except urllib.error.HTTPError as error: response=error
        assert response.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified server'
        body=response.read().decode('utf-8')
        assert not re.search(r'Fatal error|Parse error|Warning:|SQLSTATE\[',body),'Application error'
        return response.code,response.headers,body
    def fields(self,path):
        body=self.request(path)[2]
        return {k:html.unescape(v) for k,v in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',body)}
    def login(self,email,password):
        body=self.request('/auth/login.php')[2]
        csrf=re.search(r'name="csrf" value="([^"]+)"',body)[1]
        assert self.request('/auth/login.php',dict(csrf=csrf,email=email,password=password))[0]==302
count=0
def check(value,label):
    global count
    assert value,label
    count+=1
    print('PASS '+label,flush=True)
fixture=json.loads((ROOT/'.local-setup/stays-http-fixture.json').read_text(encoding='utf-8'))
finance=json.loads((ROOT/'.local-setup/finance-fixture.json').read_text(encoding='utf-8'))
owner=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
clerk=Browser();clerk.login(fixture['clerk_email'],fixture['clerk_password'])
other=Browser()
# The existing finance fixture reviewer is an administrator with its own synthetic password.
other_email_code="putenv('TIVOLI_TEST_MODE=1');require 'bootstrap/security.php';echo access_service()->repo->run('SELECT email FROM users WHERE id=?',["+str(finance['reviewer'])+"])->fetchColumn();"
import subprocess
email=subprocess.run(['C:/xampp/php/php.exe','-r',other_email_code],cwd=ROOT,capture_output=True,text=True,check=True).stdout.strip()
assert email.endswith('@example.invalid')
other.login(email,'Synthetic Finance Passphrase 123!')
# Each billing run owns its resources; previous suites may leave their room dirty or occupied.
setup="""putenv('TIVOLI_TEST_MODE=1');require 'bootstrap/stays.php';$r=access_service()->repo;
if((int)$r->run('SELECT @@port')->fetchColumn()!==3307||$r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'||$r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1')exit(2);
$r->run("INSERT INTO rooms(name,type,rate,status) VALUES(?,'Standard','300.00','vacant')",['Synthetic paid billing '.bin2hex(random_bytes(6))]);$room=(int)$r->db->lastInsertId();
$r->run("INSERT INTO stay_room_settings(room_id,short_rate,short_enabled,ready_state) VALUES(?,'150.00',1,'ready')",[$room]);
$r->run("INSERT INTO extras(name,price,stock_qty,active) VALUES(?,'10.00','10.00',1)",['Synthetic paid billing extra '.bin2hex(random_bytes(6))]);
echo json_encode(['room_id'=>$room,'extra_id'=>(int)$r->db->lastInsertId()],JSON_THROW_ON_ERROR);"""
fixture.update(json.loads(subprocess.run(['C:/xampp/php/php.exe','-r',setup],cwd=ROOT,capture_output=True,text=True,encoding='utf-8',check=True).stdout))
path='/occupancy.php?form=checkin&room_id='+str(fixture['room_id'])
body=clerk.request(path)[2]
check('Room Payment Received' in body and 'Confirm Check-In &amp; Record Payment' in body,'arrival form exposes combined receipt action')
fields=clerk.fields(path)
payload=fields|dict(action='checkin',room_id=fixture['room_id'],guest_name='Synthetic paid HTTP '+secrets.token_hex(3),phone='0000000000',gender='Female',nationality='Ghana',stay_type='short',payment_type='momo',amount='149.99',expected_rate='150.00',attest='1',reference='PAID-HTTP-'+secrets.token_hex(6))
check(clerk.request(path,payload)[0]==400,'underpaid arrival rejected through endpoint')
payload['amount']='150.00'
status,headers,_=clerk.request(path,payload)
check(status==302,'paid arrival creates stay and receipt')
booking=int(re.search(r'booking_id=(\d+)',headers['Location'])[1])
detail='/occupancy.php?booking_id='+str(booking)
receipts=clerk.request(detail+'&tab=payments')[2]
check(payload['reference'] in receipts and 'Synthetic occupancy cashier' in receipts,'receipt is linked to authenticated cashier')
check(clerk.request(path,payload)[0]==302,'arrival HTTP retry safe')
extra=detail+'&form=extra'
check(clerk.request(extra,clerk.fields(extra)|dict(action='extra',booking_id=booking,extra_id=fixture['extra_id'],qty='1',settlement='room',method='cash'))[0]==302,'extra creates residual guest debt')
checkout=detail+'&form=checkout'
check('Authorize Departure With Debt' not in clerk.request(checkout)[2] and '>Confirm Departure<' not in clerk.request(checkout)[2],'clerk cannot authorize unpaid departure')
check('Authorize Departure With Debt' not in other.request(checkout)[2],'another administrator has no owner approval control')
owner_fields=owner.fields(checkout)
approval=owner_fields|dict(action='allow_checkout',booking_id=booking,expected_balance='1000',reason='Synthetic owner-approved outstanding')
check(other.request(checkout,other.fields(checkout)|approval|dict(csrf=other.fields(checkout)['csrf'],operation_key=secrets.token_hex(16)))[0]==403,'another administrator cannot forge owner authorization')
check(owner.request(checkout,approval|dict(reason=''))[0]==400,'owner reason required through endpoint')
check(owner.request(checkout,approval)[0]==302,'owner approval succeeds')
check('>Confirm Departure<' in clerk.request(checkout)[2],'owner approval enables clerk departure')
out=clerk.fields(checkout)|dict(action='checkout',booking_id=booking,attest='1',reason='')
status,headers,_=clerk.request(checkout,out)
check(status==302 and 'confirm=' in headers.get('Location',''),'unpaid departure requires confirmation')
confirm=headers['Location'].replace(BASE,'').split('#')[0]
check(clerk.request(confirm,clerk.fields(confirm))[0]==302,'clerk completes authorized unpaid departure')
statement=clerk.request(detail+'&tab=statement')[2]
check('Final Guest Statement' in statement and 'Balance at Checkout' in statement and '₵10.00' in statement,'final statement retains unpaid balance')
today=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d')
status,_,export=clerk.request('/occupancy.php?export=csv&start='+today+'&end='+today)
rows=list(csv.DictReader(io.StringIO(export)))
row=next(row for row in rows if row['Guest']==payload['guest_name'])
check(row['Room Total']=='150.00' and row['Extras']=='10.00' and row['Paid']=='150.00' and row['Balance / evidence']=='10.00','CSV matches exact charges, receipts and unpaid statement')
print('TOTAL',count,'paid arrival HTTP assertions passed.')
