"""Only the explicitly marked loopback synthetic server; no normal app credentials."""
import datetime,html,pathlib,urllib.request,urllib.parse,urllib.error,http.cookiejar,re,secrets,json
BASE='http://127.0.0.1:8099';ROOT=pathlib.Path(__file__).resolve().parents[2]
fixture=json.loads((ROOT/'.local-setup/stays-http-fixture.json').read_text())
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
class Browser:
    def __init__(self):self.http=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(self,path,data=None,files=None):
        headers={};encoded=urllib.parse.urlencode(data).encode() if data is not None else None
        if files:
            boundary='SyntheticBoundary'+secrets.token_hex(12);parts=[]
            for key,value in (data or {}).items():parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+key+'"\r\n\r\n'+str(value)+'\r\n').encode())
            for key,(filename,mime,content) in files.items():parts.extend([('--'+boundary+'\r\nContent-Disposition: form-data; name="'+key+'"; filename="'+filename+'"\r\nContent-Type: '+mime+'\r\n\r\n').encode(),content,b'\r\n'])
            parts.append(('--'+boundary+'--\r\n').encode());encoded=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
        try:r=self.http.open(urllib.request.Request(BASE+path,data=encoded,headers=headers),timeout=20)
        except urllib.error.HTTPError as e:r=e
        body=r.read().decode('utf-8','replace')
        if r.headers.get('X-Tivoli-Test-Mode')!='synthetic':raise RuntimeError('Unverified HTTP target')
        if re.search(r'Fatal error|Warning:|Parse error|SQLSTATE\[',body):raise RuntimeError('PHP error in HTTP response')
        return r.code,r.headers,body
    def fields(self,path):
        body=self.request(path)[2]
        return dict(re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',body))
    def login(self,email,password):return self.request('/auth/login.php',{**self.fields('/auth/login.php'),'email':email,'password':password})
def house_fields(browser,path,action):
    body=browser.request(path)[2]
    forms=re.findall(r'<form\b[^>]*>(.*?)</form>',body,re.S)
    form=next(x for x in forms if 'name="action" value="'+action+'"' in x)
    return {k:html.unescape(v) for k,v in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',form)}

count=0
def check(ok,message):
    global count
    if not ok:raise RuntimeError('FAIL '+message)
    count+=1;print('PASS '+message,flush=True)
admin=Browser();clerk=Browser();anonymous=Browser()
check(anonymous.request('/occupancy.php')[0]==302,'unauthenticated occupancy request redirects')
check(admin.login('admin@example.invalid','Synthetic Admin Passphrase 123!')[0]==302,'synthetic admin login')
check(clerk.login(fixture['clerk_email'],fixture['clerk_password'])[0]==302,'synthetic cashier login')
room=fixture['room_id'];old=fixture['booking_id']
check(admin.request('/bookings.php')[1].get('Location','').endswith('occupancy.php'),'old bookings entry routes to new system')
room_form=admin.request(f'/occupancy.php?section=rooms&form=room_save&id={room}')[2]
check('<select name="type" required' in room_form and '<input type="text" name="type"' not in room_form,'Room Type is a required dropdown')
comment=re.search(r'<label[^>]*>Comment \(Optional\)<textarea name="reason"([^>]*)>',room_form)
check(comment is not None and 'required' not in comment.group(1),'room setup Comment is optional')
actions=re.search(r'<div class="stay-actions stay-form-actions">(.*?)</div>',room_form,re.S)
check(actions is not None and 'Record Action' in actions.group(1) and '>Cancel</a>' in actions.group(1),'Record Action and Cancel share one action row')

for path in ['/checkin.php','/checkout.php','/extras.php','/stock.php','/rooms.php','/admin_recovery.php']:
    check(admin.request(path,{'action':'legacy_probe'})[0]==409,'old writer denied '+path)
check(admin.request('/occupancy.php?section=extras')[0]==200,'extras catalogue available through main menu')
check('View Only' in clerk.request('/occupancy.php?section=extras')[2],'clerk extras catalogue is read only')
check(clerk.request('/occupancy.php',{'action':'checkin'})[0]==403,'missing CSRF denied')
path=f'/occupancy.php?form=checkin&room_id={room}'
body=clerk.request(path)[2]
check('I Have Received Payment For The Room' in body and 'I Have Checked The Room' in body,'check-in attestation wording')
payload={**clerk.fields(path),'action':'checkin','room_id':room,'guest_name':'Synthetic HTTP Guest','phone':'0000000000','gender':'Female','nationality':'Ghana','stay_type':'short','amount':'150.00','expected_rate':'150.00','payment_type':'cash','identity_verified':'1','payment_attested':'1','room_checked':'1'}
status,headers,_=clerk.request('/occupancy.php',payload);check(status==302,'new check-in through HTTP')
id=int(re.search(r'booking_id=(\d+)',headers['Location']).group(1));detail=f'/occupancy.php?booking_id={id}'
body=clerk.request(detail)[2]
check(body.index('Mobile')<body.index('Stay Bill'),'guest identity appears before bill')
check('Guest identity verified' not in body or 'Still In House' in body,'guest status visible')
check(clerk.request('/occupancy.php',payload)[0]==302,'HTTP check-in replay safe')
extra_path=detail+'&form=extra'
check(clerk.request(extra_path,{**clerk.fields(extra_path),'action':'extra','booking_id':id,'extra_id':fixture['extra_id'],'qty':'1','settlement':'room','method':'cash','reference':''})[0]==302,'room extra creates a checkout balance after paid arrival')
payment_path=detail+'&form=payment'
payment={**clerk.fields(payment_path),'action':'payment','booking_id':id,'amount':'1.00','method':'cash','reference':'','name':'Forged Cashier'}
check(clerk.request(payment_path,payment)[0]==302,'cashier payment posted')
message=clerk.request(detail)[2]
check('Payment recorded by Synthetic occupancy cashier.' in message and 'Payment recorded by Forged Cashier.' not in message,'payment confirmation uses authenticated full name')
check('Synthetic occupancy cashier' in clerk.request(detail+'&tab=payments')[2],'receipt displays cashier full name')
checkout=f'{detail}&form=checkout';body=clerk.request(checkout)[2]
check('Clear All Outstanding' in body and '>Confirm Departure<' not in body,'unpaid checkout hides confirmation')
check('outstanding-balance handover' not in body,'removed authority field stays absent')
pay=f'{detail}&form=clear_balance';p={**clerk.fields(pay),'action':'clear_balance','booking_id':id,'method':'cash','reference':''}
status,h,_=clerk.request('/occupancy.php',p);check(status==302 and 'confirm=' in h.get('Location',''),'full payment first opens confirmation')
confirm_path=h['Location'].replace(BASE,'').split('#')[0];body=clerk.request(confirm_path)[2]
check('Record Full Payment?' in body and 'Synthetic HTTP Guest' in body,'confirmation names guest and action')
check('Balance Due' in clerk.request(checkout)[2] and '>Confirm Departure<' not in clerk.request(checkout)[2],'payment prompt does not post receipt')
confirm=clerk.fields(confirm_path);status,h,_=clerk.request('/occupancy.php',confirm)
check(status==302 and 'form=checkout' in h.get('Location',''),'full payment returns to checkout form')
body=clerk.request(checkout)[2];check('>Confirm Departure<' in body,'settlement enables departure')
out={**clerk.fields(checkout),'action':'checkout','booking_id':id,'extras_checked':'1','keys_returned':'1','reason':''}
status,h,_=clerk.request('/occupancy.php',out);check(status==302 and 'confirm=' in h.get('Location',''),'departure first opens confirmation')
conf=h['Location'].replace(BASE,'').split('#')[0];check('Still In House' in clerk.request(detail)[2],'departure prompt does not change occupancy')
status,_,_=clerk.request('/occupancy.php',clerk.fields(conf));check(status==302,'confirmed departure recorded')
body=clerk.request(detail)[2];check('>Record Payment<' not in body and 'Still In House' not in body,'departed account has no payment action')
def card(browser):
    page=browser.request('/occupancy.php')[2]
    match=re.search(r'<article[^>]*data-room-id="'+str(room)+r'"[^>]*>.*?</article>',page,re.S)
    if not match: raise RuntimeError('Synthetic room card missing')
    return match.group(0)
check('Cleaning Complete' in card(admin),'departed room offers Cleaning Complete on board')
check('housekeeping.php' in card(clerk),'clerk receives authorized housekeeping action')
old_clean=f'/occupancy.php?form=clean&room_id={room}'
check(clerk.request('/occupancy.php',{**clerk.fields(detail),'action':'clean','operation_key':secrets.token_hex(16),'room_id':room})[0]==409,'obsolete immediate cleaning rejected')
check(admin.request(old_clean)[0]==302,'old cleaning URL redirects')
house='/housekeeping.php?room_id='+str(room)
status,h,_=clerk.request(house,house_fields(clerk,house,'start'));check(status==302,'staff starts named cleaning attempt')
attempt=int(re.search(r'id=(\d+)',h['Location'])[1]);clean_path='/housekeeping.php?id='+str(attempt)+'&form=clean'
clean_body=clerk.request(clean_path)[2]
check('Submitting records your identity, time and completed checklist.' not in clean_body,'removed cleaning submission message stays absent')
check('data-cleaning-form' in clean_body and clean_body.count('data-clean-step=')==5,'cleaning form has all five wizard stages')
checks=re.findall(r'name="done\[\]" value="([^"]+)"',clean_body)
check(len(checks)>=29 and 'Never mix cleaning chemicals' in clean_body,'supplied checklist and chemical safety rule included')
fields=house_fields(clerk,clean_path,'progress')
check(clerk.request(clean_path,fields|dict(direction='next',done='[]'))[0]==400,'server rejects missing cleaning checks')
for step in range(5):
    fields=house_fields(clerk,clean_path,'progress')|dict(direction='complete' if step==4 else 'next',done=json.dumps(checks),verified='1')
    assert clerk.request(clean_path,fields)[0]==302
check('Awaiting Inspection' in clerk.request('/housekeeping.php?id='+str(attempt))[2],'complete cleaning works without optional notes')
check(clerk.request('/cleaning-settings.php')[0]==403,'clerk cannot access admin cleaning settings')
settings_path='/cleaning-settings.php'
settings_fields=admin.fields(settings_path)
add={**settings_fields,'change':'add','section_id':'1','item_text':'Synthetic HTTP cleaning checklist item','operation_key':secrets.token_hex(16)}
check(admin.request(settings_path,add)[0]==302,'administrator adds cleaning item under Settings')
settings_body=admin.request(settings_path)[2]
check('Synthetic HTTP cleaning checklist item' in settings_body,'new item appears in settings')
entry=re.search(r'<p>Synthetic HTTP cleaning checklist item</p><form method="post">(.*?)</form>',settings_body,re.S)
remove=dict(re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',entry.group(1)))
check(admin.request(settings_path,remove)[0]==302,'administrator removes cleaning item without deleting history')
check('Inspect &amp; Release Room' in card(admin) and 'form=checkin' not in card(admin),'cleaned room awaits inspection rather than sale')
inspect_path='/housekeeping.php?id='+str(attempt)+'&form=inspect'
inspection_body=admin.request(inspect_path)[2]
inspection_checks=re.findall(r'name="result_([^"]+)" value="pass"',inspection_body)
check(len(inspection_checks)==6 and 'Inspect &amp; Release Room' in inspection_body,'inspection form contains all six owner checks')
check('room has been blocked from sale' in inspection_body and 'Needs Attention' in inspection_body,'inspection retains fault and failed-item evidence')
inspection_fields=house_fields(admin,inspect_path,'inspect')
check(admin.request(inspect_path,inspection_fields|dict(results='{}',verified='1',decision='release'))[0]==400,'server rejects incomplete inspection checks')
check(admin.request(inspect_path,inspection_fields|dict(results=json.dumps(dict.fromkeys(inspection_checks,'pass')),verified='1',decision='release'))[0]==302,'different inspector releases complete checklist without optional notes')
inspection_settings='/cleaning-settings.php?type=inspection'
check(clerk.request(inspection_settings)[0]==403,'clerk cannot access inspection settings')
inspection_add={**admin.fields(inspection_settings),'change':'add','section_id':'1','item_text':'Synthetic HTTP inspection item','operation_key':secrets.token_hex(16)}
check(admin.request(inspection_settings,inspection_add)[0]==302,'administrator adds inspection item under Settings')
inspection_settings_body=admin.request(inspection_settings)[2]
check('Inspection Checklist' in inspection_settings_body and 'Synthetic HTTP inspection item' in inspection_settings_body,'inspection settings show configured item')
inspection_entry=re.search(r'<p>Synthetic HTTP inspection item</p><form method="post">(.*?)</form>',inspection_settings_body,re.S)
inspection_remove=dict(re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"',inspection_entry.group(1)))
check(admin.request(inspection_settings,inspection_remove)[0]==302,'administrator removes inspection item for future inspections')
check('Check In' in card(admin),'released room is ready for check-in')
for browser in [admin,clerk]:
    directory=browser.request('/occupancy.php?section=folios')[2]
    check('Guest Stay Account' in directory and 'Stay #'+str(id) in directory,'Guest Stay Account directory includes departed stay')
    activity=browser.request('/occupancy.php?section=activity')[2]
    check('Operational Activity' in activity and 'Cleaning Completed' in activity and 'Housekeeping Inspection Recorded' in activity,'workspace Activity includes cleaning and inspection events')
    nav=re.search(r'<nav class="app-section-nav" aria-label="Stays">(.*?)</nav>',activity,re.S).group(1)
    check(re.findall(r'>([^<>]+)</a>',nav)==['Room Board','Guest Stay Account','Departure Incidents','Activity'],'single stay navigation has requested tab order')
    check('<th>Check-In (UTC)</th>' in directory and '<th>Check-Out (UTC)</th>' in directory and '<th>Balance</th>' not in directory,'account table has dates and no balance column')
    check('Recent Check-Outs' not in directory and 'Legacy noon-cutoff billing.' not in browser.request('/occupancy.php')[2],'duplicate recent tab and legacy description removed')
    check('Current Week' in directory and 'All Dates' in directory and 'name="start"' in directory,'account date filter defaults and history controls visible')
check(admin.request('/occupancy.php?section=folios&start=2026-02-30&end=2026-03-01')[0]==400,'invalid account filter rejected over HTTP')
check(admin.request('/occupancy.php?section=folios&start=2026-11-08&end=2026-11-02')[0]==400,'reversed account dates rejected over HTTP')
check('Synthetic account pagination' in admin.request('/occupancy.php?section=folios&range=all')[2],'All Dates retains older cancelled accounts')
historical_page=admin.request('/occupancy.php?section=folios&start=2001-01-03&end=2001-01-03')[2]
check('start=2001-01-03&amp;end=2001-01-03' in historical_page and 'Older Guest Stays' in historical_page,'pagination retains selected dates')
check('Guest Stay Account' in admin.request('/occupancy.php?tab=recent')[2] and 'Recent Check-Outs' not in admin.request('/occupancy.php?tab=recent')[2],'old recent URL opens unified accounts')
for tab in ['bill','payments','statement','activity']:
    check(clerk.request(detail+'&tab='+tab)[0]==200,'account section '+tab)
check(clerk.request('/occupancy.php',{'csrf':clerk.fields(detail)['csrf'],'operation_key':secrets.token_hex(16),'action':'payment','booking_id':id,'amount':'1.00','method':'cash'})[0]==400,'forged post-departure payment rejected')
check(clerk.request('/occupancy.php',{'csrf':clerk.fields(detail)['csrf'],'operation_key':secrets.token_hex(16),'action':'extra_save','name':'Forbidden','price':'1.00','reason':'Forbidden fixture'})[0]==403,'clerk cannot forge catalogue write')
status,headers,_=admin.request('/bookings.php?export=csv&room_id='+str(room));check(status==302 and 'export=csv' in headers.get('Location',''),'legacy export routes to authoritative exporter')
path=headers['Location'].replace(BASE,'');status,headers,body=admin.request(path);check(status==200 and 'text/csv' in headers.get('Content-Type','') and '<html' not in body,'CSV streams without layout markup')
check('Stay Type' in body and 'Incidentals' in body and 'short' in body,'CSV includes stay type and authoritative incidental totals')
fault_path=f'/occupancy.php?section=rooms&form=fault&room_id={room}'
fault_form=admin.request(fault_path)[2]
description=re.search(r'Describe Fault \(Optional\)<textarea name="reason"([^>]*)>',fault_form)
check(description is not None and 'required' not in description.group(1),'fault description is labelled and optional')
check('enctype="multipart/form-data"' in fault_form and 'name="fault_file"' in fault_form,'fault form accepts optional attachment')
fault_data={**admin.fields(fault_path),'action':'fault','room_id':room,'owner':'Synthetic maintenance','priority':'Routine'}
check(admin.request(fault_path,{**fault_data,'operation_key':secrets.token_hex(16)})[0]==302,'fault can be recorded without description or file')
check(admin.request(fault_path,{**fault_data,'operation_key':secrets.token_hex(16)},{'fault_file':('wrong.pdf','application/pdf',b'<html>wrong file</html>')})[0]==400,'mismatched attachment is rejected over HTTP')
pdf=b'%PDF-1.4\nSynthetic HTTP fault evidence\n%%EOF'
check(admin.request(fault_path,{**fault_data,'operation_key':secrets.token_hex(16)},{'fault_file':('fault.pdf','application/pdf',pdf)})[0]==302,'valid fault attachment uploads without description')
fault_log=admin.request('/occupancy.php?section=rooms&tab=faults')[2]
attachment=re.search(r'href="([^"]*fault-attachment.php\?fault_id=\d+)"',fault_log)
check(attachment is not None,'fault log exposes protected download link')
attachment_path=attachment.group(1).replace(BASE,'')
status,headers,download=admin.request(attachment_path)
check(status==200 and download.encode()==pdf and 'attachment;' in headers.get('Content-Disposition','') and 'nosniff' in headers.get('X-Content-Type-Options',''),'download returns exact file with safe headers')
check(clerk.request(attachment_path)[0]==403,'clerk attachment download denied')
check(anonymous.request(attachment_path)[0]==302,'anonymous attachment download redirects to login')
try:
    private_response=urllib.request.urlopen(BASE+'/storage/private/room-faults-test/anything.enc',timeout=10);private_status=private_response.code;private_response.close()
except urllib.error.HTTPError as private_error:private_status=private_error.code
check(private_status in [403,404],'private attachment storage is not web accessible')
repair_ids=re.findall(r'form=repair&amp;id=(\d+)',fault_log)
repair_path=f'/occupancy.php?section=rooms&form=repair&id={repair_ids[0]}'
repair_body=admin.request(repair_path)[2]
repair_comment=re.search(r'Comment \(Optional\)<textarea name="reason"([^>]*)>',repair_body)
check(repair_comment is not None and 'required' not in repair_comment.group(1),'repair Comment is optional')
check('Evidence (Optional' in repair_body and 'name="fault_file"' in repair_body,'repair form includes optional evidence upload')
repair_pdf=b'%PDF-1.4\nSynthetic HTTP repair evidence\n%%EOF'
repair_data={**admin.fields(repair_path),'action':'repair'}
check(admin.request(repair_path,repair_data,{'fault_file':('repair.pdf','application/pdf',repair_pdf)})[0]==302,'repair uploads evidence with empty Comment')
repaired_log=admin.request('/occupancy.php?section=rooms&tab=faults')[2]
repair_link=re.search(r'href="([^"]*fault-attachment.php\?fault_id=\d+&amp;kind=repair)"',repaired_log)
check(repair_link is not None,'fault log provides separate repair evidence link')
repair_download=html.unescape(repair_link.group(1)).replace(BASE,'')
check(admin.request(repair_download)[2].encode()==repair_pdf,'repair evidence downloads exact bytes')
check(clerk.request(repair_download)[0]==403,'clerk repair evidence download denied')
plain_repair=f'/occupancy.php?section=rooms&form=repair&id={repair_ids[1]}'
check(admin.request(plain_repair,{**admin.fields(plain_repair),'action':'repair'})[0]==302,'repair can omit Comment and evidence')
for path in ['/occupancy.php','/occupancy.php?section=rooms&tab=faults',detail,detail+'&tab=statement']:
    check(not re.search(r'\u00c2[\u0080-\u00bf]|\u00c3[\u0080-\u00bf]|\ufffd',admin.request(path)[2]),'no broken UTF-8 punctuation in '+path)
room_audit_path=f'/occupancy.php?section=rooms&form=room_audit&room_id={room}'
room_audit_form=admin.request(room_audit_path)[2]
owner_field=re.search(r'<input type="text" name="owner"[^>]*>',room_audit_form).group(0)
comment_field=re.search(r'<textarea name="reason"[^>]*>',room_audit_form).group(0)
check('required' not in owner_field and 'required' not in comment_field and 'Comment (Optional)' in room_audit_form,'room audit owner and Comment are optional')
check(admin.request(room_audit_path,{**admin.fields(room_audit_path),'action':'room_audit','room_id':room,'result':'pass'})[0]==302,'room audit saves without optional fields')
check('Inspect &amp; Release' not in admin.request('/occupancy.php?section=rooms&tab=faults')[2],'fault log has no separate repair inspection action')
check(clerk.request('/occupancy.php?section=areas')[0]==403,'area audit register denies clerk')
area_path='/occupancy.php?section=areas&form=area_audit'
area_form=admin.request(area_path)[2]
check('Weekly Checks' not in area_form and '<select name="area_id" required' in area_form and '<select name="result" required' in area_form,'area audit mirrors room audit with Area and overall Result')
today=datetime.datetime.now(datetime.timezone.utc).date().isoformat()
area_id=re.search(r'<select name="area_id" required[^>]*>.*?<option value="([^"]+)"',area_form,re.S).group(1)
area_data={**admin.fields(area_path),'action':'area_audit','area_id':area_id,'result':'unsafe'}
check(clerk.request(area_path,{**area_data,'csrf':clerk.fields('/occupancy.php')['csrf'],'operation_key':secrets.token_hex(16)})[0]==403,'forged clerk area audit denied')
check(admin.request(area_path,area_data)[0]==302,'weekly area audit records overall result')
area_register=admin.request('/occupancy.php?section=areas')[2]
check('Unsafe' in area_register and 'Needs Assignment' in area_register,'unsafe area audit opens visible corrective follow-up')
follow_match=re.search(r'href="([^"]*form=area_followup[^"]*)"',area_register)
follow_path=html.unescape(follow_match.group(1)).replace(BASE,'')
code,headers,_=admin.request(follow_path)
check(code==302 and 'weekly-audits.php?id=' in headers.get('Location',''),'older managed area follow-up opens authoritative task')
task_path='/weekly-audits.php?'+urllib.parse.urlsplit(headers['Location']).query
task=admin.request(task_path)[2]
check('Assign Follow-Up' in task and 'Original Audit' in task,'source-linked task offers named assignment and original evidence')
history=task
check('form=release_fault' not in history,'removed release workflow is not reintroduced')
settings_path='/cleaning-settings.php?type=areas'
check(admin.request(settings_path)[0]==200 and 'Audit Areas' in admin.request(settings_path)[2],'admin area settings available')
check(clerk.request(settings_path)[0]==403,'clerk area settings denied')
settings_fields=admin.fields(settings_path)
check(admin.request(settings_path,{**settings_fields,'area_id':'','name':'Synthetic HTTP Area '+secrets.token_hex(4),'active':'1','operation_key':secrets.token_hex(16)})[0]==302,'admin adds an area through Settings')
check('Synthetic HTTP Area' in admin.request(area_path)[2],'newly configured area appears in audit dropdown')
print(f'TOTAL {count} occupancy HTTP assertions passed.' ,flush=True)
