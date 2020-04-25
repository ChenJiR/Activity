<?php

class Helper
{
    public static function NowTime()
    {
        return date('Y-m-d H:i:s');
    }

    public static function NowDate()
    {
        return date('Y-m-d');
    }

    static function convertUTF8($str)
    {
        if (!$str) return $str;
        $encode = mb_detect_encoding($str, ['ASCII', 'GB2312', 'GBK', 'UTF-8']);
        return $encode == 'UTF-8' ? $str : mb_convert_encoding($str, 'utf-8', $encode);
    }

    public static function pregMatch($value, $type)
    {
        $value = self::convertUTF8(strval($value));
        switch ($type) {
            case 'phone' :
                $preg = "/^[1][3,4,5,6,7,8,9][0-9]{9}$/";
                break;
            default :
                return false;
        }
        return preg_match($preg, $value);
    }

    //判断字符串中是否含有emoji
    public static function has_Emoji($str)
    {
        if (!$str) {
            return false;
        }
        $mat = [];
        preg_match_all('/./u', $str, $mat);
        foreach ($mat[0] as $v) {
            if (strlen($v) >= 4) {
                return true;
            }
        }
        return false;
    }

    /**
     * XSS过滤
     * @param string|array $content
     * @param $strict
     * @return bool
     */
    public static function clean_xss(&$content, $strict = false)
    {
        if (!is_array($content)) {
            $content = trim($content);
            $content = strip_tags($content);
            $content = htmlspecialchars($content);
            $content = addslashes($content);
            if (!$strict) {
                return true;
            }
            $content = str_replace(array('"', "\\", "'", "/", "..", "../", "./", "//"), '', $content);
            $no = '/%0[0-8bcef]/';
            $content = preg_replace($no, '', $content);
            $no = '/%1[0-9a-f]/';
            $content = preg_replace($no, '', $content);
            $no = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';
            $content = preg_replace($no, '', $content);
            return true;
        } else {
            foreach ($content as $k => &$v) {
                self::clean_xss($v, $strict);
            }
            return true;
        }
    }

}