<?php
/**
 * 图片处理
 *
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 */
class Image {
    /**
     * 取得图片信息
     *
     * @param string $file
     * @return array|bool
     */
    public static function info($file) {
        if (!is_file($file)) return false;
        if ($info = getimagesize($file)) {
            return array(
                'width'  => $info[0],
                'height' => $info[1],
                'type'   => strtolower(image_type_to_extension($info[2], false)),
                'size'   => filesize($file),
                'mime'   => $info['mime']
            );
        }
        return false;
    }
    /**
     * 缩略图
     *
     * 不支持 bmp
     *
     * @param string $image
     * @param int $max_w
     * @param int $max_h
     * @param string $toname
     * @return bool|null
     */
    public static function thumb($image, $max_w=100, $max_h=100, $toname=null) {
        if (IS_SAE) {
            if (preg_match('/\.(gif|jpg|jpe|jpeg|png)$/i', $image, $matches)) {
                $type = $matches[1];
                $image = file_get_contents($image);
            } elseif (substr($image ,0, 3) == "\xFF\xD8\xFF") {
                $type = 'jpg';
            } elseif (substr($image ,0, 8) == "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A") {
                $type = 'png';
            } elseif (substr($image ,0, 4) == "\x47\x49\x46\x38") {
                $type = 'gif';
            }
            $img = new SaeImage();
            $img->setData($image);
            $img->resize($max_w, $max_h);
            if ($toname) {
                list($domain, $filename) = Upload::sae_parse($toname);
                $stor = new SaeStorage();
                $toname = $stor->write($domain, $filename, $img->exec($type));
            } else {
                // 输出 header
                header('Content-type: '.file_mime_type(microtime(true).'.'.$type));
                $img->exec($type, true);
            }
            return $toname;
        }
        // 获取原图信息
        if($info = Image::info($image)) {
            // 原图大小
            $src_w = $info['width']; $src_h = $info['height'];
            $type  = $info['type'] ? $info['type'] : strtolower(pathinfo($image, PATHINFO_EXTENSION));
            // 计算缩放比例
            $scale = max($max_w ? ($max_w / $src_w) : 0, $max_h ? ($max_h / $src_h) : 0);
            // 等比例缩放尺寸
            $width  = round($src_w * $scale); $height = round($src_h * $scale);
            // 缩略图尺寸
            $dst_w = $max_w ? min($max_w, $width) : $width;
            $dst_h = $max_h ? min($max_h, $height) : $height;
            // 载入原图
            $create = 'imagecreatefrom' . ($type == 'jpg' ? 'jpeg' : $type);
            // 不支持的图片格式
            if (!function_exists($create)) return false;
            // 原图句柄
            $srcimg = $create($image);

            // 创建缩略图画布
            if ($type == 'gif') {
                $thumb = imagecreate($dst_w, $dst_h);
            } else {
                $thumb = imagecreatetruecolor($dst_w, $dst_h);
            }

            // 画布需要透明
            if ($type == 'png') {
                // 创建透明画布
                imagealphablending($thumb, true); imagesavealpha($thumb, true);
                imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
            } else {
                $bgcolor = imagecolorallocate($thumb,255,255,255);
                imagefill($thumb,0,0,$bgcolor);
                imagecolortransparent($thumb, $bgcolor);
            }

            // 复制图片
            if (function_exists('imagecopyresampled')) {
                imagecopyresampled($thumb, $srcimg, -(($width-$dst_w)*0.5), -(($height-$dst_h)*0.5), 0, 0, $width, $height, $src_w, $src_h);
            } else {
                imagecopyresized($thumb, $srcimg, -(($width-$dst_w)*0.5), -(($height-$dst_h)*0.5), 0, 0, $width, $height, $src_w, $src_h);
            }

            // 对jpeg图形设置隔行扫描
            if ('jpg' == $type || 'jpeg' == $type) imageinterlace($thumb, 1);

            // 生成图片
            $imagefun = 'image' . ($type == 'jpg' ? 'jpeg' : $type);

            if ($toname) {
                $toname = dirname($image) . '/' . $toname . '.' . pathinfo($image, PATHINFO_EXTENSION);
                mkdirs(dirname($toname)); $imagefun($thumb, $toname);
            } else {
                // 输出 header
                header('Content-type: '.$info['mime']);
                $toname = $image; $imagefun($thumb);
            }
            imagedestroy($thumb); imagedestroy($srcimg);
            return $toname;
         }
         return false;
    }
}
