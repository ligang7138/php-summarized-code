<?php


use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode as Code;
require_once ('../vendor/composer/');
/**
 * 关键字
 * 图片合成 gd库 二维码生成 图片与base64相互转换
 * Class qrCode
 */
class qrCode
{
    /**
     * @param $codeMsg 二维码内容信息
     * @param $type 默认false 将图片存储到远程oss服务器 true直接输出base64格式图片
     * @return array
     */
    public static function createQr($codeMsg,$type = false){
        $qrCode = new Code(self::aesCrypt($codeMsg));
        $qrCode->setSize(590);
        $qrCode->setMargin(50);
        $qrCode->setEncoding('UTF-8');
        $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevel(ErrorCorrectionLevel::HIGH));
        $qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
        $qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);
        print_r($qrCode);die;
        $logoPath = $this->getParameter('admin_bundle')['logo_path'];
        if($logoPath){
            $qrCode->setLogoPath($logoPath);
            $qrCode->setLogoSize(150, 150);
        }

        $pngData = $qrCode->writeDataUri();

        if(!$type){
            $uploadUrl = $this->getParameter('admin_bundle')['uploadFileBase64'];
            $result = CommonFunction::sendFile($uploadUrl,[
                "ext" => "png",
                "content" => $pngData
            ],['authorization' => md5(date('Y-m-d'))]);
            $result1 = json_decode($result,true);

            if($result1['status'] != 2000){
                return ['code' => 500,'msg' => '二维码生成失败'];
            }
        }


        $basePicPath = $this->getParameter('admin_bundle')['base_pic_path'];

        $dst = imagecreatefromstring(file_get_contents($basePicPath)); // 背景图

        // $pngData为base64格式，file_get_contents可以获取并生成图片资源
        $src = imagecreatefromstring(file_get_contents($pngData)); // 二维码图

        list($src_w, $src_h) = getimagesize($pngData);
        list($dst_w, $dst_h) = getimagesize($basePicPath);

        // 将两个图片合并
        imagecopymerge($dst, $src, ($dst_w-$src_w)/2,300, 0, 0, $src_w, $src_h, 100);
        // 创建颜色
        $white = imagecolorallocate($dst, 255, 255, 255);
        $grey = imagecolorallocate($dst, 128, 128, 128);
        $black = imagecolorallocate($dst, 0, 0, 0);
//        imagefilledrectangle($dst, 0, 0, 399, 29, $white);

        $font = $this->getParameter('admin_bundle')['ttf_path'];
        $text = $partner_name;
        $font_size = 43;

        $box = imagettfbbox($font_size,0,$font,$text);
        $len = $box[2] - $box[0];

        $x = ceil(($dst_w - $len) / 2); //计算文字的水平位置
        $y = ($dst_h+$src_h)/2+25; //计算文字的垂直位置

        imagettftext($dst, $font_size, 0, $x, $y, $white, $font,$text);//写入文字
        //如果需要加粗,让x坐标加1
        imagettftext($dst, $font_size, 0, $x+1, $y, $white, $font,$text);

        $img = "{$partner_id}.png";
        if(!imagepng($dst,$img)){
            return ['code' => 500,'msg' => '二维码生成失败'];
        }
        imagedestroy($dst);
        imagedestroy($src);
        $content = self::Base64EncodeImage($img);
        if(!$type){
            if(!$content){
                return ['code' => 500,'msg' => '二维码转base64失败'];
            }else{
                unlink($img);
            }

            $result = self::sendFile($uploadUrl,[
                "ext" => "png",
                "content" => $content
            ],['authorization' => md5(date('Y-m-d'))]);
            $result2 = json_decode($result,true);

            if($result2['status'] == 2000){
                return [
                    'code' => 200,
                    'qr_url' => $result1['result']['url'],
                    'down_url' => $result2['result']['url'],
                ];
            }
            return ['code' => 500,'msg' => 'oss存储二维码失败'];
        }else{
            return [
                'qr_url' => $pngData,
                'down_url' => $content,
            ];
        }

        //第一种：图片输出到页面
        /*switch ($dst_type) {
          case 1://GIF
              header('Content-Type: image/gif');
              imagegif($dst);
              break;
          case 2://JPG
              header('Content-Type: image/jpeg');
              imagejpeg($dst);
              break;
          case 3://PNG
              header('Content-Type: image/png');
              imagepng($dst);
              break;
          default:
              break;
      }
      imagedestroy($dst);
      imagedestroy($src);*/

        //第二种：输出到本地文件夹，返回生成图片的路径
        $fileName= md5(rand(0,999));
        $EchoPath='./images/'.$fileName.'.png';
        imagepng($dst,$EchoPath);
        imagedestroy($dst);
        return $EchoPath;
    }


    /**
     * 在使用phpcurl post数据的时候，当数据超过1k的时候，会失败，不会直接发起请求，而是分为两步：
        一，发送一个请求，包含“Expect:100-continue”头域，询问SERVER是否愿意接收
        二，接收到SERVER返回的 100-continue应答以后，才可以继续POST数据
       解决办法：
        添加curl请求头  curl_setopt($ch,CURLOPT_HTTPHEADER,array(“Expect:”));
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
        curl_setopt($curl,CURLOPT_HTTPHEADER,array("Expect:"));
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); // if any redirection after upload

        $file_contents = curl_exec($curl);

        curl_close($curl);
        return $file_contents;
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

    /**
     * 图片转base64
     * @param ImageFile String 图片路径
     * @return 转为base64的图片
     */
    public static function base64EncodeImage($ImageFile) {
        if(file_exists($ImageFile) || is_file($ImageFile)){
            // chunk_split 生成的base64字符串中有\r\n
            /*$base64_image = '';
            $image_info = getimagesize($ImageFile);
            $image_data = fread(fopen($ImageFile, 'r'), filesize($ImageFile));
            $base64_image = 'data:' . $image_info['mime'] . ';base64,' . chunk_split(base64_encode($image_data));*/
            $img_info = getimagesize($ImageFile);
            $base64_image = "data:{$img_info['mime']};base64," . base64_encode(file_get_contents($ImageFile));
            return $base64_image;
        }
        else{
            return false;
        }
    }

    /**
     * @param $base64Str base64格式的图片字符串数据
     * @param $path 保存的文件路径和文件名(不用带扩展名 自动匹配)
     */
    public static function base64ToImg($base64Str,$path){
        $arr = explode(',',$base64Str);
        $bin = base64_decode($arr[1]);
        $ext = self::getImgExt($bin);//获取真实扩展名
        if($ext !== false){
            file_put_contents($path . '.' . $ext,$bin);
        }else{
            exit('图片格式非法');
        }
    }

    /**
     * 获取图片文件的扩展名 如果不是图片数据则返回false
     * @param $bin 二进制图片数据流
     * @return bool|int|string 图片扩展名
     */
    public static function getImgExt($bin){

        $bits = array(
            'jpg' => "\xFF\xD8\xFF",
            'gif' => "GIF",
            'png' => "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a",
            'bmp' => 'BM',
        );

        foreach ($bits as $type => $bit) {
            if (substr($bin, 0, strlen($bit)) === $bit) {
                return $type;
            }
        }
        return false;

    }
}

qrCode::createQr('lsjdf');