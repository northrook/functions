<?php

/* Core Functions

 License: MIT
 Copyright (c) 2024
 Martin Nielsen <mn@northrook.com>

*/

declare( strict_types = 1 );

namespace Northrook;

const EMPTY_STRING = '';

/**
 * Retrieves the project root directory.
 *
 * - This function assumes the Composer directory is present in the project root.
 * - The return is cached for this process.
 *
 * @return string
 */
function getProjectRootDirectory() : string {
    static $projectRoot;
    return $projectRoot ??= (
    static function () : string {
        // Get an array of each directory leading to this file
        $explodeCurrentDirectory = \explode( DIRECTORY_SEPARATOR, __DIR__ );
        // Slice off three levels, in this case /core/northrook/composer-dir, commonly /vendor
        $vendorDirectory = \array_slice( $explodeCurrentDirectory, 0, -3 );
        // Implode and return the $projectRoot path
        return \implode( DIRECTORY_SEPARATOR, $vendorDirectory );
    }
    )();
}

function memoize( mixed $key, callable $callback ) : mixed {
    static $cache = [];
    return $cache[ encodeKey( $key ) ] ??= $callback();
}

function timestamp(
    string | \DateTimeInterface $dateTime = 'now',
    string | \DateTimeZone      $timezone = 'UTC',
) : \DateTimeImmutable {
    try {
        return new \DateTimeImmutable( $dateTime, \timezone_open( $timezone ) ?: null );
    }
    catch ( \Exception $exception ) {
        throw new \InvalidArgumentException(
            message  : "Unable to create a new DateTimeImmutable object for $timezone.",
            code     : 500,
            previous : $exception,
        );
    }
}

/** Replace each key from `$map` with its value, when found in `$content`.
 *
 * @param array         $map  search:replace
 * @param string|array  $content
 * @param bool          $caseSensitive
 *
 * @return array|string|string[] The processed `$content`, or null if `$content` is empty
 */
function replaceEach(
    array          $map,
    string | array $content,
    bool           $caseSensitive = true,
) : string | array {

    if ( !$content ) {
        return $content;
    }

    $keys = \array_keys( $map );

    return $caseSensitive
        ? \str_replace( $keys, $map, $content )
        : \str_ireplace( $keys, $map, $content );
}

/**
 * # Ensure a number is within a range.
 *
 * @param int|float  $number
 * @param int|float  $ceil
 * @param int|float  $floor
 *
 * @return int|float
 */
function numberWithin( int | float $number, int | float $ceil, int | float $floor ) : int | float {
    return match ( true ) {
        $number >= $ceil => $ceil,
        $number < $floor => $floor,
        default          => $number
    };
}

/**
 * # Get the class name of a provided class, or the calling class.
 *
 * - Will use the `debug_backtrace()` to get the calling class if no `$class` is provided.
 *
 * ```
 * $class = new \Northrook\Core\Env();
 * classBasename( $class );
 * // => 'Env'
 * ```
 *
 *
 * @param class-string|object|null  $class
 *
 * @return string
 */
function classBasename( string | object | null $class = null ) : string {
    $class     ??= \debug_backtrace()[ 1 ] [ 'class' ];
    $class     = \is_object( $class ) ? $class::class : $class;
    $namespace = \strrpos( $class, '\\' );
    return $namespace ? \substr( $class, ++$namespace ) : $class;
}

/**
 * # Get all the classes, traits, and interfaces used by a class.
 *
 *
 * @param null|string|object  $class
 * @param bool                $includeSelf
 * @param bool                $includeInterface
 * @param bool                $includeTrait
 * @param bool                $namespace
 * @param bool                $details
 *
 * @return array
 */
