<?php
/** Schema-aware projections for clan PvP, alliances and detailed castle standings. */
class ExtendedRankings
{
    private $db;
    public function __construct($db) { $this->db=$db; }
    private function column($table,array $candidates,$optional=false)
    {
        $schema=$this->db->schema->getTable($table);
        if ($schema) foreach ($candidates as $candidate) foreach ($schema->columns as $name=>$column) if (strcasecmp($name,$candidate)===0) return $name;
        if ($optional) return null;
        throw new RuntimeException('Unsupported ranking schema: '.$table);
    }
    private function rows($table)
    {
        return $this->db->schema->getTable($table)?$this->db->createCommand()->from($table)->queryAll():[];
    }
    public function read($tab,$limit)
    {
        $clanId=$this->column('clan_data',['clan_id']);
        $charClan=$this->column('characters',['clanid','clan_id']);
        $charId=$this->column('characters',['obj_Id','charId','char_id']);
        $access=$this->column('characters',['accesslevel','access_level'],true);
        $charName=$this->column('characters',['char_name']);
        $pk=$this->column('characters',['pvpkills']);
        $name=$this->column('clan_data',['clan_name'],true);
        $main=[];
        foreach ($this->rows('clan_subpledges') as $r) if ((int)$r['type']===0) $main[$r['clan_id']]=$r;
        $allyNames=[];
        $allyId=$this->column('ally_data',['ally_id','id'],true);
        $allyName=$this->column('ally_data',['ally_name','name'],true);
        if ($allyId && $allyName) foreach ($this->rows('ally_data') as $r) $allyNames[$r[$allyId]]=$r[$allyName];
        $castleRows=$this->rows('castle'); $castleNames=[];
        foreach ($castleRows as $r) $castleNames[$r['id']]=$r['name'];
        $hallOwners=[];
        foreach (['clanhall','clan_hall'] as $hallTable) {
            $owner=$this->column($hallTable,['ownerId','owner_id','owner'],true);
            $hallName=$this->column($hallTable,['name'],true);
            if ($owner && $hallName) foreach ($this->rows($hallTable) as $r) if ((int)$r[$owner]) $hallOwners[$r[$owner]]=$r[$hallName];
        }
        $q=$this->db->createCommand()->select($charClan.' AS clan_id,COUNT(*) AS members,COALESCE(SUM('.$pk.'),0) AS pvp')->from('characters')->where($charClan.'>0');
        if ($access) $q->andWhere($access.'=0');
        $stats=[]; foreach ($q->group($charClan)->queryAll() as $r) $stats[$r['clan_id']]=$r;
        $clanRows=$this->rows('clan_data');
        $leaderIds=[];
        foreach ($clanRows as $r) {
            $pledge=$main[$r[$clanId]]??[];
            $leader=(int)($pledge['leader_id']??$r['leader_id']??$r['leaderId']??0);
            if ($leader>0) $leaderIds[$leader]=$leader;
        }
        $leaders=[];
        // Read only the leaders needed for this response, never all player profiles.
        if ($leaderIds) foreach ($this->db->createCommand()->select($charId.' AS id,'.$charName.' AS name')->from('characters')->where($charId.' IN ('.implode(',',$leaderIds).')')->queryAll() as $r) $leaders[$r['id']]=$r['name'];
        $clans=[];
        foreach ($clanRows as $r) {
            $id=(int)$r[$clanId]; $pledge=$main[$id]??[];
            $leaderId=$pledge['leader_id']??$r['leader_id']??$r['leaderId']??0;
            $aid=(int)($r['ally_id']??0);
            $aname=$r['ally_name']??$allyNames[$aid]??null;
            $clans[$id]=['id'=>$id,'name'=>$name?($r[$name]?:'—'):($pledge['name']??'—'),
                'level'=>(int)($r['clan_level']??0),'reputation'=>(int)($r['reputation_score']??0),
                'castle'=>$castleNames[$r['hasCastle']??0]??'—','hall'=>$hallOwners[$id]??'—',
                'members'=>(int)($stats[$id]['members']??0),'pvp'=>(int)($stats[$id]['pvp']??0),
                'ally_id'=>$aid,'alliance'=>$aname?:($aid?'#'.$aid:'—'),'leader'=>$leaders[$leaderId]??'—'];
        }
        if ($tab==='alliances') {
            $allies=[];
            foreach ($clans as $clan) if ($clan['ally_id']) {
                $id=$clan['ally_id'];
                if (!isset($allies[$id])) $allies[$id]=['id'=>$id,'name'=>$clan['alliance'],'clans'=>0,'members'=>0,'pvp'=>0,'reputation'=>0];
                foreach (['members','pvp','reputation'] as $key) $allies[$id][$key]+=$clan[$key];
                $allies[$id]['clans']++;
            }
            $rows=array_values($allies);
            usort($rows,function ($a,$b) { return ($b['pvp']<=>$a['pvp'])?:($b['reputation']<=>$a['reputation'])?:($a['id']<=>$b['id']); });
            return array_slice($rows,0,$limit);
        }
        if ($tab==='clans' || $tab==='clanpvp') {
            $rows=array_values($clans);
            usort($rows,function ($a,$b) use ($tab) {
                return ($tab==='clanpvp'?($b['pvp']<=>$a['pvp']):($b['level']<=>$a['level']))?:($b['reputation']<=>$a['reputation'])?:($a['id']<=>$b['id']);
            });
            return array_slice($rows,0,$limit);
        }
        if ($tab==='castles') {
            $participants=[];
            foreach ($this->rows('siege_clans') as $r) {
                $type=(int)($r['type']??-1);
                if (!in_array($type,[0,1],true)) continue;
                $participants[$r['castle_id']][$type===1?'attackers':'defenders'][]=$clans[$r['clan_id']]['name']??'—';
            }
            $owners=[]; foreach ($clanRows as $r) if (!empty($r['hasCastle'])) $owners[$r['hasCastle']]=$clans[$r[$clanId]];
            $result=[];
            foreach ($castleRows as $r) {
                $id=(int)$r['id']; $owner=$owners[$id]??[];
                $stamp=(int)($r['siege_date']??$r['siegeDate']??0); if ($stamp>100000000000) $stamp=intdiv($stamp,1000);
                $date=$stamp>0?(new DateTimeImmutable('@'.$stamp))->setTimezone(new DateTimeZone('+03:00'))->format('d.m.Y H:i').' GMT+3':'—';
                $result[]=['id'=>$id,'castle'=>$r['name'],'owner'=>$owner['name']??'—','leader'=>$owner['leader']??'—',
                    'tax'=>(int)($r['tax_percent']??$r['taxPercent']??0),'siege'=>$date,
                    'image'=>'/images/castles/'.$id.'.jpg','attackers'=>$participants[$id]['attackers']??[],'defenders'=>$participants[$id]['defenders']??[]];
            }
            usort($result,function ($a,$b) { return $a['id']<=>$b['id']; });
            return $result;
        }
        throw new InvalidArgumentException('Unknown ranking');
    }
}
