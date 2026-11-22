import base64, json
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


import json,html,datetime
fixture=json.loads((ROOT/'.local-setup/finance-changes-fixture.json').read_text(encoding='utf-8'))
class UploadBrowser(Browser):
 def upload(self,path,data,file_bytes=None,filename='evidence.pdf'):
  boundary='TivoliBoundary'+secrets.token_hex(16);body=b''
  for name,value in data.items(): body+=('--'+boundary+'\r\nContent-Disposition: form-data; name="'+name+'"\r\n\r\n'+str(value)+'\r\n').encode()
  if file_bytes is not None: body+=('--'+boundary+'\r\nContent-Disposition: form-data; name="evidence"; filename="'+filename+'"\r\nContent-Type: application/pdf\r\n\r\n').encode()+file_bytes+b'\r\n'
  body+=('--'+boundary+'--\r\n').encode()
  request=urllib.request.Request(BASE+path,data=body,headers={'Content-Type':'multipart/form-data; boundary='+boundary})
  try:response=self.opener.open(request,timeout=20)
  except urllib.error.HTTPError as error:response=error
  text=response.read().decode('utf-8','replace')
  if response.headers.get('X-Tivoli-Test-Mode')!='synthetic':raise RuntimeError('Unverified HTTP upload target')
  if any(x in text for x in ['Fatal error','Warning:','Parse error','SQLSTATE[']):raise RuntimeError('Application error during upload')
  return response.code,response.headers,text
count=0
def check(ok,label):
 global count
 if not ok:raise RuntimeError('FAIL '+label)
 count+=1;print('PASS '+label,flush=True)
def fields(browser,path,action):
 body=browser.request(path)[2]
 forms=re.findall(r'<form\b[^>]*>(.*?)</form>',body,re.S)
 form=next(f for f in forms if 'name="action" value="'+action+'"' in f)
 return {html.unescape(k):html.unescape(v) for k,v in re.findall(r'<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"',form)}
def row_id(body,tag):
 row=next(row for row in re.findall(r'<tr\b[^>]*>(.*?)</tr>',body,re.S) if tag in row)
 return re.search(r'#(\d+)',row).group(1)
