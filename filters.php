<?php

/* Core Filter Functions

 License: MIT
 Copyright (c) 2024
 Martin Nielsen <mn@northrook.com>

*/

declare( strict_types = 1 );

namespace Northrook;

const FILTER_URL_SAFE_CHARACTERS_UNICODE = "\w.,_~:;@!$&*?#=%()+\-\[\]\'\/";
const FILTER_URL_SAFE_CHARACTERS         = "A-Za-z0-9.,_~:;@!$&*?#=%()+\-\[\]\'\/";

function filterHtml( string $string ) : string {
    return \htmlspecialchars( $string, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Escapes string for use inside HTML text.
 */
function filterHtmlText( string $string ) : string {
    return \htmlspecialchars( $string, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Escapes string for use inside CSS template.
 */
function filterCssString( string $string ) : string {
    // http://www.w3.org/TR/2006/WD-CSS21-20060411/syndata.html#q6
    return \addcslashes( $string, "\x00..\x1F!\"#$%&'()*+,./:;<=>?@[\\]^`{|}~" );
}

/**
 * Escapes variables for use inside <script>.
 */
function filterJsString( mixed $variable ) : string {
    $json = \json_encode( $variable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
    if ( \json_last_error() ) {
        throw new \RuntimeException( \json_last_error_msg() );
    }

    return \str_replace( [ ']]>', '<!', '</' ], [ ']]\u003E', '\u003C!', '<\/' ], $json );
}

/**
 * Filter a string assuming it a URL.
 *
 * - Preserves unicode characters.
 * - Removes tags by default.
 *
 * @param string  $string
 * @param bool    $preserveTags
 *
 * @return string
 */
function filterUrl( string $string, bool $preserveTags = false ) : string {

    static $cache = [];
    return $cache[ \json_encode( [ $string, $preserveTags ], 832 ) ] ??= (
    static function () use ( $string, $preserveTags ) : string {

        $safeCharacters = FILTER_URL_SAFE_CHARACTERS_UNICODE;

        if ( $preserveTags ) {
            $safeCharacters .= '{}|^`"><@';
        }

        return \preg_replace(
            pattern     : "/[^$safeCharacters]/u",
            replacement : EMPTY_STRING,
            subject     : $string,
        );
    } )();
}