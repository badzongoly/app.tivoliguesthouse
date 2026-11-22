"""Expense approval workflow tests against only the marked synthetic HTTP server."""
import urllib.request, urllib.parse, urllib.error, http.cookiejar, re, secrets, csv, io, subprocess, pathlib
BASE='http://127.0.0.1:8099';ROOT=pathlib.Path(__file__).resolve().parents[2]
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None
class Browser:
    def __init__(self):
        self.opener=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(self,path,data=None):
        request=urllib.request.Request(BASE+path,data=urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None)
        try: response=self.opener.open(request,timeout=20)
        except urllib.error.HTTPError as error: response=error
        body=response.read().decode('utf-8','replace')
        if response.headers.get('X-Tivoli-Test-Mode')!='synthetic': raise RuntimeError('Refusing unverified HTTP server')
        if any(item in body for item in ['Fatal error','Parse error','Warning:','SQLSTATE[']): raise RuntimeError('Unexpected application error')
        return response.code,response.headers,body
    def token(self,path):
        body=self.request(path)[2];match=re.search(r'name="csrf" value="([a-f0-9]{64})"',body)
        if not match: raise RuntimeError('Missing CSRF')
        return match.group(1)
    def login(self,email,password):
        token=self.token('/auth/login.php');return self.request('/auth/login.php',{'csrf':token,'email':email,'password':password})
import json
count=0
def check(ok,label):
 global count
 if not ok:raise RuntimeError('FAIL '+label)
 count+=1;print('PASS '+label,flush=True)
def seed(who,data):
 payload={'kind':'expense','email':who,'input':data}
 return json.loads(subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/purchase_legacy_http_seed.php')],input=json.dumps(payload),text=True,capture_output=True,check=True).stdout)
fixture=json.loads((ROOT/'.local-setup/handover-http-fixture.json').read_text(encoding='utf-8'));tag=secrets.token_hex(6)
admin=Browser();clerk=Browser();other=Browser()
check(admin.login('admin@example.invalid','Synthetic Admin Passphrase 123!')[0]==302,'owner signs in')
clerk.login(fixture['sender']['email'],'Synthetic Handover Passphrase 123!');other.login(fixture['third']['email'],'Synthetic Handover Passphrase 123!')
category='Purchase-legacy '+tag
check(admin.request('/expenses.php',dict(action='add_category',csrf=admin.token('/expenses.php'),cat_name=category))[0]==302,'existing category administration remains')
body=admin.request('/expenses.php')[2];category_id=re.search(r'<option value="([0-9]+)"[^>]*>'+re.escape(category)+r'</option>',body)[1]
check('purchases.php' in body and 'value="save_expense"' not in body,'ordinary new expense form replaced by purchasing')
base=dict(expense_date='2040-04-03',category_id=category_id,method='momo',amount='7.30',vendor='Synthetic legacy vendor',reference_no='=2+2',description='Legacy compatibility '+tag)
id=seed(fixture['sender']['email'],base)['id'];filters=dict(start='2040-04-01',end='2040-04-30',category_id=category_id)
def ledger():return admin.request('/expenses.php?'+urllib.parse.urlencode(filters))[2]
def total():return re.search(r'<strong>₵([^<]+)</strong>',ledger())[1]
check(total()=='0.00','pre-existing pending submission excluded from cost')
check('Legacy compatibility '+tag not in other.request('/expenses.php?status=')[2],'staff cannot read another staff legacy submission')
token=clerk.token('/purchases.php?page=new')
check(clerk.request('/expenses.php',dict(action='save_expense',csrf=token,operation_key=secrets.token_hex(16)))[0]==409,'old expense creation denied with valid CSRF')
check(clerk.request('/expenses.php',dict(action='save_expense',operation_key=secrets.token_hex(16)))[0]==403,'old creation denial retains CSRF guard')
check(clerk.request('/expenses.php',dict(action='review_expense',csrf=token,submission_id=id,decision='approved',reason='Forged clerk review'))[0]==403,'staff cannot approve existing submissions')
review=dict(action='review_expense',csrf=admin.token('/expenses.php'),submission_id=id,decision='approved',reason='Original submitted evidence checked')
check(admin.request('/expenses.php',review)[0]==302 and total()=='7.30','existing submission approval posts one cost')
check(admin.request('/expenses.php',review)[0]==302 and total()=='7.30','legacy review replay cannot double count')
check('Original submitted evidence checked' in clerk.request('/expenses.php')[2],'requester retains original outcome and review reason')
rejected=seed(fixture['sender']['email'],dict(base,amount='9.90',reference_no='REJECT-'+tag))['id']
check(admin.request('/expenses.php',dict(review,submission_id=rejected,decision='rejected',reason='Evidence does not support this claim'))[0]==302 and total()=='7.30','rejected existing claim remains uncounted')
seed('admin@example.invalid',dict(base,amount='1.10',reference_no='HISTORICAL-'+tag))
check(total()=='8.40','pre-existing posted costs remain in ledger')
status,headers,text=admin.request('/expenses.php?'+urllib.parse.urlencode(dict(filters,export='csv')));rows=list(csv.reader(io.StringIO(text)))
check(status==200 and len(rows)==3,'CSV retains both posted expense sources')
check(any(row[5]=="'=2+2" for row in rows[1:]),'CSV formula neutralisation retained')
check('REJECT-'+tag not in text,'CSV excludes rejected unposted claim')
check(clerk.request('/expenses.php?export=csv')[0]==403,'staff cannot export broad expense ledger')
check(admin.request('/expenses.php?delete=1')[0]==405 and admin.request('/expenses.php?edit=1')[0]==405,'posted expense edit/delete routes remain denied')
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/audit_fault.php'),'enable'],check=True)
try:
 status,_,text=admin.request('/expenses.php?'+urllib.parse.urlencode(filters))
 check(status==503 and 'Counted expenses' not in text,'required read-audit failure hides fetched ledger')
finally:subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/audit_fault.php'),'disable'],check=True)
check(total()=='8.40','audit recovery leaves original ledger totals unchanged')
print(f'TOTAL {count} expense compatibility HTTP checks passed.')
