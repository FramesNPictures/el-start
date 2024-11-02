<?php

namespace FNP\ElStart\Enums;
enum UserDetailType: int
{
    case FULL_NAME = 10;
    case FIRST_NAME = 11;
    case LAST_NAME = 12;
    case PHONE = 13;
    case ADDRESS = 14;
    case CITY = 15;
    case POSTAL_CODE = 16;
    case COUNTRY = 17;
    case BIRTHDAY = 19;
    case GENDER = 20;
    case PHONE_MOBILE = 21;
    case PHONE_WORK = 22;
    case WEBSITE = 28;
    case NICKNAME = 30;
    case LANGUAGE = 31;
    case TIMEZONE = 32;
    case LAST_ACTIVITY = 100;
    case LAST_LOGIN = 101;
    case LAST_LOGOUT = 102;
}