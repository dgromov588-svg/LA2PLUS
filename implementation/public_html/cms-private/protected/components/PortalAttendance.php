<?php
/** Daily free rewards. Only server-recorded cumulative game seconds count. */
class PortalAttendance
{
    const DAYS=28; const REQUIRED=1800;
    public static function day($now=null)
    {
        $date=(new DateTimeImmutable('@'.($now??time())))->setTimezone(new DateTimeZone('+03:00'));
        return $date->modify('-6 hours -30 minutes')->format('Y-m-d');
    }
    public static function resetAt($now=null)
    {
        $date=(new DateTimeImmutable('@'.($now??time())))->setTimezone(new DateTimeZone('+03:00'));
        $reset=$date->setTime(6,30);if($reset->getTimestamp()<=($now??time()))$reset=$reset->modify('+1 day');return $reset->getTimestamp();
    }
    public static function observe(PortalStore $s,$owner,$gs,array $characters,$now=null)
    {
        $now=$now??time();$day=self::day($now);
        return $s->atomic(function()use($s,$owner,$gs,$characters,$now,$day){
            self::ensure($s,$owner,$gs);self::lock($s,$owner,$gs);
            foreach($characters as $char){
                $id=(int)($char['char_id']??0);$value=max(0,(int)($char['onlinetime']??0));if($id<1)continue;
                $row=$s->query('SELECT * FROM {{portal_attendance_time}} WHERE owner_id=? AND gs_id=? AND char_id=? AND day_date=?',[$owner,$gs,$id,$day])->fetch(PDO::FETCH_ASSOC);
                if(!$row){$s->query('INSERT INTO {{portal_attendance_time}}(owner_id,gs_id,char_id,day_date,last_value,changed_at,earned) VALUES(?,?,?,?,?,?,0)',[$owner,$gs,$id,$day,$value,$now]);continue;}
                if($value!==(int)$row['last_value']){
                    $increase=$value>(int)$row['last_value']?min($value-(int)$row['last_value'],max(0,$now-(int)$row['changed_at'])):0;
                    $earned=min(self::REQUIRED,(int)$row['earned']+$increase);
                    $s->query('UPDATE {{portal_attendance_time}} SET last_value=?,changed_at=?,earned=? WHERE owner_id=? AND gs_id=? AND char_id=? AND day_date=?',[$value,$now,$earned,$owner,$gs,$id,$day]);
                }
            }
            return self::status($s,$owner,$gs,$now);
        });
    }
    private static function ensure(PortalStore $s,$owner,$gs)
    {
        $s->query($s->driver()==='mysql'?'INSERT IGNORE INTO {{portal_attendance}}(owner_id,gs_id,next_day) VALUES(?,?,1)':'INSERT OR IGNORE INTO {{portal_attendance}}(owner_id,gs_id,next_day) VALUES(?,?,1)',[$owner,$gs]);
    }
    private static function lock(PortalStore $s,$owner,$gs)
    {
        return $s->query('SELECT * FROM {{portal_attendance}} WHERE owner_id=? AND gs_id=?'.($s->driver()==='mysql'?' FOR UPDATE':''),[$owner,$gs])->fetch(PDO::FETCH_ASSOC);
    }
    public static function status(PortalStore $s,$owner,$gs,$now=null)
    {
        $now=$now??time();self::ensure($s,$owner,$gs);$row=$s->query('SELECT * FROM {{portal_attendance}} WHERE owner_id=? AND gs_id=?',[$owner,$gs])->fetch(PDO::FETCH_ASSOC);
        $seconds=(int)$s->query('SELECT MAX(earned) FROM {{portal_attendance_time}} WHERE owner_id=? AND gs_id=? AND day_date=?',[$owner,$gs,self::day($now)])->fetchColumn();
        return ['next_day'=>(int)$row['next_day'],'claimed'=>min(self::DAYS,(int)$row['next_day']-1),'seconds'=>$seconds,'required'=>self::REQUIRED,'claimed_today'=>$row['last_claim_date']===self::day($now),'complete'=>(int)$row['next_day']>self::DAYS,'can_claim'=>$seconds>=self::REQUIRED&&$row['last_claim_date']!==self::day($now)&&(int)$row['next_day']<=self::DAYS,'reset_at'=>self::resetAt($now)];
    }
    public static function claim(PortalStore $s,$owner,$actor,$gs,array $rewards,$key,$now=null)
    {
        $now=$now??time();
        return $s->run($actor,$key,'attendance.claim',[$owner,$gs],function()use($s,$owner,$actor,$gs,$rewards,$now){
            self::ensure($s,$owner,$gs);$row=self::lock($s,$owner,$gs);$status=self::status($s,$owner,$gs,$now);
            if($status['complete'])throw new RuntimeException('Календарь пройден: все 28 наград получены.');
            if(!$status['can_claim'])throw new RuntimeException($status['claimed_today']?'Сегодня награда уже получена. Следующая — после 06:30 (GMT+3).':'Нужно 30 минут подтверждённого игрового времени сегодня.');
            $day=$status['next_day'];$items=$rewards[$day]??[];if(!$items)throw new RuntimeException('Награда этого дня ещё не настроена.');
            foreach($items as $i=>$item)$s->grantItem($actor,$gs,['item_id'=>(int)$item['item_id'],'quantity'=>(int)($item['count']??1),'enchant'=>(int)($item['enchant']??0),'name'=>(string)($item['name']??'Предмет #'.$item['item_id'])],'attendance:'.$owner.':'.$gs.':'.$day.':'.$i);
            $s->query('UPDATE {{portal_attendance}} SET next_day=?,last_claim_date=? WHERE owner_id=? AND gs_id=?',[$day+1,self::day($now),$owner,$gs]);
            $s->audit($actor,'attendance.claimed',$owner.':'.$gs.':'.$day,['day'=>$day,'date'=>self::day($now)]);
            return ['day'=>$day,'message'=>'Награда за день '.$day.' получена на склад кабинета.'];
        });
    }
}
