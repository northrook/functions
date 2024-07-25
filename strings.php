<?php

/* Core String Functions

 License: MIT
 Copyright (c) 2024
 Martin Nielsen <mn@northrook.com>

*/

declare( strict_types = 1 );

namespace Northrook;

function squish( string $string ) : string {
    return \preg_replace( '# +#', ' ', $string );
}

function stringContains(
    string         $string,
    string | array $needle,
    bool           $returnNeedles = false,
    bool           $containsOnlyOne = false,
    bool           $containsAll = false,
    bool           $caseSensitive = false,
) : bool | int | array | string {

    $count    = 0;
    $contains = [];

    $find = static fn ( string $string ) => $caseSensitive ? $string : strtolower( $string );

    $string = $find( $string );

    if ( is_string( $needle ) ) {
        $count = substr_count( $string, $find( $needle ) );
    }
    else {
        foreach ( $needle as $index => $value ) {
            $match = substr_count( $string, $find( $value ) );
            if ( $match ) {
                $contains[] = $value;
                $count      += $match;
                unset( $needle[ $index ] );
            }
        }
    }

    if ( $containsOnlyOne && count( $contains ) !== 1 ) {
        return false;
    }

    if ( $containsAll && empty( $needle ) ) {
        return true;
    }

    if ( $returnNeedles ) {
        return ( count( $needle ) === 1 ) ? $needle[ 0 ] : $needle;
    }

    return $count;
}