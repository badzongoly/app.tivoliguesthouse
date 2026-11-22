"""Marked synthetic web service only; no normal localhost application access."""
import pathlib,re,json,subprocess,datetime,html,urllib.parse,urllib.error
ROOT=pathlib.Path(__file__).resolve().parents[2]
definitions=(ROOT/'tests/Contract/receipt_corrections_http.py').read_text(encoding='utf-8').split('\ncount=0')[0].replace('name="evidence"','name="evidence_file"');scope={'__file__':str(ROOT/'tests/Contract/receipt_corrections_http.py')};exec(definitions,scope);Browser=scope['Browser'];count=0
def check(ok,label):
 global count
 assert ok,label
 count+=1;print('PASS '+label,flush=True)
check(Browser().request('/auth/login.php')[0]==200,'synthetic service marker verified before fixture setup')
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/purchase_correction_http_seed.php')],cwd=ROOT,check=True,capture_output=True)
fixture=json.loads((ROOT/'.local-setup/purchase-correction-http-fixture.json').read_text(encoding='utf-8'));owner=Browser();staff=Browser();other=Browser();admin=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
for client,key in [(staff,'staff'),(other,'other'),(admin,'admin')]:client.login(fixture['users'][key]['email'],'Synthetic purchase correction passphrase!')
base='/financial-corrections.php?area=purchases';source=base+'&source_type=purchase&source_id='+str(fixture['purchase'])
check(Browser().request(base)[0]==302,'purchase corrections require login')
try:direct=staff.op.open('http://127.0.0.1:8099/purchase-corrections.php')
except urllib.error.HTTPError as response:direct=response
check(direct.code==403 and direct.read()==b'','internal purchase controller denies direct access before bootstrap')
check(staff.request(base)[0]==200 and 'Purchases &amp; Providers' in staff.request('/financial-corrections.php')[2],'purchase corrections reuse existing Finance correction navigation')
check(staff.request(base+'&page=review')[0]==403 and admin.request(base+'&page=review')[0]==403,'review queue only available to configured owner')
check(other.request(source)[0]==403 and admin.request(source)[0]==403,'original source details private to requester and owner')
check('Request A Linked Correction' in staff.request(source)[2],'posted source offers supported correction form')
route=staff.request('/financial-corrections.php?area=expenses&expense_id='+str(fixture['expense']));check(route[0]==302 and 'area=purchases' in route[1]['Location'],'linked expense correction automatically routes to original purchase')
check(other.request('/financial-corrections.php?area=expenses&expense_id='+str(fixture['expense']))[0]==403,'linked expense redirect cannot reveal another staff source')
p=staff.fields(source,'request')|dict(type='cost',amount='400.00',reason='Synthetic private purchase correction HTTP evidence',confirmed='1')
check(staff.request(source,p|dict(csrf='bad'))[0]==403,'forged correction request denied')
check(staff.request(source,p|dict(operation_key='bad'))[0]==400,'malformed operation identity denied')
proof=b'%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF';code,headers,body=staff.request(source,p,proof)
check(code==302,'request with optional private evidence accepted');cid=int(re.search(r'id=(\d+)',headers['Location'])[1]);detail=base+'&id='+str(cid)
check(staff.request(source,p,proof)[0]==302,'same operation identity returns original request')
check(other.request(detail)[0]==403 and admin.request(detail)[0]==403,'private case denies other staff and ordinary administrator')
body=owner.request(detail)[2];check('GHS 400.00' in body and 'GHS 500.00' in body and 'GHS 100.00' in body,'owner sees exact revised cost preserved paid amount and supplier recovery due')
link=html.unescape(re.search(r'href="([^"]*finance-evidence.php[^"]+)"',body)[1]);evidence='/finance-evidence.php?'+urllib.parse.urlsplit(link).query
check(owner.request(evidence)[0]==200 and staff.request(evidence)[0]==200 and other.request(evidence)[0]==403 and admin.request(evidence)[0]==403,'private linked correction evidence enforces source account authority')
check('Synthetic private purchase correction HTTP evidence' not in owner.request('/finance.php?page=activity')[2],'generic audit page excludes private correction notes')
p=owner.fields(detail,'post')|dict(reason='Synthetic owner independently checked exact supplier amounts',verified='1')
check(owner.request(detail,p|dict(reason=''))[0]==400,'owner must supply verified decision reason')
check(owner.request(detail,p)[0]==302 and owner.request(detail,p)[0]==302,'owner posts linked correction once across retries')
body=staff.request(detail)[2];check('Posted' in body and 'GHS 100.00' in body and 'Approve &amp; Record Correction' not in body,'requester sees posted financial result without approval controls')
body=staff.request(detail+'&page=notice')[2];check('Purchase Adjustment Notice' in body and 'Actual Money Moved: GHS 0.00' in body and 'Synthetic private purchase correction HTTP evidence' not in body,'adjustment notice excludes private reasons and does not imply refund')
body=staff.request('/purchases.php?id='+str(fixture['purchase'])+'&tab=payment')[2];check('Supplier Recovery Due' in body and 'GHS 100.00' in body,'original purchase displays effective supplier recovery balance')
fresh=staff.fields(source,'followup')|dict(reason='Synthetic contacted supplier; awaiting evidence')
check(staff.request(source,fresh)[0]==302 and staff.request(source,fresh)[0]==302,'supplier follow-up is recorded idempotently')
body=staff.request(source)[2];check('Synthetic contacted supplier; awaiting evidence' in body and 'GHS 100.00' in body,'contact note retains unresolved recovery amount')
stock=base+'&source_type=purchase&source_id='+str(fixture['stock']);p=staff.fields(stock,'request')|dict(type='stock',stock_basis='actual_return',qty='2',amount='400.00',actual_at=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S'),return_reference='HTTP-RETURN-'+str(fixture['stock']),reason='Synthetic actual stock return proof',confirmed='1');code,headers,_=staff.request(stock,p);check(code==302,'actual supplier return request accepted from original stock receipt');sid=int(re.search(r'id=(\d+)',headers['Location'])[1]);stockdetail=base+'&id='+str(sid);check('HTTP-RETURN-'+str(fixture['stock']) in owner.request(stockdetail)[2] and 'Actual Return' in owner.request(stockdetail)[2],'owner sees specific actual return time and evidence reference before approval');p=owner.fields(stockdetail,'post')|dict(reason='Synthetic exact original receipt and completed return checked',verified='1');check(owner.request(stockdetail,p)[0]==400,'stock approval requires separate original-link and quantity verification');p['stock_verified']='1';check(owner.request(stockdetail,p)[0]==302,'verified actual return posts through owner correction decision');body=staff.request(stock)[2];check('Actually Returned 2.00' in body and 'Current On Hand 8.00' in body,'stock source displays exact original receipt return and on-hand positions')

legacy=base+'&source_type=provider&source_id='+str(fixture['legacy'])
body=owner.request('/finance.php?page=providers')[2];check('Linked Position / Correct Record' in body and 'source_id='+str(fixture['legacy']) in body,'legacy provider register exposes linked correction and actual settlement action')
p=staff.fields(legacy,'request')|dict(type='cost',amount='400.00',reason='Synthetic actual legacy provider corrected cost',confirmed='1');code,headers,_=staff.request(legacy,p);check(code==302,'legacy provider request uses same linked correction flow');lid=int(re.search(r'id=(\d+)',headers['Location'])[1]);ldetail=base+'&id='+str(lid);p=owner.fields(ldetail,'post')|dict(reason='Synthetic owner independently verified legacy original service cost',verified='1');check(owner.request(ldetail,p)[0]==302,'owner posts approved legacy provider cost adjustment')
p=owner.fields(legacy,'settle')|dict(amount='150.00',method='cash',payer_id=str(fixture['users']['staff']['id']),actual_at=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S'),reference='',evidence='Synthetic completed separately funded provider cash payment',reason='Synthetic exact actual supplier handover verified',completed='1');check(owner.request(legacy,p)[0]==302 and owner.request(legacy,p)[0]==302,'owner records actual partial cash legacy provider settlement once');body=owner.request(legacy)[2];check('GHS 250.00' in body and 'GHS 150.00' in body,'legacy source shows effective remaining payable after actual partial cash payment')
body=owner.request('/finance.php?page=providers')[2];row=next(x for x in re.findall(r'<tr>(.*?)</tr>',body,re.S) if '#'+str(fixture['legacy'])+' ' in x);check('Part Paid' in row and '₵400.00' in row and '₵250.00' in row and 'Paid by MoMo' not in row,'provider register uses corrected cost and partial balance without inventing payment method');check('Linked Position / Correct Record' in row,'corrected legacy provider remains linked for further settlement or follow-up');check('Synthetic completed separately funded provider cash payment' not in admin.request('/finance.php?page=activity')[2],'generic audit hides linked legacy provider private payment evidence from other administrators')
old='/finance.php?page=providers&form=pay_cost&type=request&id='+str(fixture['legacy']);redirect=owner.request(old);check(redirect[0]==302 and 'area=purchases' in redirect[1]['Location'],'old full-payment form routes to effective legacy provider balance')

fixture.update(case=cid,stock_case=sid);(ROOT/'.local-setup/purchase-correction-http-fixture.json').write_text(json.dumps(fixture),encoding='utf-8');print('TOTAL '+str(count)+' purchase correction HTTP assertions passed.')
