<?php
/** Canonical player pages; legacy controllers remain internal business handlers. */
class PortalRoutes
{
    public static $dispatching=false;
    public static function pages()
    {
        return ['access'=>['security',['index']],'logins'=>['authHistory',['index']],'characters'=>['characters',['index','view']],'accounts'=>['accounts',['index','create','characters','switch']], 'wallet'=>['wallet',['index']],
            'calendar'=>['default',['calendar','calendarbuy','calendarclaim']], 'shop'=>['shop',['index','buy']],
            'support'=>['tickets',['index','add','view','close']], 'referrals'=>['referals',['index']],
            'history'=>['transactionHistory',['index']], 'bonuses'=>['bonuses',['index','bonusCode']],
            'messages'=>['messages',['index','detail']]];
    }
    public static function resolve($controller,$action)
    {
        $clean=function($s){return strtolower(str_replace(['-','_'],'',$s));};
        foreach(self::pages() as $section=>[$id,$actions]) if($clean($controller)===$clean($id)) foreach($actions as $a) if($clean($action)===$clean($a)) return [$section,$a];
        return null;
    }
    public static function url($section,$view='index',array $params=[])
    {
        unset($params['r'],$params['view']);
        if($view!=='index')$params=['view'=>$view]+$params;
        return '/cabinet/portal/'.$section.'/'.($params?'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986):'');
    }
    public static function rewrite($url)
    {
        if(!is_string($url)||preg_match('#^(?:[a-z]+:)?//#i',$url))return $url;
        $path=parse_url($url,PHP_URL_PATH);$parts=explode('/',trim((string)$path,'/'));
        if(($parts[0]??'')!=='cabinet')return $url;
        if(($parts[1]??'')==='calendar')return self::url('calendar','calendar');
        if(in_array($parts[1]??'',['tickets','messages'],true)&&preg_match('/^[0-9]+$/D',$parts[2]??'')){
            parse_str(parse_url($url,PHP_URL_QUERY)??'',$params);$section=$parts[1]==='tickets'?'support':'messages';$params[$parts[1]==='tickets'?'ticket_id':'id']=$parts[2];
            return self::url($section,$parts[3]??($section==='support'?'view':'detail'),$params);
        }
        if(($parts[1]??'')==='shop'&&!in_array($parts[2]??'index',['index','buy'],true)){
            parse_str(parse_url($url,PHP_URL_QUERY)??'',$params);$params['category_link']=$parts[2];return self::url('shop',$parts[3]??'index',$params);
        }
        $match=self::resolve($parts[1]??'', $parts[2]??'index');if(!$match)return $url;
        parse_str(parse_url($url,PHP_URL_QUERY)??'',$params);
        for($i=3;$i+1<count($parts);$i+=2)$params[$parts[$i]]=$parts[$i+1];
        return self::url($match[0],$match[1],$params);
    }
}
