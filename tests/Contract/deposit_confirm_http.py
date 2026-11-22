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
f=json.loads((ROOT/'.local-setup/finance-fixture.json').read_text(encoding='utf-8'));count=0
def check(ok,label):
 global count
 if not ok:raise RuntimeError('FAIL '+label)
 count+=1;print('PASS '+label,flush=True)
def fields(browser,path,action):
 body=browser.request(path)[2];form=next(f for f in re.findall(r'<form\b[^>]*>(.*?)</form>',body,re.S) if 'name="action" value="'+action+'"' in f)
 return {html.unescape(k):html.unescape(v) for k,v in re.findall(r'<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"',form)},form
admin=Browser();clerk=Browser();check(admin.login('admin@example.invalid','Synthetic Admin Passphrase 123!')[0]==302,'test administrator authenticates');check(clerk.login(f['clerk_email'],'Synthetic Finance Passphrase 123!')[0]==302,'test cashier authenticates')
tag=secrets.token_hex(6);today=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d')
values,_=fields(clerk,'/finance.php?page=income&form=income_record','income_record');check(clerk.request('/finance.php?page=income',{**values,'payer':'Synthetic confirmation payer','activity':'Confirmation fixture '+tag,'amount':'5.00','method':'cash','treatment':'advance'})[0]==302,'synthetic source cash receipt recorded')
values,_=fields(clerk,'/finance.php?page=deposits&form=deposit_record','deposit_record');reference='CHECKBOX-'+tag
check(clerk.request('/finance.php?page=deposits',{**values,'amount':'3.00','fee':'0.10','date':today,'reference':reference})[0]==302,'synthetic deposit with fee recorded')
body=clerk.request('/finance.php?page=deposits')[2];row=next(row for row in re.findall(r'<tr\b[^>]*>(.*?)</tr>',body,re.S) if reference in row);id=re.search(r'#(\d+)',row).group(1)
values,form=fields(admin,'/finance.php?page=deposits&form=deposit_confirm&id='+id,'deposit_confirm');values.pop('verified',None)
check('name="amount"' not in form and 'name="evidence"' not in form and 'name="verified"' in form,'confirmation needs no amount or evidence input')
check('Expected Wallet Credit' in form and '₵2.90' in form,'derived net credit displayed as read-only information')
check(admin.request('/finance.php?page=deposits',values)[0]==400,'unchecked confirmation rejected server-side')
check(admin.request('/finance.php?page=deposits',{**values,'verified':'1','amount':'2.91'})[0]==400,'conflicting legacy amount rejected')
check(clerk.request('/finance.php?page=deposits',{**values,'csrf':clerk.token('/finance.php?form=deposit_record'),'verified':'1'})[0]==403,'cashier cannot forge administrator confirmation')
checked={**values,'verified':'1'};check(admin.request('/finance.php?page=deposits',checked)[0]==302,'checkbox-only confirmation succeeds')
check(admin.request('/finance.php?page=deposits',checked)[0]==302,'identical confirmation retry returns original event')
body=admin.request('/finance.php?page=deposits')[2];row=next(row for row in re.findall(r'<tr\b[^>]*>(.*?)</tr>',body,re.S) if reference in row);check('Wallet Confirmed' in row,'deposit register shows confirmed state')
print('TOTAL',count,'checkbox-confirmation HTTP assertions passed.')
