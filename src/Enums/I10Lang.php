<?php

namespace FNP\ElStart\Enums;

enum I10Lang
{
    case EN;
    case DE;
    case FR;
    case ES;
    case IT;
    case PT;
    case NL;
    case SE;
    case NO;
    case FI;
    case DK;
    case PL;
    case CS;
    case SK;
    case HU;
    case RO;
    case BG;
    case SL;
    case HR;
    case SR;
    case BS;
    case SQ;
    case MK;
    case LT;
    case LV;
    case EE;
    case RU;
    case GR;
    case TR;

    public static function active(): I10Lang
    {
        $lang = app()->getLocale();
        $lang = strtoupper(substr($lang, 0, 2));
        return constant("self::$lang");
    }

    public function name(): string
    {
        return match ($this) {
            self::EN => __('English'),
            self::DE => __('German'),
            self::FR => __('French'),
            self::ES => __('Spanish'),
            self::IT => __('Italian'),
            self::PT => __('Portuguese'),
            self::NL => __('Dutch'),
            self::SE => __('Swedish'),
            self::NO => __('Norwegian'),
            self::FI => __('Finnish'),
            self::DK => __('Danish'),
            self::PL => __('Polish'),
            self::CS => __('Czech'),
            self::SK => __('Slovak'),
            self::HU => __('Hungarian'),
            self::RO => __('Romanian'),
            self::BG => __('Bulgarian'),
            self::SL => __('Slovene'),
            self::HR => __('Croatian'),
            self::SR => __('Serbian'),
            self::BS => __('Bosnian'),
            self::SQ => __('Albanian'),
            self::MK => __('Macedonian'),
            self::LT => __('Lithuanian'),
            self::LV => __('Latvian'),
            self::EE => __('Estonian'),
            self::RU => __('Russian'),
            self::GR => __('Greek'),
            self::TR => __('Turkish'),
        };
    }

}
