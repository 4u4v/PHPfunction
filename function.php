<?php



/**
 * $str:要截取的字符串
 * $start=0：开始位置，默认从0开始
 * $length：截取长度
 * $charset="utf-8″：字符编码，默认UTF－8
 * $suffix=true：是否在截取后的字符后面显示省略号，默认true显示，false为不显示
 */

function msubstr($str, $start=0, $length, $charset="utf-8", $suffix=true)
{
    if(function_exists("mb_substr"))
        return mb_substr($str, $start, $length, $charset);
    elseif(function_exists('iconv_substr')) {
        return iconv_substr($str,$start,$length,$charset);
    }
    $re['utf-8']   = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
    $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
    $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
    $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
    preg_match_all($re[$charset], $str, $match);
    $slice = join("",array_slice($match[0], $start, $length));
    if($suffix) return $slice."…";
    return $slice;
}


/**
 * 数据编码转换
 * @param $data  被转换数据
 * @param $fencode 原始编码
 * @param $tencode 输出编码
 * @return string
 */
function convertEncode( $data, $fencode, $tencode ){
    if( is_array($data) || is_object($data) ){
        foreach($data as &$value)
            $value = convertEncode( $value, $fencode, $tencode );
    }else{
        return function_exists('mb_convert_encoding')?
             trim( mb_convert_encoding( $data, $tencode, $fencode ) ):
                trim( iconv( $fencode, $tencode, $data ) );
    }
    return $data;
}


/**
 * @description 清楚HTML代码
 * @param $content
 * @param $allowtags
 * @return mixed
 */
function clearHtml($content,$allowtags='') {

    mb_regex_encoding('UTF-8');

    $search = array('/&lsquo;/u', '/&rsquo;/u', '/&ldquo;/u', '/&rdquo;/u', '/&mdash;/u');
    $replace = array('\'', '\'', '"', '"', '-');
    $content = preg_replace($search, $replace, $content);

    $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

    if(mb_stripos($content, '/*') !== FALSE){
        $content = mb_eregi_replace('#/\*.*?\*/#s', '', $content, 'm');
    }

    $content = preg_replace(array('/<([0-9]+)/'), array('< $1'), $content);

    $content = strip_tags($content, $allowtags);

    $content = preg_replace(array('/^\s\s+/', '/\s\s+$/', '/\s\s+/u'), array('', '', ' '), $content);

    $search = array('#<(strong|b)[^>]*>(.*?)</(strong|b)>#isu', '#<(em|i)[^>]*>(.*?)</(em|i)>#isu', '#<u[^>]*>(.*?)</u>#isu');
    $replace = array('<b>$2</b>', '<i>$2</i>', '<u>$1</u>');
    $content = preg_replace($search, $replace, $content);

    $num_matches = preg_match_all("/\<!--/u", $content, $matches);
    if($num_matches){
        $content = preg_replace('/\<!--(.)*--\>/isu', '', $content);
    }
    return $content;
}



/**
 * 编译缓存清除器
 * @param null $path 清除文件路径 默认为 RUNTIME_PATH
 */
function clearCompile($path=null){
    $path = $path==null?RUNTIME_PATH:$path;
    $handle = opendir($path) or halt('Open Dir Failed! '.$path,1020);
    while(false !==($file=readdir($handle))){
        if($file!=="."&&$file!==".."){
            $file = $path.DIRECTORY_SEPARATOR.$file;
            if( is_dir($file) ){
                clearCompile($file);
            }else{
                @chmod($file,0777);
                ( is_writable($file) && @unlink($file) ) or
                    halt('No Power To Delete file: "'.$file.'"',1020);
            }
        }
    }
    return ;
}



/**
 * JSON格式化数据处理(友好化处理JSON中文)
 * @param mixed
 * @return Json String
 */
function jsonEncode($var) {
    switch (gettype($var)) {
        case 'boolean':
            return $var ? 'true' : 'false';
        case 'integer':
        case 'double':
            return $var;
        case 'resource':
        case 'string':
            return '"'. str_replace(array("\r", "\n", "<", ">", "&"),
                array('\r', '\n', '\x3c', '\x3e', '\x26'),
                addslashes($var)) .'"';
        case 'array':
            if (empty ($var) || array_keys($var) === range(0, sizeof($var) - 1)) {
                $output = array();
                foreach ($var as $v) {
                    $output[] = jsonEncode($v);
                }
                return '[ '. implode(', ', $output) .' ]';
            }
        case 'object':
            $output = array();
            foreach ($var as $k => $v) {
                $output[] = jsonEncode(strval($k)) .': '. jsonEncode($v);
            }
            return '{ '. implode(', ', $output) .' }';
        default:
            return 'null';
    }
}

/**
 *	获取客户端IP地址
 *	@return String IPAddress OR  Not Found
 */
