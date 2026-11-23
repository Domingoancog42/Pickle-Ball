<?php
function business_hours_for_date(array $config,string $date): array {
    $holidays=preg_split('/[\s,]+/',trim($config['holiday_dates']??''),-1,PREG_SPLIT_NO_EMPTY);
    $group=in_array($date,$holidays,true)?'holiday':((int)(new DateTimeImmutable($date))->format('N')>=6?'weekend':'weekday');
    return [$config[$group.'_opens_at']??$config['opens_at'],$config[$group.'_closes_at']??$config['closes_at']];
}
// Return operating intervals within a calendar date. Overnight tails belong to
// the previous weekday, including when the current weekday is closed.
function operating_windows(array $court,string $date,array $config,array $schedules): array {
    $windows=[];$day=new DateTimeImmutable($date);
    foreach([-1,0] as $offset){
        $source=$day->modify("$offset day");
        $schedule=$schedules[(int)$source->format('w')]??null;
        if($schedule&&!$schedule['is_open'])continue;
        $minutes=static fn($t)=>(int)substr($t,0,2)*60+(int)substr($t,3,2);
        [$businessOpen,$businessClose]=business_hours_for_date($config,$source->format('Y-m-d'));
        // General Settings are the default; an explicit court weekday schedule
        // can narrow them. Legacy court defaults must not mask new venue hours.
        $a=$minutes($schedule['opens_at']??$businessOpen);$b=$minutes($schedule['closes_at']??$businessClose);
        $x=$minutes($businessOpen);$y=$minutes($businessClose);
        if($b<$a)$b+=1440;if($y<$x)$y+=1440;
        $start=max(0,max($a,$x)+$offset*1440);$end=min(1440,min($b,$y)+$offset*1440);
        if($end>$start)$windows[]=[$start,$end];
    }
    usort($windows,static fn($a,$b)=>$a[0]<=>$b[0]);$merged=[];
    foreach($windows as [$a,$b]){ $i=count($merged)-1;if($i>=0&&$a<=$merged[$i][1])$merged[$i][1]=max($merged[$i][1],$b);else $merged[]=[$a,$b]; }
    return $merged;
}
function court_operating_windows(array $court,string $date): array {
    return operating_windows($court,$date,settings(),array_column(rows('SELECT * FROM court_schedules WHERE court_id=?',[$court['court_id']]),null,'day_of_week'));
}
function slot_in_operating_hours(array $court,string $date,string $start,string $end): bool {
    $minutes=static fn($t)=>(int)substr($t,0,2)*60+(int)substr($t,3,2);
    foreach(court_operating_windows($court,$date) as [$a,$b])if($minutes($start)>=$a&&$minutes($end)<=$b&&$end>$start)return true;
    return false;
}
function valid_booking_end(string $value): string {return in_array($value,['24:00','24:00:00'],true)?'24:00:00':valid_time($value);}
