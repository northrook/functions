<?php

/* Core Boolean Functions

 License: MIT
 Copyright (c) 2024
 Martin Nielsen <mn@northrook.com>

*/

declare( strict_types = 1 );

namespace Northrook;


/**
 * # Determine if a value is a scalar.
 *
 * @param mixed  $value
 *
 * @return bool
 */
function isScalar( mixed $value ) : bool {
    return \is_scalar( $value ) || $value instanceof \Stringable || \is_null( $value );
}


function isEmail( mixed $value, ?string ...$enforceDomain ) : bool {

    // Can not be an empty string
    if ( !$value || !is_string( $value ) ) {
        return false;
    }

    // Emails are case-insensitive, lowercase the $value for processing
    $value = \strtolower( (string) $value );

    // Must contain an [at] and at least one period
    if ( !str_contains( $value, '@' ) || !str_contains( $value, '.' ) ) {
        return false;
    }

    // Must end with a letter
    if ( !\preg_match( '/[a-z]/', $value[ -1 ] ) ) {
        return false;
    }

    // Must only contain valid characters
    if ( \preg_match( '/[^' . FILTER_URL_SAFE_CHARACTERS_UNICODE . ']/u', $value ) ) {
        return false;
    }

    // Validate domains, if specified
    foreach ( $enforceDomain as $domain ) {
        if ( \str_ends_with( $value, \strtolower( $domain ) ) ) {
            return true;
        }
    }

    return true;
}


function isUrl( mixed $value, ?string $requiredProtocol = null ) : bool {

    // Can not be an empty string
    if ( !$value || !is_string( $value ) ) {
        return false;
    }

    // Must not start with a number
    if ( \is_int( $value[ 0 ] ) ) {
        return false;
    }

    // Must only contain valid characters
    if ( !\preg_match( '/([\w\-+]*?[:\/]{2}).+\.[a-z0-9]{2,}/', $value ) ) {
        return false;
    }

    // Check for required protocol if requested
    if ( $requiredProtocol && !\str_starts_with( $value, \rtrim( $requiredProtocol, ':/' ) . '://' ) ) {
        return false;
    }

    return true;
}