function getClientIp(){
    if(!empty($_SERVER["HTTP_CLIENT_IP"])){
        $cip = $_SERVER["HTTP_CLIENT_IP"];
    }
    elseif(!empty($_SERVER["HTTP_X_FORWARDED_FOR"])){
        $cip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    }
    elseif(!empty($_SERVER["REMOTE_ADDR"])){
        $cip = $_SERVER["REMOTE_ADDR"];
    }
    else{
        $cip = "Not Found";
    }
    return $cip;
}


/**
 * 浏览器友好的变量输出(来源ThinkPHP)
 * @param mixed $var 变量
 * @param boolean $echo 是否输出 默认为True 如果为false 则返回输出字符串
 * @param string $label 标签 默认为空
 * @param boolean $strict 是否严谨 默认为true
 * @return void|string
 */
function dump($var, $echo=true, $label=null, $strict=true) {
    $label = ($label === null) ? '' : rtrim($label) . ' ';
    if (!$strict) {
        if (ini_get('html_errors')) {
            $output = print_r($var, true);
            $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
        } else {
            $output = $label . print_r($var, true);
        }
    } else {
        ob_start();
        var_dump($var);
        $output = ob_get_clean();
        if (!extension_loaded('xdebug')) {
            $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);
            $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
        }
    }
    if ($echo) {
        echo($output);
        return null;
    }else
        return $output;
}


/**
 * 判断是否SSL协议
 * @return boolean
 */
function is_ssl() {
    if(isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))){
        return true;
    }elseif(isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'] )) {
        return true;
    }
    return false;
}

/**
 * 根据PHP各种类型变量生成唯一标识号
 * @param mixed $mix 变量
 * @return string
 */
function to_guid_string($mix) {
    if (is_object($mix) && function_exists('spl_object_hash')) {
        return spl_object_hash($mix);
    } elseif (is_resource($mix)) {
        $mix = get_resource_type($mix) . strval($mix);
    } else {
        $mix = serialize($mix);
    }
    return md5($mix);
}

/**
 * XML编码
 * @param mixed $data 数据
 * @param string $encoding 数据编码
 * @param string $root 根节点名
 * @return string
 */
function xml_encode($data, $encoding='utf-8', $root='think') {
    $xml    = '<?xml version="1.0" encoding="' . $encoding . '"?>';
    $xml   .= '<' . $root . '>';
    $xml   .= data_to_xml($data);
    $xml   .= '</' . $root . '>';
    return $xml;
}
/**
 * 数据XML编码
 * @param mixed $data 数据
 * @return string
 */
function data_to_xml($data) {
    $xml = '';
    foreach ($data as $key => $val) {
        is_numeric($key) && $key = "item id=\"$key\"";
        $xml    .=  "<$key>";
        $xml    .=  ( is_array($val) || is_object($val)) ? data_to_xml($val) : $val;
        list($key, ) = explode(' ', $key);
        $xml    .=  "</$key>";
    }
    return $xml;
}


/**
 * 格式化友好时间
 * @param sTime 源时间
 * @param type 时间格式精简范围 normal(default)/full/month
 * @return 友好时间
 */
function friendlyDate($sTime,$type = 'normal',$alt = 'false') {
    if(!$sTime) {
        return '';
    }
    //sTime=源时间，cTime=当前时间，dTime=时间差
    $cTime = time();
    $dTime = $cTime - $sTime;
    $dDay = intval(date("Ymd",$cTime)) - intval(date("Ymd",$sTime));
    $dYear = intval(date("Y",$cTime)) - intval(date("Y",$sTime));
    //normal：n秒前，n分钟前，n小时前，日期
    if($type=='normal') {
        if( $dTime < 60 ) {
            return $dTime."秒前";
        }elseif( $dTime < 3600 ) {
            return intval($dTime/60)."分钟前";
        }elseif( $dTime >= 3600 && $dDay == 0  ) {
            return intval($dTime/3600)."小时前";
        }elseif($dYear==0) {
            return date("m-d H:i",$sTime);
        }else {
            return date("y-m-d H:i",$sTime);
        }
    //full: Y-m-d , H:i:s
    }elseif($type=='full') {
        return date("y-m-d H:i",$sTime);
    }elseif($type=='month') {
        return date("m-d H:i",$sTime);
    }else {
        if( $dTime < 60 ) {
            return $dTime."秒前";
        }elseif( $dTime < 3600 ) {
            return intval($dTime/60)."分钟前";
        }elseif( $dTime >= 3600 && $dDay == 0  ) {
            return intval($dTime/3600)."小时前";
        }elseif($dYear==0) {
            return date("y-m-d H:i",$sTime);
        }else {
            return date("y-m-d H:i",$sTime);
        }
    }
}
