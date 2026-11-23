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

f=json.loads((ROOT/'.local-setup/occupancy-ui-fixture.json').read_text(encoding='utf-8'));n=0
def check(ok,label):
 global n
 assert ok,label
 n+=1;print('PASS '+label,flush=True)
staff=Browser();staff.login(f['email'],'Synthetic Visibility Passphrase 123!');owner=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
detail='/occupancy.php?booking_id='+str(f['booking']);body=staff.request(detail)[2]
nav=re.search(r'<nav[^>]*aria-label="Stays"[^>]*>(.*?)</nav>',body,re.S)[1]
check(re.findall(r'<a[^>]*aria-current="page"[^>]*>(.*?)</a>',nav,re.S)==['Guest Stay Account'],'Guest details select the Guest Stay Account parent')
sub=re.search(r'<nav[^>]*aria-label="Guest account sections"[^>]*>(.*?)</nav>',body,re.S)[1]
check('aria-current="page">Bill</a>' in sub and '>Stay Activity</a>' in sub,'Default guest bill has a current tab and scoped Stay Activity label')
for form,label in [('payment','Record Payment'),('extra','Add Extra'),('checkout','Check Out'),('report_departure','Report Unauthorised Departure')]:
 code,_,body=staff.request(detail+'&form='+form)
 check(code==200 and '<form method="post"' in body and 'id="stay-action"' in body,form+' retains its action form')
 check('>'+label+'</a>' not in body,form+' does not repeat its current entry button')
code,_,body=staff.request('/occupancy.php?form=checkin&room_id='+str(f['ready_room']))
check(code==200 and 'name="action" value="checkin"' in body,'Ready room retains protected check-in form')
room=re.search(r'<article[^>]*data-room-id="'+str(f['ready_room'])+'"[^>]*>(.*?)</article>',body,re.S)[1]
check('>Check In</a>' not in room,'Selected check-in room does not repeat its entry action')
body=owner.request('/occupancy.php?booking_id='+str(f['incident_booking']))[2]
check(body.count('>Recover Outstanding</a>')==1,'Incident guest has one outstanding-collection entry')
clean='/occupancy.php?section=housekeeping&id='+str(f['attempt'])+'&form=clean';body=staff.request(clean)[2]
check('data-clean-step=' in body and 'name="operation_key" value=' in body,'Captured cleaning wizard and operation key remain available')
check('>Continue Cleaning</a>' not in body and '>Back To Room Board</a>' not in body,'Cleaning form has no repeated start or Room Board link')
check(body.count('>Cleaning History</a>')==1,'One cleaning-history entry in preparation navigation')
original='/occupancy.php?section=housekeeping&id='+str(f['source_attempt'])+'&from_stay='+str(f['source_booking']);body=staff.request(original)[2]
check('>Back To Guest Stay Account</a>' in body and 'booking_id='+str(f['source_booking']) in body,'Original cleaning record returns to its actual source stay')
check('name="action"' not in body and 'This is the cleaning record linked to Stay' in body,'Original cleaning record remains read-only')
rooms=Browser();rooms.login(f['rooms_email'],'Synthetic Rooms Only Passphrase 123!');body=rooms.request('/occupancy.php?section=housekeeping')[2]
check('>Guest Stay Account</a>' not in body and '>Back To Room Board</a>' not in body and '>Room Board</a>' in body,'Room-only staff keep one permitted board entry without guest access')
print('TOTAL '+str(n)+' occupancy UX HTTP assertions passed')