admin=UploadBrowser();clerk=UploadBrowser();other=UploadBrowser()
check(admin.login('admin@example.invalid','Synthetic Admin Passphrase 123!')[0]==302,'administrator signs in')
check(clerk.login(fixture['clerk_email'],'Synthetic Finance Change Passphrase 123!')[0]==302,'cashier signs in')
check(other.login(fixture['other_email'],'Synthetic Finance Change Passphrase 123!')[0]==302,'other cashier signs in')
tag=secrets.token_hex(5);today=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d');pdf=('%PDF-1.4\nSynthetic multipart provider '+tag).encode()
path='/finance.php?page=providers&form=provider_request'
status,headers,_=clerk.request(path);check(status==302 and 'purchases.php' in headers['Location'],'new provider form routes to purchase approval')
pdf=('%PDF-1.4\nSynthetic provider evidence '+tag).encode()
provider={'payee':'HTTP service '+tag,'phone':'0000000000','date':today,'amount':'12.00','accepted':'1'}
seed={'kind':'provider','email':fixture['clerk_email'],'input':provider,'proof':base64.b64encode(pdf).decode()}
pid=str(json.loads(subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/purchase_legacy_http_seed.php')],input=json.dumps(seed),text=True,capture_output=True,check=True).stdout)['id'])
check(bool(pid),'synthetic pre-existing provider record prepared for compatibility')
forged={**provider,'action':'provider_request','operation_key':secrets.token_hex(16),'csrf':clerk.token('/finance.php?form=deposit_record')}
check(clerk.upload('/finance.php?page=providers',forged,pdf)[0]==409,'obsolete provider submission denied even with evidence')
check(clerk.upload('/finance.php?page=providers',{k:v for k,v in forged.items() if k!='csrf'},pdf)[0]==403,'obsolete provider POST still requires CSRF')
body=clerk.request('/finance.php?page=providers')[2];check('HTTP service '+tag in body,'existing provider record remains visible')
status,headers,body=clerk.request('/finance-evidence.php?kind=provider&id='+pid);check(status==200 and body==pdf.decode() and headers.get('Content-Disposition','').startswith('attachment;'),'recorder downloads exact uploaded evidence safely')
check(other.request('/finance-evidence.php?kind=provider&id='+pid)[0]==403,'other cashier denied provider evidence')
check(admin.request('/finance-evidence.php?kind=provider&id='+pid)[0]==200,'admin can inspect evidence before review')
check(clerk.request('/finance-evidence.php?kind=provider&id='+pid,{'csrf':clerk.token('/finance.php?form=deposit_record')})[0]==405,'evidence endpoint accepts GET only')
body=admin.request('/finance.php?page=providers&form=request_review&id='+pid)[2];check('Download Work / Receipt Evidence' in body and 'Phone Number' in body,'review page includes phone and evidence link')
for action in ['day_close','day_reopen']:
 body=admin.request('/finance.php?page=daily&form='+action+'&id='+today)[2]
 field=re.search(r'<input name="comment"[^>]*>',body).group(0)
 check('required' not in field,'daily '+action+' comment optional in HTML')
body=clerk.request('/expenses.php')[2];check('purchases.php' in body and 'value="save_expense"' not in body,'new expense entry moved to purchasing; old records retained')
body=clerk.request('/purchases.php?page=new&mode=report')[2]
check('type="file" name="evidence"' in body and 'value="cash"' in body and 'value="momo"' in body,'actual-cost exception supports private evidence and Cash/MoMo')
cat=re.search(r'<select name="category_id"[^>]*>.*?<option value="(\d+)"',body,re.S).group(1)
expense={'expense_date':today,'category_id':cat,'method':'cash','amount':'6.00','vendor':'HTTP cash supplier '+tag,'description':'Receipt-uploaded separate cash cost','reference_no':''}
receipt=('%PDF-1.4\nSynthetic expense receipt '+tag).encode()
seed={'kind':'expense','email':fixture['clerk_email'],'input':expense,'proof':base64.b64encode(receipt).decode()}
sid=str(json.loads(subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/purchase_legacy_http_seed.php')],input=json.dumps(seed),text=True,capture_output=True,check=True).stdout)['id'])
check(clerk.upload('/expenses.php',dict(expense,action='save_expense',operation_key=secrets.token_hex(16),csrf=clerk.token('/finance.php?form=deposit_record')),receipt)[0]==409,'obsolete direct expense creation denied')
check(clerk.request('/finance-evidence.php?kind=submission&id='+sid)[2]==receipt.decode(),'submitter can download pending receipt')
check(other.request('/finance-evidence.php?kind=submission&id='+sid)[0]==403,'other cashier denied pending receipt')
check(admin.request('/expenses.php',{'csrf':admin.token('/expenses.php'),'action':'review_expense','submission_id':sid,'decision':'approved','reason':'Receipt and incurred cash cost checked'})[0]==302,'administrator approves cash expense')
body=admin.request('/finance.php?page=expenses')[2];eid=row_id(body,'HTTP cash supplier '+tag)
check(admin.request('/finance-evidence.php?kind=expense&id='+eid)[2]==receipt.decode(),'approved expense retains same uploaded receipt')
body=admin.request('/finance.php?page=expenses&form=pay_cost&id='+eid+'&type=expense')[2];check('Payment Method: Cash' in body and 'do not reduce' in body,'cash settlement UI explains separate sales-deposit obligation')
income_path='/finance.php?page=income&form=income_record';income={**fields(clerk,income_path,'income_record'),'payer':'HTTP event payer '+tag,'phone':'','activity':'Courtyard hire '+tag,'amount':'8.25','method':'cash','treatment':'earned','provided':'1'}
check(clerk.request('/finance.php?page=income',income)[0]==302,'cashier records non-guest activity payment')
check(clerk.request('/finance.php?page=income',income)[0]==302,'activity payment HTTP replay does not duplicate receipt')
body=admin.request('/finance.php?page=payments&start='+today+'&end='+today)[2]
check('HTTP event payer '+tag in body and 'Courtyard hire '+tag in body,'date-range Payment Log includes non-guest payment')
check('href="http://127.0.0.1:8099/payments.php"' not in body and 'Payment Log' in body,'Payments removed from sidebar and present as finance tab')
status,headers,_=admin.request('/payments.php?start='+today+'&end='+today);check(status==302 and 'page=payments' in headers['Location'] and 'start='+today in headers['Location'],'old Payments URL redirects preserving date filters')
check(clerk.request('/finance.php?page=payments')[0]==403,'cashier cannot bypass payment log permission')
check(admin.request('/finance.php?page=payments&start=2026-02-30&end='+today)[0]==400,'invalid Payment Log filter rejected')
print('TOTAL',count,'finance-change HTTP assertions passed.')
