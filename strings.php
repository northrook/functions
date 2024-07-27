<?php

/* Core String Functions

 License: MIT
 Copyright (c) 2024
 Martin Nielsen <mn@northrook.com>

*/

declare( strict_types = 1 );

namespace Northrook;


function stringExplode(
    string $separator,
    mixed  $string,
    bool   $filter = true,
    int    $limit = PHP_INT_MAX,
) : array {
    $exploded = \explode( $separator, toString( $string ) );
    return $filter ? arrayFilter( $exploded ) : $exploded;
}

/**
 * This function tries very hard to return a string from any given $value.
 *
 * @param mixed   $value
 * @param string  $separator
 * @param bool    $filter
 *
 * @return string
 */
function toString( mixed $value, string $separator = '', bool $filter = true ) : string {

    if ( isScalar( $value ) ) {
        return (string) $value;
    }

    if ( \is_array( $value ) || $value instanceof \ArrayAccess || $value instanceof \Iterator ) {
        $array = \iterator_to_array( $value );
        return \implode( $separator, $filter ? arrayFilter( $array ) : $array );
    }

    if ( \is_object( $value ) ) {
        try {
            return \serialize( $value );
        }
        catch ( \Throwable ) {
            return $value::class;
        }
    }

    return (string) $value;
}

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


/**
 * Throws a {@see \LengthException} when the length of `$string` exceeds the provided `$limit`.
 *
 * @param string       $string
 * @param int          $limit
 * @param null|string  $caller  Class, method, or function name
 *
 * @return void
 */
function stringCharacterLimit(
    string  $string,
    int     $limit,
    ?string $caller = null,
) : void {
    $limit  = \PHP_MAXPATHLEN - 2;
    $length = \strlen( $string );
    if ( $length > $limit ) {
        throw new \LengthException (
            $caller
                ? $caller . " resulted in a $length character string, exceeding the $limit limit."
                : "The provided string is $length characters long, exceeding the $limit limit.",
        );
    }
}