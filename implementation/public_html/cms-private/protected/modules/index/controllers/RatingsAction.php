<?php
/** Public read-only rankings; database credentials stay in the configured Gs model. */
class RatingsAction extends CAction
{
    public function run()
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        $result = ['ok' => false, 'message' => Yii::t('js', 'rating.unavailable')];
        try {
            $servers = Gs::getOpenServers();
            ksort($servers);
            $requested = request()->getQuery('gs_id');
            $server = null;
            foreach ($servers as $id => $candidate) {
                if ($requested !== null && (string)$id !== (string)$requested) continue;
                if ($candidate->stats_allow) { $server = $candidate; break; }
            }
            if (!$server) {
                $result['message'] = Yii::t('js', 'rating.disabled');
            } else {
                $key = 'landing.rankings.v5.' . app()->language . '.' . $server->getPrimaryKey() . '.' . sha1(serialize($server->attributes));
                $cached = cache()->get($key);
                if ($cached !== false) $result = $cached;
                else {
                    try {
                        Yii::import('application.components.LiveRankings');
                        $db = l2('gs', $server->getPrimaryKey())->connect()->getDb();
                        if (!$db->active) $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
                        $db->active = true;
                        $reader = new LiveRankings($db);
                        $data = []; $states = [];
                        $limit = 100;
                        foreach (['pvp' => 'pvp', 'pk' => 'pk', 'exp' => 'top', 'clans' => 'clans', 'clanpvp'=>'clans', 'alliances'=>'clans', 'castles' => 'castles'] as $tab => $flag) {
                            $data[$tab] = [];
                            $states[$tab] = 'disabled';
                            if (!$server->{'stats_' . $flag}) continue;
                            try {
                                $data[$tab] = $reader->read($tab, $limit);
                                $states[$tab] = 'ok';
                            } catch (Exception $e) {
                                $states[$tab] = 'error';
                                Yii::log($e->getMessage(), CLogger::LEVEL_ERROR, 'LiveRankings.' . $tab);
                            }
                        }
                        $result = ['ok' => true, 'data' => $data, 'states' => $states, 'server' => (string)$server->name, 'updatedAt' => gmdate('c')];
                    } catch (Exception $e) {
                        Yii::log($e->getMessage(), CLogger::LEVEL_ERROR, 'LiveRankings');
                    }
                    cache()->set($key, $result, $result['ok'] && !in_array('error', $result['states'], true) ? max(30, min(3600, (int)$server->stats_cache_time * 60)) : 30);
                }
            }
        } catch (Exception $e) {
            Yii::log($e->getMessage(), CLogger::LEVEL_ERROR, 'LiveRankings');
        }
        echo CJSON::encode($result);
        app()->end();
    }
}
