<?php

/* Core String Functions

 License: MIT
 Copyright (c) 2024
 Martin Nielsen <mn@northrook.com>

*/

declare( strict_types = 1 );

namespace Northrook;



function stringAfter( string $string, string $substring, bool $first = false ) : string
{
    if ( !\str_contains( $string, $substring ) ) {
        return $string;
    }

    $offset = $first ? \strpos( $string, $substring ) : \strrpos( $string, $substring );

    if ( $offset === false ) {
        return $string;
    }
    else {
        $offset += \strlen( $substring );
    }

    return \substr( $string, $offset );
}

function stringBefore( string $string, string $substring, bool $first = false ) : string
{
    if ( !\str_contains( $string, $substring ) ) {
        return $string;
    }
    $offset = $first ? \strpos( $string, $substring ) : \strrpos( $string, $substring );

    if ( $offset === false ) {
        return $string;
    }
    // else {
    //     $offset += \strlen( $substring );
    // }

    return \substr( $string, 0, $offset );
}

function stringStartsWith( string $string, string | array $substring, bool $caseSensitive = false ) : bool
{
    if ( !$caseSensitive ) {
        $string = \strtolower( $string );
    }

    foreach ( (array) $substring as $substring ) {
        if ( \str_starts_with( $string, $caseSensitive ? $substring : \strtolower( $substring ) ) ) {
            return true;
        }
    }

    return false;
}

function stringEndsWith( string $string, string | array $substring, bool $caseSensitive = false ) : bool
{
    if ( !$caseSensitive ) {
        $string = \strtolower( $string );
    }

    foreach ( (array) $substring as $substring ) {
        if ( \str_ends_with( $string, $caseSensitive ? $substring : \strtolower( $substring ) ) ) {
            return true;
        }
    }

    return false;
}

function stringStart( string $subject, string $substring, ?string $separator = null ) : string
{
    if ( \str_starts_with( $subject, $substring ) ) {
        return $subject;
    }
    return $substring . $separator . $subject;
}

function stringEnd( string $subject, string $substring, ?string $separator = null ) : string
{
    if ( \str_ends_with( $subject, $substring ) ) {
        return $subject;
    }
    return $subject . $separator . $substring;
}

/**
 * @param string  $string
 * @param string  $substring
 * @param bool    $first
 * @param bool    $includeSubstring
 *
 * @return array{string, ?string}
 */
function stringSplit(
        string $string,
        string $substring,
        bool   $first = true,
        bool   $includeSubstring = true,
) : array
{
    if ( !\str_contains( $string, $substring ) ) {
        return [ $string, null ];
    }

    $offset = $first ? \strpos( $string, $substring ) : \strrpos( $string, $substring );

    if ( $offset === false ) {
        trigger_error(
                __FUNCTION__ . " could not split '$substring' using '$substring'.\nOffset position could not be determined.",
                E_USER_WARNING,
        );
        return [ $string, null ];
    }

    if ( $first ) {
        $offset = $includeSubstring ? $offset + \strlen( $substring ) : $offset;
    }
    else {
        $offset = $includeSubstring ? $offset : $offset - \strlen( $substring );
    }

    $before = \substr( $string, 0, $offset );
    $after  = \substr( $string, $offset );

    return [
            $before,
            $after,
    ];
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
) : void
{
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