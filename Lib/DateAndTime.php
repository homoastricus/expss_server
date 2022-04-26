<?php

class DateAndTime
{
    public static function days_later($day_in_seconds, $real_time = '')
    {
        $days = floor($day_in_seconds / 86400);
        if ($days == 0) {
            if ($day_in_seconds <= 60) {
                return $day_in_seconds . ' second(s) ago';
            } else {
                if ($day_in_seconds > 60 and $day_in_seconds <= 3600) {
                    $min = floor($day_in_seconds / 60);
                    return $min . " minute(s) later";
                }
            }
            if ($day_in_seconds > 3600 and $day_in_seconds <= 86400) {
                $hour = floor($day_in_seconds / 3600);
                return $hour . " hour(s) later";
            }
        } elseif ($days == 1) {
            return "yesterday at " . self::only_time($real_time);
        } elseif ($days > 1 and $days < 30) {
            return $days . "  day(s)  later";
        } elseif ($days >= 30 and $days < 365) {
            $month = floor($days / 30);

            return $month . " month(s) later";
        } elseif ($days >= 365) {
            $years = floor($days / 365);

            return $years . "  year(s) later";
        }
    }

    public static function day_separator($day_in_seconds, $days_only = false)
    {
        $viewed_string = "";
        $days          = floor($day_in_seconds / 86400);

        if ($days > 0) {
            $viewed_string .= $days . " days(s)";
            $left          = $day_in_seconds - $days * 86400;
        } else {
            $left = $day_in_seconds;
        }

        if ($days_only) {
            if (empty($viewed_string)) {
                $viewed_string = L('1ST_DAY');
            }

            return $viewed_string;
        }

        if ($days > 1) {
            return $viewed_string;
        }

        $viewed_string .= " ";
        $hours         = floor($left / 3600);
        if ($hours > 0) {

            $viewed_string .= $hours . " hour(s)";
            $left          = $left - $hours * 3600;
        }

        $viewed_string .= " ";
        $minutes       = floor($left / 60);
        if ($minutes > 0) {
            $viewed_string .= $minutes . " minute(s)";
            $left          = $left - $minutes * 60;
        }

        $seconds       = $left;
        $viewed_string .= " ";
        if ($seconds > 0) {
            $viewed_string .= $seconds . " sec(s)";
        }

        return $viewed_string;

    }

    public static function only_date($date)
    {
        return substr($date, 0, 10);
    }

    public static function only_time($date)
    {
        return substr($date, 11, 5);
    }

}