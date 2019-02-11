
<?php
header("Content-type: text/html; charset=utf-8");
 
class Common_util_pub
{ 
    public function sendhongbaoto($arr){
        $data['mch_id'] = ' ';
        $data['mch_billno'] = uniqid($data['mch_id'].date("YmdHis",time()).rand(1111,9999));// 商户订单号
        $data['nonce_str'] = uniqid();
        $data['re_openid'] = $arr['openid'];// 红包接受方openid
        $data['wxappid'] = '';
        $data['nick_name'] = $arr['hbname'];// 提供方
        $data['send_name'] = $arr['hbname'];// 发送公众号
        $data['total_amount'] = $arr['fee']*100;// 金额乘100，微信红包单位：分
        $data['min_value'] = $arr['fee']*100;
        $data['max_value'] = $arr['fee']*100;
        $data['total_num'] = 1; // 红包数
        $data['client_ip'] = $_SERVER['REMOTE_ADDR'];// 请求红包接口的ip地址
        $data['act_name'] = '';// 活动名称
        $data['remark'] = '';// 备注
        $data['wishing'] = $arr['body'];// 红包祝福语

        $result = [];
        if(empty($data['re_openid'])){
            $result['return_msg'] = '用户openid不能为空';
            return $result;
        }

        $data['sign'] = $this->getSign($data); // 生成签名
        $xml = $this->arrayToXml($data);
        $url = '';
        $re = $this->wxHttpsRequestPem($xml,$url);
        $result = $this->xmlToArray($re);
        return $result;

    }


    /**
      *作用：array转xml
     * @param $arr
     * @return string
     */
    public  function arrayToXml($arr){
            $xml = "<xml>";
            foreach ($arr as $key=>$val)
            {
                 if (is_numeric($val))
                 {
                    $xml.="<".$key.">".$val."</".$key.">";

                 }else{
                    $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
                 }
            }
            $xml.="</xml>";
            return $xml;
        }
    /**
     *  作用：将xml转为array
     * @param $xml
     * @return array
     */
    public function xmlToArray($xml)
    {      
        //将XML转为array       
        $array_data = (array)simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);  
        return $array_data;
    }
    /**
     * 发送http请求方法
     * @param $vars
     * @param $url
     * @param int $second
     * @return bool|string
     */
    public function wxHttpsRequestPem( $vars,$url, $second=30){
        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_URL,$url);
        //证书密钥
        curl_setopt($ch,CURLOPT_SSLCERT,dirname(__FILE__).'/cert/apiclient_cert.pem');
        curl_setopt($ch,CURLOPT_SSLKEY,dirname(__FILE__).'/cert/apiclient_key.pem');
        //CA证书目录
        curl_setopt($ch,CURLOPT_CAINFO,dirname(__FILE__).'/cert/rootca.pem');

        if(stripos($url,"https://")!==FALSE){
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }else{
            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
            curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
        }

        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$vars);
        $data = curl_exec($ch);
        if($data){
            curl_close($ch);
            return $data;
        }else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }

    }



    /**
     * 生成签名
     * @param $obj
     * @return string
     */
    public function getSign($obj)
    {
        foreach ($obj as $k => $v)
        {
            $parameters[$k] = $v;
        }
        ksort($parameters);
        $string = $this->formatBizQueryParaMap($parameters, false);
        $string = $string."&key=".KEY; // 商户后台设置的key
        $string = md5($string);
        $result = strtoupper($string);
        return $result;
    }

     /**
     * 格式化参数，签名过程需要使用
     * @param $paraMap
     * @param $urlencode
     * @return bool|string
     */
    function formatBizQueryParaMap($paraMap, $urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v)
        {
            if($urlencode)
            {
               $v = urlencode($v);
            }
            //$buff .= strtolower($k) . "=" . $v . "&";
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar='';
        if (strlen($buff) > 0)
        {
            $reqPar = substr($buff, 0, strlen($buff)-1);
        }
        return $reqPar;
    }
    
}