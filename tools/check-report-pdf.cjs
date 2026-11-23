// Run with: node tools/check-report-pdf.cjs (no database or browser required).
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=path.join(__dirname,'..'),{jsPDF}=require('../assets/vendor/jspdf.umd.min.js');
require('../assets/vendor/jspdf.plugin.autotable.min.js').applyPlugin(jsPDF);
let saved,search='',fail=false,loads=0;
function Pdf(options){const doc=new jsPDF(options);doc.save=async filename=>{saved={doc,filename};};return doc;}Pdf.API=jsPDF.API;
const A={views:{},actions:{},filters:{},cache:{},config:{user:{full_name:'QA Administrator'}},e:String,icons(){},
  money:value=>new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP',maximumFractionDigits:2,minimumFractionDigits:0}).format(Number(value)||0),
  $:selector=>selector==='#club-brand-name'?{textContent:'Pikol Castle'}:{value:search}
};
const context=vm.createContext({window:{Admin:A,jspdf:{jsPDF:Pdf}},Intl,Date,
  fetch:async url=>{loads++;return {ok:!fail,blob:async()=>({base64:fs.readFileSync(path.join(root,'assets',url.includes('logo.png')?'images':'vendor',path.basename(url))).toString('base64')})};},
  FileReader:class{readAsDataURL(blob){this.result='data:application/octet-stream;base64,'+blob.base64;this.onload();}}
});
for(const file of ['report-types.js','report-pdf.js'])vm.runInContext(fs.readFileSync(path.join(root,'assets/js',file),'utf8'),context);
const button={innerHTML:'Export PDF',disabled:false,setAttribute(){},removeAttribute(){}};
const report={type:'bookings',title:'Booking Reservation Report',from:'2026-11-01',to:'2026-11-21',courtLabel:'Court 1',note:'Reservations by scheduled booking date.',
  columns:{reference_no:'Booking Reference',full_name:'Customer Name',court_name:'Court',booking_date:'Booking Date',time_slot:'Time Slot',duration:'Duration',amount:'Amount',status:'Status'},
  items:Array.from({length:90},(_,i)=>({reference_no:'PC-QA-'+String(i).padStart(4,'0'),full_name:i===0?'José Peña':'Player '+i,court_name:'Court 1',booking_date:'2026-11-21',time_slot:'10:00 - 11:00',duration:'1 hr',amount:220,status:'Confirmed'}))
};
(async()=>{
  A.cache.report=report;fail=true;
  await assert.rejects(()=>A.actions['export-pdf-report'](button),/font or logo could not be loaded/);assert.equal(button.disabled,false);
  fail=false;await A.actions['export-pdf-report'](button);
  assert.equal(saved.filename,'pikol-bookings-2026-11-01-2026-11-21.pdf');
  assert(saved.doc.getNumberOfPages()>1,'All rows must export across multiple pages');
  assert.equal(saved.doc.lastAutoTable.body.length,90);assert.equal(saved.doc.lastAutoTable.columns.length,8);
  assert.equal(saved.doc.lastAutoTable.body[0].cells[1].raw,'José Peña');
  assert.equal(saved.doc.lastAutoTable.body[0].cells[6].raw,A.money(220));
  assert.equal(saved.doc.lastAutoTable.head[0].cells[0].styles.fillColor.join(','),'22,72,79');
  assert.equal(saved.doc.lastAutoTable.settings.showHead,'everyPage');
  assert(saved.doc.output().startsWith('%PDF-'),'A real PDF must be generated');
  const loaded=loads;search='Peña';await A.actions['export-pdf-report'](button);
  assert.equal(saved.doc.lastAutoTable.body.length,1);assert.equal(loads,loaded,'Assets should be reused');
  search='no matching record';await A.actions['export-pdf-report'](button);
  assert.equal(saved.doc.lastAutoTable.body[0].cells[0].colSpan,8);assert.equal(saved.doc.getNumberOfPages(),1);
  search='';A.cache.report={...report,type:'cancellations',title:'Cancellation & No-show Report',columns:{reference_no:'Booking Reference',reason:'Reason'},items:[{reference_no:'PC-QA-LONG',reason:'Long cancellation reason. '.repeat(300)}]};
  await A.actions['export-pdf-report'](button);assert(saved.doc.getNumberOfPages()>1,'Long reasons must continue across pages');
  assert.equal(button.disabled,false);assert.equal(button.innerHTML,'Export PDF');
  console.log('PASS: real PDF generation, matching columns/values, accented names/peso fonts, repeated colored headers, all rows, table search, empty results, long rows, asset failure/retry and button recovery.');
})().catch(error=>{console.error(error);process.exitCode=1;});
