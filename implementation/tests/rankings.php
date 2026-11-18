<?php
declare(strict_types=1);
require __DIR__.'/../public_html/cms-private/protected/components/ExtendedRankings.php';
class TestSchema {
    private $db;
    public function __construct($db) { $this->db=$db; }
    public function getTable($name) {
        $columns=$this->db->query('PRAGMA table_info('.$name.')')->fetchAll(PDO::FETCH_ASSOC);
        if (!$columns) return null;
        $result=(object)['columns'=>[]]; foreach ($columns as $column) $result->columns[$column['name']]=(object)$column;
        return $result;
    }
}
class TestCommand {
    private $db; private $select='*'; private $from; private $where=[]; private $group;
    public function __construct($db) { $this->db=$db; }
    public function select($s) { $this->select=$s; return $this; }
    public function from($s) { $this->from=$s; return $this; }
    public function where($s) { $this->where=[$s]; return $this; }
    public function andWhere($s) { $this->where[]=$s; return $this; }
    public function group($s) { $this->group=$s; return $this; }
    public function queryAll() {
        $sql='SELECT '.$this->select.' FROM '.$this->from;
        if ($this->where) $sql.=' WHERE '.implode(' AND ',$this->where);
        if ($this->group) $sql.=' GROUP BY '.$this->group;
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
class TestGameDb {
    public $schema; private $db;
    public function __construct($db) { $this->db=$db; $this->schema=new TestSchema($db); }
    public function createCommand() { return new TestCommand($this->db); }
}
$db=new PDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE clan_data(clan_id INTEGER,clan_level INTEGER,reputation_score INTEGER,hasCastle INTEGER,ally_id INTEGER)');
$db->exec('CREATE TABLE clan_subpledges(clan_id INTEGER,type INTEGER,name TEXT,leader_id INTEGER)');
$db->exec('CREATE TABLE characters(obj_Id INTEGER,char_name TEXT,clanid INTEGER,pvpkills INTEGER,accesslevel INTEGER)');
$db->exec('CREATE TABLE castle(id INTEGER,name TEXT,tax_percent INTEGER,siege_date BIGINT)');
$db->exec('CREATE TABLE siege_clans(castle_id INTEGER,clan_id INTEGER,type INTEGER)');
$db->exec('CREATE TABLE ally_data(ally_id INTEGER,ally_name TEXT)');
$db->exec('CREATE TABLE clanhall(ownerId INTEGER,name TEXT)');
$db->exec("INSERT INTO clan_data VALUES(1,8,200,1,10),(2,7,300,0,10),(3,8,100,0,0)");
$db->exec("INSERT INTO clan_subpledges VALUES(1,0,'Main clan',100),(1,1,'Academy',999),(2,0,'Second clan',200),(3,0,'Third clan',300)");
$db->exec("INSERT INTO characters VALUES(100,'Leader',1,50,0),(101,'Member',1,20,0),(102,'GM',1,99999,100),(200,'Other',2,100,0),(300,'Third',3,20,0)");
$db->exec("INSERT INTO castle VALUES(1,'Gludio',5,1797703200000)");
$db->exec('INSERT INTO siege_clans VALUES(1,2,1),(1,1,0),(1,3,2)');
$db->exec("INSERT INTO ally_data VALUES(10,'Alliance'); INSERT INTO clanhall VALUES(1,'Hall')");
$reader=new ExtendedRankings(new TestGameDb($db));
function expect($condition,$label) { if (!$condition) throw new RuntimeException($label); }
$clans=$reader->read('clans',100);
expect($clans[0]['name']==='Main clan','Clan ordering and main pledge');
expect($clans[0]['members']===2 && $clans[0]['pvp']===70,'Exclude service characters');
expect($clans[0]['hall']==='Hall' && $clans[0]['alliance']==='Alliance','Hall and alliance names');
expect($reader->read('clanpvp',100)[0]['name']==='Second clan','Clan PvP ordering');
$alliances=$reader->read('alliances',100);
expect($alliances[0]['pvp']===170 && $alliances[0]['clans']===2 && $alliances[0]['members']===3,'Alliance aggregates');
$castles=$reader->read('castles',100);
expect($castles[0]['leader']==='Leader' && $castles[0]['owner']==='Main clan','Castle owner/leader');
expect($castles[0]['attackers']===['Second clan'] && $castles[0]['defenders']===['Main clan'],'Siege participants');
expect(strpos($castles[0]['siege'],'GMT+3')!==false,'Server timezone');
$db->exec('DROP TABLE clanhall; DROP TABLE ally_data; DROP TABLE siege_clans');
$castles=$reader->read('castles',100);
expect($castles[0]['attackers']===[],'Missing optional schema');
echo "PASS: ranking aggregation, service character exclusion, owners, leaders, siege participants, optional tables\n";
