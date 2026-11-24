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
fixture=json.loads((ROOT/'.local-setup/finance-fixture.json').read_text(encoding='utf-8'))
count=0
def check(ok,label):
 global count
 if not ok: raise RuntimeError('FAIL '+label)
 count+=1;print('PASS '+label,flush=True)
admin=Browser();check(admin.login('admin@example.invalid','Synthetic Admin Passphrase 123!')[0]==302,'administrator authenticates on marked test server')
clerk=Browser();check(clerk.login(fixture['clerk_email'],'Synthetic Finance Passphrase 123!')[0]==302,'synthetic cashier authenticates')
for page in ['overview','payments','income','daily','deposits','expenses','payroll','providers','revenue','profit','activity']:
 status,headers,body=admin.request('/finance.php?page='+page)
 check(status==200 and 'FINANCE &amp; CASH CONTROL' in body,'administrator '+page+' renders')
for page in ['payments','expenses','payroll','revenue','profit','activity']:
 check(clerk.request('/finance.php?page='+page)[0]==403,'cashier denied direct '+page+' access')
for page in ['overview','deposits','providers','income']:
 status,headers,body=clerk.request('/finance.php?page='+page)
 check(status==200 and 'Prepare Monthly Payroll' not in body,'cashier '+page+' is scoped')
for form,label in {
 'deposit_record':'Record Deposit','deposit_confirm':'Confirm Deposit',
 'request_review':'Save Review Decision','pay_cost':'Record Completed Payment','day_close':'Close Day',
 'day_reopen':'Reopen Day','period_lock':'Lock Period','period_reopen':'Reopen Period','review_action':'Save Follow-Up Action',
}.items():
 status,headers,body=admin.request('/finance.php?page=payroll&form='+form+'&id=1&type=request')
 form_body=next(f for f in re.findall(r'<form\b[^>]*>(.*?)</form>',body,re.S) if 'name="action" value="'+form+'"' in f)
 check(status==200 and ('>'+label+'</button>') in form_body and 'name="operation_key"' in form_body,'action form '+form+' has its specific submit label')
 check(re.search(r'<a class="button secondary" href="[^"]+">Cancel</a>',form_body) is not None,'action form '+form+' has a styled Cancel link')
status,headers,_=admin.request('/finance.php?page=daily&form=receipt_match&type=payment&id=1')
check(status==302 and 'page=discrepancies' in headers['Location'],'Old matching URL opens the linked difference workflow')
body=admin.request('/finance.php?page=daily')[2]
check('Recorded MoMo payments count as successful' in body and 'Unmatched Guest' not in body and 'Match Wallet Evidence' not in body,'Daily review accepts recorded MoMo payments without a matching queue')
check(clerk.request('/finance.php',{'action':'provider_request','operation_key':secrets.token_hex(16)})[0]==403,'finance mutation requires CSRF')
body=clerk.request('/finance.php?page=deposits&form=deposit_record')[2];csrf=re.search('name="csrf" value="([a-f0-9]+)"',body).group(1)
check(clerk.request('/finance.php',{'csrf':csrf,'action':'period_lock','operation_key':secrets.token_hex(16),'period':'week','start':'2026-10-26','comment':'Forged review','complete':'1'})[0]==403,'cashier cannot forge a financial review')
check(admin.request('/finance.php?date=2026-02-30')[0]==400,'invalid filter dates rejected')
status,_,body=admin.request('/purchases.php?page=new');match=re.search(r'<select name="method"[^>]*>(.*?)</select>',body,re.S)
check(status==200 and match and 'momo' in match.group(1) and 'value="cash"' in match.group(1) and all(x not in match.group(1) for x in ['value="bank"','value="card"']),'new purchase costs offer Cash or MoMo')
body=admin.request('/hr-settings.php?section=payments')[2]
check('Bank names' not in body and 'MoMo Providers' in body,'payment settings omit bank workflow after activation')
status,_,body=admin.request('/finance.php?page=deposits&form=deposit_record')
check('data-confirm=' in body and 'id="finance-action" tabindex="-1"' in body,'finance forms have confirmation and action focus')
body=admin.request('/finance.php?page=deposits&form=deposit_confirm&id=1')[2]
confirm_form=next(f for f in re.findall(r'<form\b[^>]*>(.*?)</form>',body,re.S) if 'name="action" value="deposit_confirm"' in f)
check('name="amount"' not in confirm_form and 'name="evidence"' not in confirm_form and 'name="verified"' in confirm_form,'Deposit Confirm has only checkbox and action controls')
status,headers,body=admin.request('/finance.php?page=payroll&form=payroll_request')
check(status==302 and 'payroll.php' in headers.get('Location',''),'old payroll creation routes to private approved workflow')
body=admin.request('/payroll.php')[2]
check('Calculation / Attendance Evidence' not in body and 'Start Monthly Payroll' in body,'private payroll entry replaces removed legacy evidence form')
print('TOTAL',count,'finance HTTP assertions passed.')
