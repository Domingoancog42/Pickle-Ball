// Run with: node tools/check-admin-sidebar.cjs
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const root=path.join(__dirname,'..'),listeners={},badges=new Map();
for(const id of ['#notification-count','#nav-pending','#nav-pending-payments'])badges.set(id,{hidden:true,setAttribute(name,value){this[name]=value;}});
const document={
  hidden:false,readyState:'loading',currentScript:{src:'http://localhost/assets/js/live-updates.js'},
  querySelector:selector=>selector==='#admin-config'?{textContent:JSON.stringify({page:'courts'})}:badges.get(selector),
  addEventListener(name,callback){(listeners[name]??=[]).push(callback);}
};
const window={addEventListener(){}},context=vm.createContext({window,document,URL,Intl,navigator:{onLine:true},setTimeout(){},clearTimeout(){},setInterval(){}});
for(const file of ['admin.js','live-updates.js'])vm.runInContext(fs.readFileSync(path.join(root,'assets/js',file),'utf8'),context);
const A=window.Admin;let payload,calls=0;
A.api=async()=>{calls++;return payload;};
const load=async(unread,reservations,payments)=>{
  payload={unread,pending_reservations:reservations,pending_payments:payments,items:[]};
  await A.loadNotifications();
};
const check=(id,text,hidden)=>{assert.equal(String(badges.get(id).textContent),text);assert.equal(badges.get(id).hidden,hidden);};
(async()=>{
  await load(2,3,4);
  check('#notification-count','2',false);check('#nav-pending','3',false);check('#nav-pending-payments','4',false);
  assert.equal(badges.get('#nav-pending-payments')['aria-label'],'4 payments awaiting verification');
  await load(0,3,4);
  check('#notification-count','0',true);check('#nav-pending','3',false);check('#nav-pending-payments','4',false);
  await load(100,101,999);
  for(const id of badges.keys())check(id,'99+',false);
  assert.equal(badges.get('#nav-pending-payments').title,'999 payments awaiting verification');
  await load(0,0,0);
  for(const id of badges.keys())check(id,'0',true);
  payload={unread:0,pending_reservations:1,pending_payments:2,items:[]};
  A.icons=()=>{};A.navigate=async()=>{};
  await listeners.DOMContentLoaded[0]();await Promise.resolve();
  check('#nav-pending','1',false);check('#nav-pending-payments','2',false);
  for(const change of ['payments','reservations','notifications']){
    const before=calls;payload.pending_payments++;
    listeners['pikol:live-update'][0]({detail:{audience:'admin',changes:[change]}});
    await new Promise(resolve=>setImmediate(resolve));
    assert.equal(calls,before+1,`${change} must refresh sidebar counts`);
    check('#nav-pending-payments',String(payload.pending_payments),false);
  }
  console.log('PASS: direct non-dashboard startup, independent unread/pending counts, zero states, 99+ cap, accessible totals and live payment/reservation updates.');
})().catch(error=>{console.error(error);process.exitCode=1;});
