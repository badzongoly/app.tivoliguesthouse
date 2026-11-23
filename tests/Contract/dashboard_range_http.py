import datetime,http.cookiejar,json,pathlib,re,urllib.request,urllib.parse
root=pathlib.Path(__file__).resolve().parents[2]
fixture=json.loads((root/'.local-setup/activity-visibility-fixture.json').read_text(encoding='utf-8'))
base='http://127.0.0.1:8099';browser=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));count=0
def get(path,data=None):
 r=browser.open(urllib.request.Request(base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None),timeout=45)
 assert r.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified server'
 body=r.read().decode('utf-8');assert not re.search('Fatal error|Parse error|Warning:|SQLSTATE',body)
 return r,body
def check(ok,label):
 global count
 assert ok,label
 count+=1;print('PASS '+label,flush=True)
body=get('/auth/login.php')[1];csrf=re.search(r'name="csrf" value="([^"]+)"',body)[1];get('/auth/login.php',dict(csrf=csrf,email=fixture['email'],password='Synthetic Visibility Passphrase 123!'))
for query in ['', '?range=30&days=90&role=admin']:
 r,body=get('/dashboard.php'+query)
 check('id="range14"' not in body and 'id="range30"' not in body,'Non-admin has only seven-day range controls '+query)
 labels=json.loads(re.search(r'const allLabels\s*=.*?(\[[^;]*\]);',body)[1])
 values=json.loads(re.search(r'const allRev\s*=.*?(\[[^;]*\]);',body)[1])
 today=datetime.datetime.strptime(r.headers['Date'],'%a, %d %b %Y %H:%M:%S GMT').date()
 expected=[(today-datetime.timedelta(days=i)).strftime('%d/%m/%Y') for i in range(6,-1,-1)]
 check(labels==expected and len(values)==7,'Only latest seven UTC calendar days are serialized '+query)
 check(re.search(r'const nights14\s*=.*?\[\];',body) and re.search(r'const nights30\s*=.*?\[\];',body),'Non-admin receives no 14/30-day room series '+query)
 check('Daily Revenue (7 days)' in body and 'Booked Nights by Room (last 7 days)' in body,'Graph headings show seven days '+query)
 check('const allowedRanges = [7];' in body and 'render(7);' in body,'Client starts at and clamps to permitted range '+query)
print('TOTAL '+str(count)+' dashboard range assertions passed')
