<?php
/** Minimal projections avoid unrelated differences between Lucera database revisions. */
class LiveRankings
{
    private $db;
    public function __construct($db) { $this->db = $db; }
    private function col($table, array $names, $optional = false)
    {
        $schema = $this->db->schema->getTable($table);
        if ($schema) foreach ($names as $name) {
            foreach ($schema->columns as $actual => $column) {
                if (strcasecmp($actual, $name) === 0) return $actual;
            }
        }
        if ($optional) return null;
        throw new Exception('Unsupported ranking field: ' . $table . '.' . implode('/', $names));
    }
    private function field($table, $alias, array $names, $fallback = null)
    {
        $col = $this->col($table, $names, $fallback !== null);
        return $col ? $alias . '.' . $col : $fallback;
    }
    public function read($tab, $limit)
    {
        $limit = min(100, max(1, (int)$limit));
        if (in_array($tab,['clans','clanpvp','alliances','castles'],true)) {
            require_once __DIR__.'/ExtendedRankings.php';
            return (new ExtendedRankings($this->db))->read($tab,$limit);
        }
        $schema = $this->db->schema->getTable('characters');
        if ($schema && isset($schema->columns['obj_Id'], $schema->columns['base_class_id'])) return $this->readSplit($tab, $limit);
        if (in_array($tab, ['pvp', 'pk', 'exp'], true)) {
            $map = ['name'=>['char_name'], 'level'=>['level'], 'exp'=>['exp'], 'pvp'=>['pvpkills'], 'pk'=>['pkkills'], 'classId'=>['base_class','classid'], 'online'=>['online'], 'seconds'=>['onlinetime']];
            $select = [];
            foreach ($map as $alias => $names) $select[] = $this->field('characters','c',$names) . ' AS ' . $alias;
            $clan = $this->field('characters','c',['clanid','clan_id']);
            $clanId = $this->field('clan_data','d',['clan_id']);
            $select[] = $this->field('clan_data','d',['clan_name']) . ' AS clan';
            $order = $this->field('characters','c',$map[$tab]);
            $id = $this->field('characters','c',['charId','obj_Id','char_id']);
            $query = $this->db->createCommand()->select(implode(',', $select))->from('characters c')->leftJoin('clan_data d', $clan . '=' . $clanId);
            $access = $this->col('characters',['accesslevel','access_level'],true);
            if ($access) $query->andWhere('c.' . $access . '=0');
            if ($tab !== 'exp') $query->andWhere($order . '>0');
            $rows = $query->order($order . ' DESC, ' . $id . ' ASC')->limit($limit)->queryAll();
            foreach ($rows as &$row) {
                $row['exp'] = (string)$row['exp'];
                $row['class'] = Lineage::getClassName((int)$row['classId']);
                $row['clan'] = $row['clan'] ?: '—';
                $seconds = max(0,(int)$row['seconds']);
                $row['time'] = Yii::t('js', 'rating.duration', ['{days}' => floor($seconds/86400), '{hours}' => floor($seconds%86400/3600)]);
                $row['online'] = (int)$row['online'] === 1;
                unset($row['seconds'], $row['classId']);
            }
            return $rows;
        }
        throw new InvalidArgumentException('Unknown ranking');
    }

    private function readSplit($tab, $limit)
    {
        require_once dirname(__DIR__) . '/l2j/LuceraSplitQuery.php';
        $adapter = new LuceraSplitQuery($this->db);
        if (in_array($tab, ['pvp', 'pk', 'exp'], true)) {
            $field = ['pvp'=>'characters.pvpkills','pk'=>'characters.pkkills','exp'=>'sub.exp'][$tab];
            $query = $adapter->characters()->andWhere('COALESCE(characters.accesslevel,0) = 0');
            if ($tab !== 'exp') $query->andWhere($field . ' > 0');
            $rows = $query->order($field . ' DESC, characters.obj_Id ASC')->limit($limit)->queryAll();
            $result = [];
            foreach ($rows as $row) {
                $seconds = max(0,(int)$row['onlinetime']);
                $result[] = ['name'=>$row['char_name'], 'class'=>Lineage::getClassName((int)$row['base_class']), 'clan'=>$row['clan_name'] ?: '—', 'level'=>$row['level'], 'exp'=>$row['exp'] === null ? null : (string)$row['exp'], 'pvp'=>(int)$row['pvpkills'], 'pk'=>(int)$row['pkkills'], 'online'=>(int)$row['online'] === 1, 'time'=>Yii::t('js','rating.duration',['{days}'=>floor($seconds/86400),'{hours}'=>floor($seconds%86400/3600)])];
            }
            return $result;
        }
        throw new InvalidArgumentException('Unknown ranking');
    }
}
