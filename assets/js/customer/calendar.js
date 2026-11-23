(() => {
  const P=window.Player;
  P.views.calendar=async()=>{
    P.$('#player-main').innerHTML=P.heading('YOUR COURT TIME, AT A GLANCE','Booking Calendar','Only your own reservations are shown. All court times use Asia/Manila.',P.button('book','Book a court','plus'))+'<section class="panel"><div id="player-calendar" class="admin-calendar"></div><div class="calendar-guide"><span>Teal · Confirmed</span><span>Gold · Pending</span><span>Green · Completed</span><span>Red · Cancelled</span></div></section>';
    P.calendar=new FullCalendar.Calendar(P.$('#player-calendar'),{initialView:innerWidth<768?'listWeek':'dayGridMonth',initialDate:P.routeParams.date||undefined,timeZone:'Asia/Manila',firstDay:1,height:'auto',headerToolbar:{left:'prev,next today',center:'title',right:'dayGridMonth,timeGridWeek,timeGridDay,listWeek'},dayMaxEvents:3,events:async(info,success,failure)=>{try{success(await P.api('calendar-events',{start:info.startStr.slice(0,10),end:info.endStr.slice(0,10)}));}catch(error){failure(error);P.toast(error.message,'error');}},eventClick:info=>P.showReservation(info.event.extendedProps.booking_id).catch(e=>P.toast(e.message,'error')),dateClick:info=>{if(info.dateStr.slice(0,10)>=P.today())P.go('book');},editable:false});P.calendar.render();P.cleanup.push(()=>{P.calendar?.destroy();P.calendar=null;});
  };
  P.actions['view-schedule']=button=>P.go('calendar',{date:button.dataset.date});
})();
