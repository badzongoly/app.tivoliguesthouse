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

n=0
def check(ok,label):
 global n
 assert ok,label
 n+=1;print('PASS '+label,flush=True)
f=json.loads((ROOT/'.local-setup/stock-issue-http-fixture.json').read_text(encoding='utf-8'))
owner=Browser();staff=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!');staff.login(f['users']['staff']['email'],'Synthetic Stock Passphrase 123!')
for b,who in [(owner,'owner'),(staff,'clerk')]:
 body=b.request('/stock-counts.php?page=new')[2]
 check('data-count-picker' in body and 'data-count-search' in body and 'data-count-select' in body,who+' can search and bulk-select count items')
 check('data-count-summary' in body and 'data-count-start' in body,who+' gets selection feedback and clear start action')
 check('value="Weekly Stock Count"' in body and 'Count Details (Optional)' in body,who+' weekly default keeps optional details out of normal path')
 check('Expected At Count' not in body and re.search(r'name="operation_key" value="[a-f0-9]{32}"',body),who+' blind balances and durable form identity retained')
cases=[('/finance.php?page=daily','daily'),('/finance.php?page=weekly','weekly'),('/finance.php?page=payments','payments'),('/finance.php?page=providers','purchases'),('/finance.php?page=payroll','payroll'),('/finance.php?page=revenue','revenue'),('/finance.php?page=profit','profit'),('/finance.php?page=activity','activity'),('/finance.php?page=expenses','expenses'),('/finance.php','overview'),('/finance.php?page=deposits','deposits'),('/finance.php?page=income','income'),('/finance.php?page=discrepancies','discrepancies'),('/purchases.php','purchases'),('/expenses.php','expenses'),('/financial-corrections.php','corrections'),('/financial-corrections.php?area=expenses','corrections'),('/financial-corrections.php?area=charges','corrections'),('/financial-corrections.php?area=purchases','corrections')]
for path,key in cases:
 code,_,body=owner.request(path);check(code==200,path+' renders')
 nav=re.findall(r'<nav[^>]*class="[^"]*finance-navigation[^"]*"[^>]*>(.*?)</nav>',body,re.S)
 check(len(nav)==1 and 'Provider Payments</a>' not in nav[0] and 'Payroll &amp; Staff Cost</a>' not in nav[0],path+' uses one consolidated primary navigation')
 if key=='corrections':
  source=re.search(r'<nav[^>]*aria-label="Correction Sources"[^>]*>(.*?)</nav>',body,re.S)
  check(source is not None and source[1].index('Guest Receipts')<source[1].index('Guest Charges')<source[1].index('Expenses')<source[1].index('Purchases'),path+' correction sources use consistent order')
body=owner.request('/purchases.php')[2];check('Legacy Provider Records' in body,'Existing provider history remains reachable under purchases')
body=owner.request('/expenses.php')[2];sub=re.search(r'<nav[^>]*expense-navigation[^>]*>(.*?)</nav>',body,re.S);check(sub and 'Capture &amp; Approval' not in sub[1] and 'Capture & Approval' not in sub[1] and 'Expense Payments' in sub[1],'Expense payment view stays reachable without duplicate capture link')
body=owner.request('/finance.php?page=discrepancies')[2];check(body.count('>Cash → MoMo Deposits</a>')==1,'Discrepancy page does not repeat deposit navigation')
check('id="weekly-totals" tabindex="-1"' in owner.request('/finance.php?page=weekly')[2],'Weekly review totals anchor is valid and focusable')
for action,label in [('deposit_record','Record Cash → MoMo Deposit'),('income_record','Record Activity Payment')]:
 body=owner.request('/finance.php?page='+('deposits' if action=='deposit_record' else 'income')+'&form='+action)[2]
 check('name="action" value="'+action+'"' in body and '>'+label+'</a>' not in body,action+' form keeps one action without repeated entry link')
for page in ['weekly','profit','payroll']:
 check(staff.request('/finance.php?page='+page)[0]==403,'Clerk cannot open protected '+page+' workflow')
print('TOTAL '+str(n)+' stock start and finance UX HTTP checks passed')
