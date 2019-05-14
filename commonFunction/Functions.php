<?php

namespace App\Utils;
use function DI\object;
use App\Exceptions\NotifyException;
use App\Constants\MesageType;
use App\Constants\StatusCode;

if (!function_exists('getallheaders')) {
    function getallheaders() {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}
/**
 * 公共函数类
 * @package App\Utils
 */
class Functions
{
    /**
     * @inject
     * @var \PhpBoot\YsApplication
     */
    public static $app;

    const IMG_TYPE_ORIGINAL_IMG = 1;

    const IMG_TYPE_LIST_ICON = 2;

    const IMG_TYPE_CONTENT_ICON = 3;

    const IMG_TYPE_USER_AVATAR = 4;

    const IMG_TYPE_BANNER = 5;


    private static $imgTypeMap = [
        self::IMG_TYPE_ORIGINAL_IMG => '',
        self::IMG_TYPE_BANNER => '?x-oss-process=image/resize,m_fill,h_707,w_1080',
        self::IMG_TYPE_LIST_ICON => '?x-oss-process=image/resize,m_fill,h_167,w_165',
        self::IMG_TYPE_CONTENT_ICON => '?x-oss-process=image/resize,m_fill,h_810,w_1080',
        self::IMG_TYPE_USER_AVATAR => '',
        //self::IMG_TYPE_USER_AVATAR => '?x-oss-process=image/circle,r_250/format,png'
    ];

    /**
     * 计算出授信信用分
     * @param $sourceScore 需要转化的分值
     * @param $sourceMaxScore 需要转化分值的最大值
     * @param $targetMaxScore 目标转化分值的最大值
     * @param int $scale
     * @return string
     */
    public static function calCreditScore($sourceScore,$sourceMaxScore,$targetMaxScore,$scale = 3){
        return bcmul(bcdiv($sourceScore,$sourceMaxScore,$scale),$targetMaxScore,$scale);
    }
    /**
     * @desc 生成授信申请唯一流水号
     * @param string $id
     * @return string
     */
    public static function createCreditSerialNumber($id){
        return 'C'.self::createSerialNumber($id);
    }
    

    /**
     * @desc 生成唯一流水号
     * @param string $id
     * @return string
     */
    private static function createSerialNumber($id = ''){
        return date('Ymd').substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(1000, 9999)).$id;
    }

    /**
     * UUID
     */
    public static function uuid()
    {
        $charid = md5(uniqid(mt_rand(), true));
        $uuid = substr($charid, 0, 8)
            . substr($charid, 8, 4)
            . substr($charid, 12, 4)
            . substr($charid, 16, 4)
            . substr($charid, 20, 12);
        return $uuid;
    }

    public static function getHeader($key){
        $headers = getallheaders();
        $value = '';
        if(isset($headers[$key])){
            $value = $headers[$key];
        }
        return $value;
    }

    /**
     * 取两个日期间的天数
     * @param $day1
     * @param $day2
     * @return int
     */
    public static function diffBetweenTwoDays ($day1, $day2){
        $second1 = strtotime($day1);
        $second2 = strtotime($day2);

        if ($second1 < $second2) {
            $tmp = $second2;
            $second2 = $second1;
            $second1 = $tmp;
        }
        return intval(abs($second1 - $second2) / 86400);
    }

    /*
    *function：计算两个日期相隔多少年，多少月，多少天
    *param string $date1[格式如：2011-11-5]
    *param string $date2[格式如：2012-12-01]
    *return array array('年','月','日');
    */
    public static function diffDate($date1,$date2){
        if(strtotime($date1)>strtotime($date2)){
            $tmp=$date2;
            $date2=$date1;
            $date1=$tmp;
        }
        list($Y1,$m1,$d1)=explode('-',$date1);
        list($Y2,$m2,$d2)=explode('-',$date2);
        $Y=$Y2-$Y1;
        $m=$m2-$m1;
        $d=$d2-$d1;
        if($d<0){
            $d+=(int)date('t',strtotime("-1 month $date2"));
            $m--;
        }
        if($m<0){
            $m+=12;
            $Y--;
        }
        return array('year'=>$Y,'month'=>$m,'day'=>$d);
    }

    /**
     * 人民币小写转大写
     * @param string $number 数值
     * @param string $int_unit 币种单位，默认"元"，有的需求可能为"圆"
     * @param bool $is_round 是否对小数进行四舍五入
     * @param bool $is_extra_zero 是否对整数部分以0结尾，小数存在的数字附加0,比如1960.30，
     *             有的系统要求输出"壹仟玖佰陆拾元零叁角"，实际上"壹仟玖佰陆拾元叁角"也是对的
     * @return string
     */
    public static function numToRmb($number = 0, $int_unit = '元', $is_round = true, $is_extra_zero = false){
        // 将数字切分成两段
        $parts = explode('.', $number, 2);
        $int = isset($parts[0]) ? strval($parts[0]) : '0';
        $dec = isset($parts[1]) ? strval($parts[1]) : '';

        // 如果小数点后多于2位，不四舍五入就直接截，否则就处理
        $dec_len = strlen($dec);
        if (isset($parts[1]) && $dec_len > 2)
        {
            $dec = $is_round
                ? substr(strrchr(strval(round(floatval("0.".$dec), 2)), '.'), 1)
                : substr($parts[1], 0, 2);
        }

        // 当number为0.001时，小数点后的金额为0元
        if(empty($int) && empty($dec))
        {
            return '零';
        }

        // 定义
        $chs = array('0','壹','贰','叁','肆','伍','陆','柒','捌','玖');
        $uni = array('','拾','佰','仟');
        $dec_uni = array('角', '分');
        $exp = array('', '万');
        $res = '';

        // 整数部分从右向左找
        for($i = strlen($int) - 1, $k = 0; $i >= 0; $k++)
        {
            $str = '';
            // 按照中文读写习惯，每4个字为一段进行转化，i一直在减
            for($j = 0; $j < 4 && $i >= 0; $j++, $i--)
            {
                $u = $int{$i} > 0 ? $uni[$j] : ''; // 非0的数字后面添加单位
                $str = $chs[$int{$i}] . $u . $str;
            }
            //echo $str."|".($k - 2)."<br>";
            $str = rtrim($str, '0');// 去掉末尾的0
            $str = preg_replace("/0+/", "零", $str); // 替换多个连续的0
            if(!isset($exp[$k]))
            {
                $exp[$k] = $exp[$k - 2] . '亿'; // 构建单位
            }
            $u2 = $str != '' ? $exp[$k] : '';
            $res = $str . $u2 . $res;
        }

        // 如果小数部分处理完之后是00，需要处理下
        $dec = rtrim($dec, '0');

        // 小数部分从左向右找
        if(!empty($dec))
        {
            $res .= $int_unit;

            // 是否要在整数部分以0结尾的数字后附加0，有的系统有这要求
            if ($is_extra_zero)
            {
                if (substr($int, -1) === '0')
                {
                    $res.= '零';
                }
            }

            for($i = 0, $cnt = strlen($dec); $i < $cnt; $i++)
            {
                $u = $dec{$i} > 0 ? $dec_uni[$i] : ''; // 非0的数字后面添加单位
                $res .= $chs[$dec{$i}] . $u;
            }
            $res = rtrim($res, '0');// 去掉末尾的0
            $res = preg_replace("/0+/", "零", $res); // 替换多个连续的0
        }
        else
        {
            $res .= $int_unit . '整';
        }
        return $res;
    }

    /**
     * 对参数进行加密
     * @param $params
     * @return string
     */
    public static function sginParamsString($params){
        $apiSecret = $GLOBALS['app']->get('apiSecret');
        //计算签名
        $paramsSign = $params;
        unset($paramsSign['sign']);
        foreach ($paramsSign as &$item){
            $item  = ''.$item;
        }
        //按照key排序
        sort($paramsSign,SORT_STRING);
        //加密获取sign
        $sign=md5(implode('',$paramsSign).$apiSecret);//对该字符串进行 MD5 计算，得到签名，并转换成 16 进制小写编码
        //设置请求参数
        $params['sign'] = $sign;

        $paramString = '';
        foreach ($params as $key=>$value){
            $paramString .= "{$key}={$value}&";
        }
        $paramString = trim($paramString,'&');

        return $paramString;
    }

    /**
     * 验证签名
     * @param $params
     * @return string
     */
    public static function checkSgin($params){
        $apiSecret = $GLOBALS['app']->get('apiSecret');

        //计算签名
        $paramsSign = $params;
        unset($paramsSign['sign']);
        unset($paramsSign['s']);

        //按照key排序
        sort($paramsSign,SORT_STRING);
        //加密获取sign
        $sign=md5(implode('',$paramsSign).$apiSecret);//对该字符串进行 MD5 计算，得到签名，并转换成 16 进制小写编码

        if($sign == $params['sign']){
            return true;
        }else{
            return false;
        }
    }

    public static function checkTelCaptcha($uname,$tel_captcha,$captcha_type){
        $tel_captcha_cache_key = 'tel_captcha_' . $uname.'_'.$captcha_type;

        $check_tel_captcha = $GLOBALS['redis']->cache($tel_captcha_cache_key);

        if (empty($tel_captcha)) {
            throw new NotifyException(MesageType::TEL_CAPTCHA_NOT_EMPTY);
        }

        if (empty($check_tel_captcha)) {
            throw new NotifyException(MesageType::TEL_CAPTCHA_EXPIRE);
        }

        if ($check_tel_captcha != $tel_captcha) {
            throw new NotifyException(MesageType::TEL_CAPTCHA_ERROR);
        }
        return $tel_captcha_cache_key;
    }

    /**
     * curl请求，支持post, get方式
     * @return string $file_contents
     */
    public static function sendRequest($url = '', $post = 'post', $json = false, $method = 'post', $headers = [],$timeout=25,$certs=[])
    {
        $ch = curl_init();
        if (is_array($post)) {
            $post = http_build_query($post);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if ('post' == strtolower($method)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        if ($json) {
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($post))
            );
        } else if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if(empty(!$certs)){
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, $certs['cert_file']);
            curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $certs['cert_passwd']);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, $certs['cert_key_file']);
        }

        $file_contents = curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == '200') {
            $body = false;
        }
        curl_close($ch);
        return $file_contents;
    }


    /**
     * curl请求，支持post, get方式
     * @return string $file_contents
     */
    public static function sendFile($url = '', $post = [], $headers = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER,$headers);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // stop verifying certificate
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true); // enable posting
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post); // post images
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); // if any redirection after upload

        $file_contents = curl_exec($curl);

        curl_close($curl);
        return $file_contents;
    }

    /**
     * 手动写入日志
     * @param string $data 写入内容
     * @param stirng $dir 写入目录
     * @param string $method 写入方式
     * @return ;
     */
    public static function wlog($data = '', $dir = '', $method = "a+")
    {
        $fileDir = APP_PATH . '/runtime/logs/' . $dir . date('Y/m/d') . '.log';
        if (!file_exists(dirname($fileDir))) {
            if (!@mkdir(dirname($fileDir), 0777, true)) {
                die(dirname($fileDir) . '创建目录失败!');
            }
        }

        if (is_file($fileDir) && floor(1024 * 1000 * 50) <= filesize($fileDir)) {
            rename($fileDir, dirname($fileDir) . '/' . time() . '-' . basename($fileDir));
        }

        $ip = self::getClientIp();
        $fp = @fopen($fileDir, $method);
        if (!$fp) {
            return 0;
        }
        flock($fp, LOCK_EX);
        $http = (strtolower(@$_SERVER['HTTPS']) == 'on') ? 'https://' : 'http://';
        $http = $http . trim($_SERVER['HTTP_HOST']);
        $submit_type = !empty($_POST) ? 'POST' : 'GET';
        $opt = @fwrite($fp, '[' . date('Y-m-d H:i:s') . ']' . "\r\n" . ' URL来源:' . $_SERVER['HTTP_REFERER'] . "\r\n 请求地址：" . $http . $_SERVER["REQUEST_URI"] . "\r\n 请求Header数据: " . var_export($_SERVER, true) . "\r\n 请求数据(" . $submit_type . "方式): " . var_export($_REQUEST, true) . "\r\n 浏览器 : " . $_SERVER['HTTP_USER_AGENT'] . "\r\n 数据：" . var_export($data, true) . ' 访问IP：' . $ip . "\r\n\r\n");
        fclose($fp);
        return $opt;
    }

    /**
     * 获取客户端IP地址
     * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @return string
     */

    public static function getClientIp($type = 0)
    {
        $type = $type ? 1 : 0;
        static $ip = NULL;
        if ($ip !== NULL) return $ip[$type];
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) unset($arr[$pos]);
            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
        return $ip[$type];

    }


    /**
     * 公共返回值
     * @param string $data 数据
     * @param string $status 状态码
     * @return string
     */
    public static function parse_data($data = '', $status = '2000')
    {
        $msg = '操作成功';
        $version = 'v1.0';
        $result = object();
        if (is_string($data)) {
            $msg = $data;
        } else if (is_array($data)) {
            $msg = !empty($data['msg']) ? $data['msg'] : $msg;
            $version = !empty($data['version']) ? $data['version'] : $version;
            $result = !empty($data['result']) ? $data['result'] : $data;
            unset($data['msg']);
            unset($data['version']);
        } else {
            $msg = '参数错误';
        }

        $result = \GuzzleHttp\json_encode(array('id' => uniqid(rand()), 'status' => $status, 'msg' => $msg, 'result' => $result, 'version' => $version, 'runtime' => time()));
        return self::aesCrypt(strrev(base64_encode($result)), true, self::getToken());
    }

    /**
     * 生成密码
     * @param int $length
     * @return string
     */
    public static function makePassword($length = 8){
        $chars = [
            'a','b','c','d','e','f','g','h','i','j','k','l','m',
            'n','o','p','q','r','s','t','u','v','w','x','y','z',
            'A','B','C','D','E','F','G','H','I','J','K','L','M',
            'N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
            '0','1','2','3','4','5','6','7','8','9','!',
            '@','#','$','%','^','&','*','(',')','-','_',
            '[',']','{','}','<','>','~','`','+','=',',',
            '.',';',':','/','?','|'];
        $keys = array_rand($chars, $length);

        $password = '';
        for($i = 0; $i < $length; $i++)
        {
            $password .= $chars[$keys[$i]];
        }

        return $password;
    }

    public static function createToken($prefix = ''): string
    {
        return substr(md5(uniqid($prefix, true)), 2, 16);
    }

    public static function getToken(): string
    {
        $key = 123456;
        $sessoin = $GLOBALS['redis']->hgetall(session_id());
//        $login_info  = $GLOBALS['redis']->cache('app_login_user_' . $sessoin['u_name']);
//        if($sessoin['token_id'] != $login_info['curr_token']){
//            throw new NotifyException('您的账户已在其他设备登录，若非本人操作请及时更新密码！',StatusCode::NORMAL_ERROR);
//        }

        return empty($sessoin) ? substr(md5($key), 2, 16) : (isset($sessoin['token_id']) && $sessoin['token_id'] ? $sessoin['token_id'] : substr(md5($key), 2, 16));
    }

    /**
     * 加/解密
     * @param string $data 待加密串或待解密串
     * @param boolean $is_crypt 是否加密 默认为加密,否则为解密
     * @param string $key 加密Key值
     * @return string
     */
    public static function aesCrypt($data = '', $is_crypt = true, $key = '')
    {
        $key = empty($key) ? substr(md5(123456), 2, 16) : $key;
        $privateKey = $iv = $key;
        $iv = '';
        if ($is_crypt) {
            //加密
            $encrypted = openssl_encrypt($data, 'AES-128-ECB', $privateKey, OPENSSL_RAW_DATA, $iv);
            return str_replace('+', '-', str_replace('/', '_', str_replace('=', '', base64_encode($encrypted))));
        } else {
            //解密
            $data = str_replace('-', '+', str_replace('_', '/', $data));
            $decrypted = openssl_decrypt(base64_decode($data), 'AES-128-ECB', $privateKey, OPENSSL_RAW_DATA, $iv);
            return trim($decrypted);
        }
    }

    //只替换第一个配匹的关键字
    public static function str_replace_once($needle, $replace, $haystack)
    {
        $pos = strpos($haystack, $needle);
        if ($pos === false) {
            return $haystack;
        }
        return substr_replace($haystack, $replace, $pos, strlen($needle));
    }

    /**
     * 获取服务
     * @param string $targetSys
     * @param string $interface
     * @param string $params
     * @param int $callback_type
     * @param string $msg_id
     * @return string
     */
    public static function getWS(string $targetSys, string $interface, array $params = [], int $callback_type = 0, string $msg_id = '')
    {
        $config = $GLOBALS['app']->get('ESB');
        $s = new \SoapClient($config['wsdl']);
	    $soap_var = new \SoapVar(array('targetSys'=>$targetSys,'authName' => $config[$targetSys]['authName'], 'authPwd' => $config[$targetSys]['authPwd'], 'authorization' => md5(date('Y-m-d'))), SOAP_ENC_OBJECT, 'auth', 'ys');
	    $u = new \SoapHeader('ys', 'auth', $soap_var, true);
        $s->__setSoapHeaders($u);
        return json_decode($s->destInterface('api', $targetSys, $interface, \GuzzleHttp\json_encode($params), $callback_type, $msg_id), true);
    }

    /**
     * 在指定时间内是否超出指定次数
     * @param $key 加锁键值
     * @param $max_num  最大次数
     * @param $expire 限制时间
     * @return bool (true=超出|false=未超出)
     */
    public static function moreThanCount($key, $max_num, $expire = 60)
    {
        $current_val = $GLOBALS['redis']->get($key);

        if (!empty($current_val) && $current_val > $max_num) {
            return true;
        } else {
            $value = $GLOBALS['redis']->incr($key);
            if ($value == 1) {
                $GLOBALS['redis']->expire($key, $expire);
            }
            return false;
        }
    }

    /**
     * 判断是否是维护中
     */
    public static function isMaintained(){
        $content = $GLOBALS['redis']->get('api_service_maintenance');

        if (!empty($content)) {
            throw new NotifyException($content,StatusCode::SYSTEM_MAINTAINED);
        }
    }


    /**
     * unicode编码
     * @param $str
     * @return string
     */
    public static function unicode_encode($str)
    {
        if (!$str)
            return $str;
        $decode = json_encode($str);
        if ($decode)
            return $decode;
        $str = '["' . $str . '"]';

        $decode = json_encode($str);
        if (count($decode) == 1) {
            return $decode[0];
        }
        return $str;
    }

    /**
     * unicode解码
     * @param $str
     * @return mixed|string
     */
    public static function unicode_decode($str)
    {
        if (!$str)
            return $str;
        $decode = json_decode($str);
        if ($decode)
            return $decode;
        $str = '["' . $str . '"]';

        $decode = json_decode($str);
        if (count($decode) == 1) {
            return $decode[0];
        }
        return $str;
    }

    /**
     * 分页格式药数据
     * @param $data
     * @param $page
     */
    public static function pageFormat($data,$page=false){
        $result  = array();

        if(is_array($data[0]) || empty($data)){
            $result['data'] = $data;
        }else{
            $result['data'][] = $data;
        }


        $result['is_encrypt'] = false;
        $result['page']['curr'] = min(intval($page),1   );
        if(!empty($data) && $page){
//            $result['page']['next'] = $page;
//
//        }else{
            $result['page']['next'] = $page+1;
        }
//        var_dump($result);
//        die();

        return $result;
    }

    /**
     * 格式化商品规格
     * @param $specs
     */
    public static function formatSpecs($specs){
        if($specs){
            return strtr($specs,['/'=> ' ']).'/件';
        }else{
            return '';
        }
    }

    /**
     * 获取app类型
     */
    public static function getAppSource(){
        $app_source = intval(Functions::getHeader('Identification'));

        if(!in_array($app_source,[1,2])){
            $app_source = 1;
        }
        return $app_source;
    }
    /**
     * 距离格式化输出
     * @param $dist 默认单位米
     */
    public static function dist_format($dist){
        $unit = '米';
        $right_operand = 1;
        if($dist > 999){
            $unit = '公里';
            $right_operand = 1000;
        }
        $dist = bcdiv($dist,$right_operand,1);
        return "{$dist}{$unit}";
    }


    /**
     * 将相对路径转绝对路径
     * @param $real_path
     */
    public static function fullFilePath($real_path,$img_type=self::IMG_TYPE_ORIGINAL_IMG){
        $full_path = '';
        if($real_path){
            $full_path = $real_path.self::$imgTypeMap[$img_type];
        }
        return $full_path;
    }


    /**
     * 根据两点间的经纬度计算距离
     * @param $lng1
     * @param $lat1
     * @param $lng2
     * @param $lat2
     * @return int 米
     */
    public static function getDistance($lng1, $lat1, $lng2, $lat2)
    {
        //将角度转为狐度
        $radLat1 = deg2rad($lat1);//deg2rad()函数将角度转换为弧度
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137 * 1000;
        return $s;
    }

    /**
     * 格式化商家配送信息
     * @param $partner_info
     */
    public static function formatDistributInfo($partner_info){
        $info_list = [];
        if($partner_info['is_support_distribut'] == 1){
            $info_list[] = '商家配送';
            if($partner_info['free_freight'] == 2){
                $info_list[] = '免运费';
            }else{
                $info_list[] = '起送费￥'.$partner_info['lowest_freight_money'];
            }

        }else{
            $info_list[] = '到店自取';
        }

        return implode(' ',$info_list);
    }


    /**
     * 格式化内容
     * @param $rule_type
     * @param $rule_value
     * @return string
     */
    public static function formatRuleInfo($rule_type,$rule_value){
        if($rule_type == '~'){
            $temp_arr_value = explode(',',$rule_value);
            $out = "{$temp_arr_value[0]} {$rule_type} {$temp_arr_value[1]}";
        }else if($rule_type == 'in'){
            $out = "包含($rule_value)";
        }else{
            $out = "{$rule_type} $rule_value ";
        }
        return $out;
    }

    /**
     * 格式化优惠券金额信息
     * @param $money
     */
    public static function formatCouponMoney($money){
        $split_money = explode('.',$money,2);
        if(count($split_money) == 2){
            if(intval($split_money[1]) > 0){
                return bcsub($money,0,1);
            }else{

                return $split_money[0];
            }
        }else{
            return $money;
        }

    }

    /**
     * 存储魔蝎pdf报告
     * @param $type
     * @param $message
     */
    public static function saveMoxieReport($type,$message){
        $url = "https://tenant.51datakey.com/{$type}/report_data?data={$message}&pdfFlag=0";
        $upload_info = Functions::sendRequest($GLOBALS['app']->get('sendUrlUploadNomal'),['namespace'=>'contract','file_url'=>$url,'ext'=>'html'],false,'post',['Authorization:'.md5(date('Y-m-d'))]);

        $upload_info = json_decode($upload_info,true);

        return $upload_info['result']['url'];

    }

    /**
     * 通过手机号获取归属地
     * @param $mobile
     * @return bool|string
     */
    public static function getPositionByMobile($mobile){
        $config = $GLOBALS['app']->get('AliYun');
        $host = $config['mobile']['url'];
        $path = "/mobile";
        $method = $config['mobile']['method'];
        $appcode = $config['mobile']['appCode'];
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "mobile={$mobile}";
        $bodys = "";
        $url = $host . $path . "?" . $querys;

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $context = curl_exec($curl);
        curl_close($curl);
        return $context;
    }

    /**
     * 通过ip号获取归属地
     * @param $ip
     * @return bool|string
     */
    public static function getPositionByIp($ip){
        $config = $GLOBALS['app']->get('AliYun');
        $host = $config['ip']['url'];
        $method = $config['ip']['method'];
        $appcode = $config['ip']['appCode'];
        $path = "/ip";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "ip={$ip}";
        $bodys = "";
        $url = $host . $path . "?" . $querys;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $context = curl_exec($curl);
        curl_close($curl);
        return $context;
    }
}