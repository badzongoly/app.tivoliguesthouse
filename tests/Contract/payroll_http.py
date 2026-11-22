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
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/payroll_http_seed.php')],cwd=ROOT,check=True,capture_output=True)
fixture=json.loads((ROOT/'.local-setup/payroll-http-fixture.json').read_text(encoding='utf-8'))
owner=Browser();employee=Browser();other=Browser();admin=Browser()
owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
for b,key in [(employee,'employee'),(other,'other'),(admin,'admin')]:b.login(fixture['users'][key]['email'],'Synthetic Payroll Passphrase 123!')
root='/payroll.php'
check(Browser().request(root)[0]==302,'Payroll requires login')
check(owner.request(root)[0]==200,'Owner payroll workspace renders')
check('My Payslips' in employee.request(root)[2],'Employee sees own payslip workspace')
p=owner.fields(root,'start')|dict(staff_id=str(fixture['staff']),period=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m'),not_recorded='1')
check(owner.request(root,p|dict(csrf='bad'))[0]==403,'CSRF denied')
check('Start Monthly Payroll' not in admin.request(root)[2],'Other administrator has no owner controls')
code,headers,body=owner.request(root,p);check(code==302,'Owner starts payroll')
id=int(re.search(r'id=(\d+)',headers['Location'])[1]);detail=root+'?id='+str(id)
check(employee.request(detail)[0]==403 and other.request(detail)[0]==403,'Private draft denied to staff')
p=owner.fields(detail,'prepare');body=owner.request(detail)[2]
check('data-payroll-base="100000"' in body and 'data-payroll-preview' in body,'Exact preview and frozen salary source render')
p|=dict(allowance='100.00',overtime='50.00',deductions='25.00',employer='75.00',adjustment='Reviewed synthetic adjustment',comment='PRIVATE-HTTP-PAYROLL',checked='1')
code,_,invalid=owner.request(detail,p|dict(adjustment=''))
check(code==400 and 'Prepare Payroll' in invalid and 'PRIVATE-HTTP-PAYROLL' in invalid,'Validation preserves current form and entered values')
check(owner.request(detail,p)[0]==302,'Prepare exact manual amounts')
p=owner.fields(detail,'approve')|dict(reason='Owner verified salary and payee',authorized='1')
check(owner.request(detail,p)[0]==302,'Owner approves own prepared payroll')
body=owner.request(detail)[2];check('GHS 1225.00' in body and 'GHS 1125.00' in body,'Cost and net are distinct')
p=owner.fields(detail,'pay')|dict(amount='400.00',reference='PAY-HTTP-'+fixture['tag']+'-1',paid_at=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%S'),verified='1',comment='PRIVATE-HTTP-PAYMENT')
proof=b'%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF'
check(owner.request(detail,p,proof)[0]==302,'Partial transfer records with protected proof')
check(owner.request(detail,p,proof)[0]==302,'Same payment replay is safe')
body=owner.request(detail)[2];check('GHS 725.00' in body,'Remaining employee balance exact')
prooflink=html.unescape(re.search(r'href="([^"]*finance-evidence.php[^"]+)"',body)[1]);proofpath='/finance-evidence.php?'+urllib.parse.urlsplit(prooflink).query
check(owner.request(proofpath)[0]==200 and employee.request(proofpath)[0]==403 and admin.request(proofpath)[0]==403,'Payment proof owner-only')
p=owner.fields(detail,'pay')|dict(amount='725.00',reference='PAY-HTTP-'+fixture['tag']+'-2',paid_at=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%S'),verified='1')
check(owner.request(detail,p)[0]==302,'Final transfer settles remaining balance')
check('Record Completed MoMo Payment' not in owner.request(detail)[2],'Settled payroll cannot record more payment')
check(employee.request(detail)[0]==403,'Unpublished settled statement remains private')
p=owner.fields(detail,'publish')|dict(confirmed='1');check(owner.request(detail,p)[0]==302,'Publish payslip to verified linked account')
code,_,body=employee.request(detail);check(code==200 and 'GHS 1125.00' in body,'Employee reads published own statement')
check('PRIVATE-HTTP' not in body and 'Download Private Payment Evidence' not in body and 'Employer Costs' not in body,'Staff statement excludes internal comments proof and employer cost')
check(other.request(detail)[0]==403 and admin.request(detail)[0]==403,'Other accounts cannot read payslip')
check('Monthly Payslip' in owner.request(detail+'&page=statement')[2],'Owner statement preview renders')
check('Synthetic Payroll Employee' in employee.request(root)[2],'Published statement appears in own list')
check(owner.request('/finance.php?page=payroll')[0]==200,'Legacy payroll register handles new canonical sources')
check(owner.request('/finance.php?form=payroll_request')[0]==302,'Obsolete new payroll form redirects')
legacy=owner.fields('/finance.php?form=pay_cost&id=1&type=expense','pay_cost') if False else owner.fields(root,'start')
check(owner.request('/finance.php',legacy|dict(action='payroll_request'))[0]==409,'Old public payroll creation denied')
check(owner.request(root+'?page=costs')[0]==200,'Staff cost report renders')
check('Payroll Activity' in owner.request(root+'?page=activity')[2],'Private payroll activity renders for owner')
body=owner.request('/finance.php?page=payments')[2]
check('PAY-HTTP-'+fixture['tag']+'-2' in body,'Owner payment log shows actual private payroll reference')
body=admin.request('/finance.php?page=payments')[2]
check('PAY-HTTP-'+fixture['tag']+'-2' not in body and 'Restricted Payroll Reference' in body,'Other salary-authorized admin retains redacted financial payment row')
check('payroll.php' in employee.request(root)[2],'Staff payroll navigation available')
print('TOTAL '+str(count)+' payroll HTTP assertions passed.')
