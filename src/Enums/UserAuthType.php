<?php

namespace FNP\ElStart\Enums;

enum UserAuthType: int
{
    case PASSWORD  = 10;
    case PASSKEY   = 20;
    case EMAIL     = 30;
    case PHONE     = 40;
    case FACEBOOK  = 50;
    case GOOGLE    = 60;
    case TWITTER   = 70;
    case LINKEDIN  = 80;
    case APPLE     = 90;
    case MICROSOFT = 100;
}
