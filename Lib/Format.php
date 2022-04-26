<?php

class Format
{
    /**
     * @param $message_hash_prefix
     * @param $part_number
     * @param $message_partial
     * @param $part_count
     * @return string
     */
    public static function partialMessageFormat($message_hash_prefix, $part_number, $message_partial, $part_count)
    {
        return EXPSS_CODE . "message_hash:" . $message_hash_prefix . ":part:" . $part_number . ":" . $part_count . ":__PARTIAL__" . $message_partial;
    }

    /**
     * @param $message_hash_prefix
     * @return string
     */
    public static function partialCompleteFormat($message_hash_prefix)
    {
        return EXPSS_CODE . "partial_complete:" . $message_hash_prefix;
    }

    /**
     * @param $message_hash_prefix
     * @param $part_number
     * @return string
     */
    public static function partialSuccessFormat($message_hash_prefix, $part_number)
    {
        return EXPSS_CODE . "success:" . $message_hash_prefix . ":" . $part_number;
    }

    /**
     * @param $input
     * @return bool
     */
    public static function isCorrectDecoding($input)
    {
        if (mb_substr($input, 0, mb_strlen(EXPSS_CODE)) == EXPSS_CODE) {
            return true;
        }
        return false;
    }

    /**
     * @param $input
     * @return bool
     */
    public static function partialComplete($input)
    {
        if (mb_substr_count($input, "partial_complete:")) {
            $partial__arr = explode(":", $input);
            return $partial__arr[1];

        }
        return false;
    }

    /**
     * @param $input
     * @return mixed|string
     */
    public static function partialMessageHash($input)
    {
        $partial__arr = explode(":", $input);
        return $partial__arr[1];
    }

    /**
     * @param $input
     * @return string
     */
    public static function realMessage($input)
    {
        return mb_substr($input, mb_strlen(EXPSS_CODE));
    }

    /**
     * @param $message
     * @return false|string
     */
    public static function getMessageHash($message)
    {
        return  substr(md5($message), 0, 7);
    }

    /**
     * @param int $int
     * @return string
     */
    public static function partBlockName($int)
    {
        return sprintf("%06s", $int);
    }


}