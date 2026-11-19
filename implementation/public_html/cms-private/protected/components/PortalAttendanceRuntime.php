<?php
class PortalAttendanceRuntime
{
    public static function scope($uid,$gs)
    {
        $group=AccountLinkService::getGroup($uid,true);$accounts=[];
        foreach(AccountLinkService::getAccounts($uid) as $account)if((int)$account['gs_id']===$gs&&(int)$account['status']===1)$accounts[]=$account['game_login'];
        return [(int)$group['owner_id'],array_values(array_unique($accounts))];
    }
    public static function refresh($uid,$gs)
    {
        [$owner,$accounts]=self::scope($uid,$gs);$s=PortalRuntime::store();$available=true;
        try{
            $rows=[];
            if($accounts){$db=l2('gs',$gs)->connect()->getDb();$table=$db->getSchema()->getTable('characters');if(!$table||!isset($table->columns['onlinetime']))throw new RuntimeException('Game time unavailable');$id=isset($table->columns['obj_Id'])?'obj_Id':'charId';$rows=$db->createCommand('SELECT '.$id.' AS char_id,onlinetime FROM characters WHERE account_name IN ('.implode(',',array_fill(0,count($accounts),'?')).')')->queryAll(true,$accounts);}
            $status=PortalAttendance::observe($s,$owner,$gs,$rows);
        }catch(Throwable $e){$available=false;$status=PortalAttendance::status($s,$owner,$gs);}
        return $status+['available'=>$available,'owner'=>$owner];
    }
    public static function rewards()
    {
        $rewards=[];foreach(CalendarService::getRewards() as $day=>$items){if($day<1||$day>28)continue;$rewards[$day]=CalendarService::decorateItems($items);}return $rewards;
    }
    public static function tick()
    {
        $s=PortalRuntime::store();$rows=$s->query('SELECT DISTINCT g.owner_id,l.gs_id FROM {{account_groups}} g JOIN {{account_links}} l ON l.group_id=g.id WHERE l.status=1')->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row)self::refresh((int)$row['owner_id'],(int)$row['gs_id']);
    }
}