function extendingClasses(
    string | object | null $class = null,
    bool                   $includeSelf = true,
    bool                   $includeInterface = true,
    bool                   $includeTrait = true,
    bool                   $namespace = true,
    bool                   $details = false,
) : array {

    $class ??= \debug_backtrace()[ 1 ] [ 'class' ];
    $class = \is_object( $class ) ? $class::class : $class;

    $classes = $includeSelf ? [ $class => 'self' ] : [];

    $parent  = \class_parents( $class );
    $classes += \array_fill_keys( $parent, 'parent' );

    if ( $includeInterface ) {
        $interfaces = \class_implements( $class );
        $classes    += \array_fill_keys( $interfaces, 'interface' );
    }

    if ( $includeTrait ) {
        $traits  = \class_uses( $class );
        $classes += \array_fill_keys( $traits, 'trait' );
    }

    if ( $details ) {
        return $classes;
    }

    $classes = \array_keys( $classes );

    return $namespace ? $classes : \array_map( 'Northrook\Core\Function\classBasename', $classes );

}

/**
 * Get a boolean option from an array of options.
 *
 * ⚠️ Be careful if passing other nullable values, as they will be converted to booleans.
 *
 * - Pass an array of options, `get_defined_vars()` is recommended.
 * - All 'nullable' values will be converted to booleans.
 * - `true` options set all others to false.
 * - `false` options set all others to true.
 * - Use the `$default` parameter to set value for all if none are set.
 *
 * @param array  $array    Array of options, `get_defined_vars()` is recommended
 * @param bool   $default  Default value for all options
 *
 * @return array<string, bool>
 */
function booleanValues( array $array, bool $default = true ) : array {

    // Isolate the options
    $array = \array_filter( $array, static fn ( $value ) => \is_bool( $value ) || \is_null( $value ) );

    // If any option is true, set all others to false
    if ( \in_array( true, $array, true ) ) {
        return \array_map( static fn ( $option ) => $option === true, $array );
    }

    // If any option is false, set all others to true
    if ( \in_array( false, $array, true ) ) {
        return \array_map( static fn ( $option ) => $option !== false, $array );
    }

    // If none are true or false, set all to the default
    return \array_map( static fn ( $option ) => $default, $array );
}

/**
 * # Generate a deterministic key from a value.
 *
 *  - `$value` will be stringified using `json_encode()`.
 *
 * @param mixed  ...$value
 *
 * @return string
 */
function encodeKey( mixed ...$value ) : string {
    return \json_encode( $value, 64 | 256 | 512 );
}

/**
 * # Generate a deterministic hash key from a value.
 *
 *  - `$value` will be stringified using `json_encode()` by default.
 *  - The value is hashed using `xxh3`.
 *  - The hash is not reversible.
 *
 * The $value can be stringified in one of the following ways:
 *
 * ## `json`
 * Often the fastest option when passing a large object.
 * Will fall back to `serialize` if `json_encode()` fails.
 *
 * ## `serialize`
 * Can sometimes be faster for arrays of strings.
 *
 * ## `implode`
 * Very fast for simple arrays of strings.
 * Requires the `$value` to be an `array` of `string|int|float|bool|Stringable`.
 * Nested arrays are not supported.
 *
 * ```
 * hashKey( [ 'example', new stdClass(), true ] );
 * // => a0a42b9a3a72e14c
 * ```
 *
 * @param mixed                         $value
 * @param 'json'|'serialize'|'implode'  $encoder
 *
 * @return string 16 character hash of the value
 */
function hashKey(
    mixed  $value,
    string $encoder = 'json',
) : string {
    // Use serialize if defined
    if ( $encoder === 'serialize' ) {
        $value = \serialize( $value );
    }
    // Implode if defined and $value is an array
    elseif ( $encoder === 'implode' && \is_array( $value ) ) {
        $value = \implode( ':', $value );
    }
    // JSON as default, or as fallback
    else {
        $value = \json_encode( $value ) ?: \serialize( $value );
    }

    // Hash the $value to a 16 character string
    return \hash( algo : 'xxh3', data : $value );
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
function validateCharacterLimit(
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