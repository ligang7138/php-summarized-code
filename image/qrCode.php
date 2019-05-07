<?php


use AdminBundle\Common\CommonFunction;
use Endroid\QrCode\ErrorCorrectionLevel;

/**
 * 关键字
 * 图片合成 gd库 二维码生成
 * Class qrCode
 */
class qrCode
{
    /**
     * @param $codeMsg 二维码内容信息
     * @param $type 默认false 将图片存储到远程oss服务器 true直接输出base64格式图片
     * @return array
     */
    public function createQr($codeMsg,$type = false){
        $qrCode = new \Endroid\QrCode\QrCode(CommonFunction::aesCrypt($codeMsg));
        $qrCode->setSize(590);
        $qrCode->setMargin(50);
        $qrCode->setEncoding('UTF-8');
        $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevel(ErrorCorrectionLevel::HIGH));
        $qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
        $qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);
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
        // 用白色写文字
        $white = imagecolorallocate($dst, 255, 255, 255);

        $font = $this->getParameter('admin_bundle')['ttf_path'];
        $text = $partner_name;
        $font_size = 43;

        $box = imagettfbbox($font_size,0,$font,$text);
        $len = $box[2] - $box[0];

        $x = ceil(($dst_w - $len) / 2); //计算文字的水平位置
        $y = ($dst_h+$src_h)/2+25; //计算文字的垂直位置

        imagettftext($dst, $font_size, 0, $x, $y, $white, $font,$text);//写入文字

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
        curl_setopt($curl,CURLOPT_HTTPHEADER,array("Expect:"));
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); // if any redirection after upload

        $file_contents = curl_exec($curl);

        curl_close($curl);
        return $file_contents;
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
}