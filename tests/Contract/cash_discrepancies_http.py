"""Only the marked synthetic service/database; no ordinary application target."""
import html,http.cookiejar,json,pathlib,re,secrets,subprocess,urllib.request,urllib.error,urllib.parse
ROOT=pathlib.Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:8099'
class NR(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
class Browser:
 def __init__(self):self.op=urllib.request.build_opener(NR(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(self,path,data=None,file=None):
  headers={};body=urllib.parse.urlencode(data).encode() if data is not None else None
  if file:
   boundary='Cash'+secrets.token_hex(8);parts=[]
   for k,v in data.items():parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"\r\n\r\n'+str(v)+'\r\n').encode())
   parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="evidence"; filename="proof.pdf"\r\nContent-Type: application/pdf\r\n\r\n').encode()+file+b'\r\n');parts.append(('--'+boundary+'--\r\n').encode());body=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
  try:r=self.op.open(urllib.request.Request(BASE+path,data=body,headers=headers),timeout=40)
  except urllib.error.HTTPError as e:r=e
  text=r.read().decode('utf-8');assert (path=='/cash-discrepancies.php' and r.code==403) or r.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unsafe service'
  assert not re.search(r'Fatal error|Warning:|Parse error|SQLSTATE\[',text),'Application error'
  return r.code,r.headers,text
 def fields(self,path):
  text=self.request(path)[2];f=next(x for x in re.findall(r'<form\b[^>]*>(.*?)</form>',text,re.S) if 'data-cd-action' in x or 'name="action" value="report"' in x)
  return {k:html.unescape(v) for k,v in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',f)}
 def login(self,email,password):
  text=self.request('/auth/login.php')[2];csrf=html.unescape(re.search(r'name="csrf" value="([^"]+)"',text)[1]);assert self.request('/auth/login.php',dict(csrf=csrf,email=email,password=password))[0]==302
count=0
def check(ok,label):
 global count
 assert ok,label
 count+=1;print('PASS '+label,flush=True)
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/cash_discrepancies_http_seed.php')],cwd=ROOT,check=True,capture_output=True)
fixture=json.loads((ROOT/'.local-setup/cash-discrepancies-http-fixture.json').read_text(encoding='utf-8'));root='/finance.php?page=discrepancies'
owner=Browser();staff=Browser();other=Browser();admin=Browser();outsider=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
for b,k in [(staff,'staff'),(other,'other'),(admin,'admin'),(outsider,'outsider')]:b.login(fixture['users'][k]['email'],'Synthetic Cash Discrepancy Passphrase 123!')
check(Browser().request(root)[0]==302,'login required');check(outsider.request(root)[0]==403,'finance permission required');check(staff.request('/cash-discrepancies.php')[0]==403,'private controller denied directly')
source=root+'&type=deposit&source_id='+str(fixture['deposit']);check(other.request(source)[0]==403 and admin.request(source)[0]==403,'source visibility restricted to involved staff / configured owner')
text=staff.request(source)[2];check('Report Difference' in text and 'Evidence File (Optional)' in text,'short source-linked report form');p=staff.fields(source)|dict(category='short',reason='Synthetic independently reported short wallet credit.')
check(staff.request(source,p|dict(csrf='bad'))[0]==403,'invalid CSRF denied');check(staff.request(source,p|dict(operation_key='bad'))[0]==400,'invalid idempotency key denied')
proof=b'%PDF-1.4\nSynthetic cash discrepancy private proof\n%%EOF';code,headers,_=staff.request(source,p,proof);check(code==302,'report and optional private evidence committed');id=int(re.search(r'id=(\d+)',headers['Location'])[1]);detail=root+'&id='+str(id)
check(staff.request(source,p,proof)[1]['Location']==headers['Location'],'same-key report retry returns original case');check(staff.request(source,p|dict(reason='Changed input'),proof)[0]==400,'changed payload same key denied')
check(other.request(detail)[0]==403 and admin.request(detail)[0]==403,'case and evidence private');text=owner.request(detail)[2];evidence=html.unescape(re.search(r'href="([^"]*finance-evidence.php[^"]+)"',text)[1]);evidence='/finance-evidence.php?'+urllib.parse.urlsplit(evidence).query
check(other.request(evidence)[0]==403,'other user cannot download proof');check(owner.request(evidence)[0]==200 and staff.request(evidence)[0]==200,'owner and involved staff can download proof')
q=owner.fields(detail)|dict(decision='credit',amount='480.00',reference='CD-VERIFIED-'+fixture['tag'],reason='Synthetic official wallet evidence independently checked.',verified='1')
check(admin.request(detail,q)[0]==403 and staff.request(detail,staff.fields(detail)|dict(decision='credit',amount='480.00',reference='forged',reason='Forged authority',verified='1'))[0]==403,'posted fields cannot grant owner authority')
code,headers,_=owner.request(detail,q);check(code==302,'owner records partial verified credit');check(owner.request(detail,q)[0]==302,'partial credit retry returns original outcome');check(owner.request(detail,q|dict(amount='470.00'))[0]==400,'changed credit retry denied')
text=owner.request(detail)[2];check('GHS 480.00' in text and 'GHS 20.00' in text and 'Partial' in text,'exact partial status and residual displayed');check(owner.request(detail,owner.fields(detail)|dict(decision='credit',amount='21.00',reference='too-much-'+fixture['tag'],reason='Too much',verified='1'))[0]==400,'different key cannot over-credit')
q=owner.fields(detail)|dict(decision='return_cash',amount='20.00',reference='CD-RETURN-'+fixture['tag'],reason='Synthetic actual uncredited cash returned to original custodian.',verified='1');check(owner.request(detail,q)[0]==302,'owner records bounded cash return');check('Returned To Custody' in owner.request(detail)[2],'returned cash remains explicitly visible')
check('Report / Resolve Difference' in staff.request('/finance.php?page=deposits')[2],'deposit register links source to workflow')
check('Cash &amp; MoMo Discrepancies' in owner.request(root)[2],'existing finance navigation includes case register');check('Report / Resolve Difference' in owner.request('/finance.php?page=daily')[2],'daily wallet matching links discrepancy workflow')
check(staff.request(root+'&type=payment&source_id='+str(fixture['receipts']['momo']))[0]==200,'MoMo source report available without physical cash return')
check('Synthetic independently reported short wallet credit.' not in admin.request('/finance.php?page=activity')[2] and 'Synthetic official wallet evidence independently checked.' not in admin.request('/finance.php?page=activity')[2],'generic finance audit does not expose private discrepancy evidence to unrelated admin')
redirect=owner.request('/finance.php?page=deposits&form=deposit_confirm&id='+str(fixture['deposit']));check(redirect[0]==302 and 'page=discrepancies' in redirect[1]['Location'],'old deposit confirmation links continue directly on the existing discrepancy')
receiver=Browser();receiver.login(fixture['users']['recipient']['email'],'Synthetic Cash Discrepancy Passphrase 123!');hsource=root+'&type=handover&source_id='+str(fixture['transfer']);q=staff.fields(hsource)|dict(category='disputed',reason='Synthetic receiver reported a short cash handover.');code,headers,_=staff.request(hsource,q);check(code==302,'cash handover opens the same source-linked resolution workflow');hdetail=root+'&id='+re.search(r'id=(\d+)',headers['Location'])[1];q=receiver.fields(hdetail)|dict(decision='acknowledge',amount='50.00',reason='I personally received exactly half of this handover.',verified='1');check(receiver.request(hdetail,q)[0]==302,'named recipient acknowledges a partial amount without invented external reference');text=receiver.request('/handover.php?page=cash&id='+str(fixture['transfer']))[2];check('Partially Acknowledged / Residual Reserved' in text and 'value="cash_ack"' not in text,'original handover shows partial status and hides obsolete full-acknowledgement form')
file=(ROOT/'.local-setup/cash-discrepancies-http-result.json');file.write_text(json.dumps(dict(assertions=count,case=id,deposit=fixture['deposit'],fixture=fixture),indent=2),encoding='utf-8')
print('TOTAL',count,'cash discrepancy HTTP assertions passed.')
