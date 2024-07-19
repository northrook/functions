<?php

/* Core Escape Functions

 License: MIT
 Copyright (c) 2024
 Martin Nielsen <mn@northrook.com>

*/

declare( strict_types = 1 );

namespace Northrook;

/**
 * Escapes string for use everywhere inside HTML (except for comments).
 */
function escapeHtml( null | string | \Stringable $string ) : string {
    return htmlspecialchars( (string) $string, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8' );
}


/**
 * Escapes string for use inside HTML text.
 */
function escapeHtmlText( null | string | \Stringable $string ) : string {

    $string = htmlspecialchars( (string) $string, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' );
    $string = strtr( $string, [ '{{' => '{<!-- -->{', '{' => '&#123;' ] );
    dump( $string );
    return $string;
}

/**
 * Escapes string for use inside HTML attribute value.
 */
function escapeHtmlAttr( null | string | \Stringable $string, bool $double = true ) : string {

    $string = (string) $string;

    if ( str_contains( $string, '`' ) && strpbrk( $string, ' <>"\'' ) === false ) {
        $string .= ' '; // protection against innerHTML mXSS vulnerability nette/nette#1496
    }

    $string = htmlspecialchars( $string, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', $double );
    $string = str_replace( '{', '&#123;', $string );
    dump( $string );
    return $string;
}


/**
 * Escapes string for use inside CSS template.
 */
function escapeCss( $s ) : string {
    // http://www.w3.org/TR/2006/WD-CSS21-20060411/syndata.html#q6
    return addcslashes( (string) $s, "\x00..\x1F!\"#$%&'()*+,./:;<=>?@[\\]^`{|}~" );
}


/**
 * Escapes variables for use inside <script>.
 */
function escapeJs( mixed $s ) : string {

    $json = json_encode( $s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
    if ( $error = json_last_error() ) {
        throw new \RuntimeException( json_last_error_msg() );
    }

    return str_replace( [ ']]>', '<!', '</' ], [ ']]\u003E', '\u003C!', '<\/' ], $json );
}


/**
 * Escapes string for use inside iCal template.
 */
function escapeICal( $s ) : string {
    // https://www.ietf.org/rfc/rfc5545.txt
    $s = str_replace( "\r", '', (string) $s );
    $s = preg_replace( '#[\x00-\x08\x0B-\x1F]#', "\u{FFFD}", (string) $s );
    return addcslashes( $s, "\";\\,:\n" );
}

/**
 * Sanitizes string for use inside href attribute.
 */
function escapeUrl( null | string | \Stringable $string ) : string {

    // Sanitize the URL, preserving tags for escaping
    $string = filterUrl( (string) $string, true );

    // Escape special characters including tags
    return \htmlspecialchars( $string, ENT_QUOTES, 'UTF-8' );
}


/**
 * Escape each and every character in the provided string.
 *
 * ```
 *  escapeCharacters( 'Hello!' );
 *  // => '\H\e\l\l\o\!'
 * ```
 *
 * @param string  $string
 *
 * @return string
 */
function escapeCharacters( string $string ) : string {
    return \implode( '', \array_map( static fn ( $char ) => '\\' . $char, \str_split( $string ) ) );
}