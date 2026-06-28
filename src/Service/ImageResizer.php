<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service;

class ImageResizer
{
    public function resize(string $source, string $destination, int $newWidth): bool
    {
        $info = getimagesize($source);
        if (!$info) {
            return false;
        }

        list($width, $height) = $info;
        $mime = $info['mime'];

        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($source);
                break;
            default:
                return false;
        }

        $ratio = $height / $width;
        $newHeight = (int) ($newWidth * $ratio);

        $thumb = imagecreatetruecolor($newWidth, $newHeight);

        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($thumb, $destination, 85);
                break;
            case 'image/png':
                imagepng($thumb, $destination, 8);
                break;
            case 'image/webp':
                imagewebp($thumb, $destination, 85);
                break;
        }

        imagedestroy($image);
        imagedestroy($thumb);

        return true;
    }
}
