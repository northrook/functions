<?php

/* Core Constants

 License: MIT
 Copyright (c) 2024
 Martin Nielsen <mn@northrook.com>

*/

declare( strict_types = 1 );

namespace Duration {

    const
    EPHEMERAL = -1,
    AUTO      = null,
    FOREVER   = 0,
    MINUTE    = 60,
    HOUR      = 3600,
    HOUR_4    = 14400,
    HOUR_8    = 28800,
    HOUR_12   = 43200,
    DAY       = 86400,
    WEEK      = 604800,
    MONTH     = 2592000,
    YEAR      = 31536000;
}

namespace Northrook {

    const
    TAB          = "\t",
    EMPTY_STRING = '',
    WHITESPACE   = ' ';
}