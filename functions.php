<?php

/* Support Functions

 License: MIT
 Copyright (c) 2024
 Martin Nielsen <mn@northrook.com>

*/

declare( strict_types = 1 );

namespace {

    if ( !defined( 'ENCODING' ) ) {
        define( 'ENCODING', 'UTF-8' );
    }
}

namespace Support {

    use function Array\filter;
    use function Assert\isIterable;
    use function Assert\isScalar;
    use function String\normalizePath;

    const URL_SAFE_CHARACTERS_UNICODE = "\w.,_~:;@!$&*?#=%()+\-\[\]\'\/";
    const URL_SAFE_CHARACTERS         = "A-Za-z0-9.,_~:;@!$&*?#=%()+\-\[\]\'\/";
    const FILTER_JSON_ENCODE          = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
    const FILTER_STRING_COMMENTS      = [
            '{* '   => '<!-- ', // Latte
            ' *}'   => ' -->',
            '{# '   => '<!-- ', // Twig
            ' #}'   => ' -->',
            '{{-- ' => '<!-- ', // Blade
            ' --}}' => ' -->',
    ];

    // <editor-fold desc="System">

    /**
     * @param string|\DateTimeZone|null  $timezone  [UTC]
     */
    function getTimestamp(
            string | \DateTimeInterface   $when = 'now',
            string | \DateTimeZone | null $timezone = null,
    ) : \DateTimeImmutable
    {
        $fromDateTime = $when instanceof \DateTimeInterface;
        $datetime     = (string) ( $fromDateTime ? $when->getTimestamp() : $when );

        $timezone = match ( true ) {
            \is_null( $timezone )   => $fromDateTime ? $when->getTimezone() : \timezone_open( 'UTC' ),
            \is_string( $timezone ) => \timezone_open( $timezone ),
            default                 => $timezone,
        };

        try {
            return new \DateTimeImmutable( $datetime, $timezone ?: null );
        }
        catch ( \Exception $exception ) {
            throw new \InvalidArgumentException(
                    message : 'Unable to create a new DateTimeImmutable object: ' . $exception->getMessage(),
                    code    : 500, previous : $exception,
            );
        }
    }

    /**
     * Retrieves the project root directory.
     *
     * - This function assumes the Composer directory is present in the project root.
     * - The return is cached for this process.
     *
     * @return non-empty-string
     */
    function getProjectRootDirectory() : string
    {
        static $projectRoot;

        return $projectRoot ??= (
        static function() : string
        {
            // Get an array of each directory leading to this file
            $explodeCurrentDirectory = \explode( \DIRECTORY_SEPARATOR, __DIR__ );
            // Slice off three levels, in this case /core/northrook/composer-dir, commonly /vendor
            $vendorDirectory = \array_slice( $explodeCurrentDirectory, 0, -3 );

            // Implode and return the $projectRoot path
            return \implode( \DIRECTORY_SEPARATOR, $vendorDirectory );
        } )();
    }

    /**
     * Retrieves the system temp directory for this project.
     *
     * - A directory is named using a hash based on the projectRootDirectory.
     * - The return is cached for this process.
     */
    function getSystemCacheDirectory( ?string $append = null ) : string
    {
        static $systemCache;
        $path = $systemCache ??= (
        static function() : string
        {
            $tempDir = \sys_get_temp_dir();
            $dirHash = \hash( 'xxh3', getProjectRootDirectory() );

            return "$tempDir/$dirHash";
        } )();

        return normalizePath( [ $path, $append ] );
    }

    // </editor-fold>

    //<editor-fold desc="Coerce">

    /**
     * This function tries very hard to return a string from any given $value.
     *
     * @param mixed   $value
     * @param string  $separator
     * @param bool    $filter
     *
     * @return string
     */
    function toString( mixed $value, string $separator = '', bool $filter = true ) : string
    {
        if ( isScalar( $value ) ) {
            return (string) $value;
        }

        if ( isIterable( $value ) ) {
            $array = \iterator_to_array( $value );
            return \implode( $separator, $filter ? filter( $array ) : $array );
        }

        if ( \is_object( $value ) ) {
            try {
                return \serialize( $value );
            }
            catch ( \Throwable ) {
                return $value::class;
            }
        }

        /** @var scalar $value */
        return (string) $value;
    }

    //</editor-fold>

    // <editor-fold desc="Class Functions">

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
     * @param string|class-string|object  $class
     *
     * @return string
     * @closure-this
     */
    function classBasename( string | object $class ) : string
    {
        $class     = \is_object( $class ) ? $class::class : $class;
        $namespace = \strrpos( $class, '\\' );

        return $namespace ? \substr( $class, ++$namespace ) : $class;
    }

    /**
     * # Get all the classes, traits, and interfaces used by a class.
     *
     * @param string|object  $class
     * @param bool           $includeSelf
     * @param bool           $includeInterface
     * @param bool           $includeTrait
     * @param bool           $namespace
     * @param bool           $details
     *
     * @return array<array-key, string>
     */
    function extendingClasses(
            string | object $class,
            bool            $includeSelf = true,
            bool            $includeInterface = true,
            bool            $includeTrait = true,
            bool            $namespace = true,
            bool            $details = false,
    ) : array
    {
        $class = \is_object( $class ) ? $class::class : $class;

        $classes = $includeSelf ? [ $class => 'self' ] : [];

        $parent  = \class_parents( $class ) ?: [];
        $classes += \array_fill_keys( $parent, 'parent' );

        if ( $includeInterface ) {
            $interfaces = \class_implements( $class ) ?: [];
            $classes    += \array_fill_keys( $interfaces, 'interface' );
        }

        if ( $includeTrait ) {
            $traits  = \class_uses( $class ) ?: [];
            $classes += \array_fill_keys( $traits, 'trait' );
        }

        if ( $details ) {
            return $classes;
        }

        $classes = \array_keys( $classes );

        if ( $namespace ) {
            foreach ( $classes as $key => $class ) {
                $classes[ $key ] = classBasename( $class );
            }
        }

        return $classes;
    }
    // </editor-fold>
}

namespace Functions {

    use Array\Filter;
    use JetBrains\PhpStorm\Language;
    use function Array\filter;

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
     * @param array<string, ?bool>  $array    Array of options, `get_defined_vars()` is recommended
     * @param bool                  $default  Default value for all options
     *
     * @return array<string, bool>
     */
    function booleanValues( array $array, bool $default = true ) : array
    {
        // Isolate the options
        $array = \array_filter( $array, static fn( $value ) => \is_bool( $value ) || \is_null( $value ) );

        // If any option is true, set all others to false
        if ( \in_array( true, $array, true ) ) {
            return \array_map( static fn( $option ) => $option === true, $array );
        }

        // If any option is false, set all others to true
        if ( \in_array( false, $array, true ) ) {
            return \array_map( static fn( $option ) => $option !== false, $array );
        }

        // If none are true or false, set all to the default
        return \array_map( static fn( $option ) => $default, $array );
    }

    // </editor-fold>

    //<editor-fold desc="Regex Functions">

    function pregExtract(
            #[Language( 'RegExp' )]
            string $pattern,
            string $string,
    ) : null | string | array
    {
        if ( false === \preg_match_all( $pattern, $string, $matches, PREG_SET_ORDER ) ) {
            return null;
        }
        return $matches[ 0 ][ 0 ];
    }

    function regexNamedGroups(
            string $pattern,
            string $subject,
            int    $offset = 0,
            int    &$count = 0,
            int    $flags = PREG_SET_ORDER,
    ) : array
    {
        \preg_match_all( $pattern, $subject, $matches, $flags, $offset );

        foreach ( $matches as $index => $match ) {
            $getNamed = static fn( $value, $key ) => \is_string( $key ) ? $value : false;
            $named    = filter( $match, $getNamed, Filter::USE_BOTH );

            if ( $named ) {
                $matches[ $index ] = [ 'match' => \array_shift( $match ), ... $named, ];
            }
            else {
                unset( $matches[ $index ] );
            }
        }

        $count += \count( $matches );

        return $matches;
    }
}

namespace Assert {

    use const Support\URL_SAFE_CHARACTERS_UNICODE;

    /**
     * Check whether the script is being executed from a command line.
     */
    function isCLI() : bool
    {
        return PHP_SAPI === 'cli' || \defined( 'STDIN' );
    }

    /**
     * Checks whether OPcache is installed and enabled for the given environment.
     */
    function OPcacheEnabled() : bool
    {
        // Ensure OPcache is installed and not disabled
        if (
                !\function_exists( 'opcache_invalidate' )
                || !(bool) \ini_get( 'opcache.enable' ) ) {
            return false;
        }

        // If called from CLI, check accordingly, otherwise true
        return !isCLI() || (bool) \ini_get( 'opcache.enable_cli' );
    }

    /**
     * False if passed value is considered `null` and `empty` type values, retains `0` and `false`.
     *
     * @phpstan-assert-if-true scalar $value
     */
    function isEmpty( mixed $value ) : bool
    {
        return \is_bool( $value ) || \is_numeric( $value ) || (bool) $value;
    }

    /**
     * # Determine if a value is a scalar.
     *
     * @phpstan-assert-if-true scalar|\Stringable|null $value
     */
    function isScalar( mixed $value ) : bool
    {
        return \is_scalar( $value ) || $value instanceof \Stringable || \is_null( $value );
    }

    /**
     * `is_iterable` implementation that also checks for {@see \ArrayAccess}.
     *
     * @phpstan-assert-if-true iterable|\Traversable $value
     */
    function isIterable( mixed $value ) : bool
    {
        return \is_iterable( $value ) || $value instanceof \ArrayAccess;
    }

    /**
     * @param null|string|\Stringable  $value
     * @param string                   ...$enforceDomain
     *
     * @return bool
     */
    function isEmail( null | string | \Stringable $value, string ...$enforceDomain ) : bool
    {
        $string = (string) $value;
        // Can not be null or an empty string
        if ( !$string ) {
            return false;
        }

        // Emails are case-insensitive, lowercase the $value for processing
        $string = \strtolower( $string );

        // Must contain an [at] and at least one period
        if ( !\str_contains( $string, '@' ) || !\str_contains( $string, '.' ) ) {
            return false;
        }

        // Must end with a letter
        if ( !\preg_match( '/[a-z]/', $string[ -1 ] ) ) {
            return false;
        }

        // Must only contain valid characters
        if ( \preg_match( '/[^' . URL_SAFE_CHARACTERS_UNICODE . ']/u', $string ) ) {
            return false;
        }

        // Validate domains, if specified
        foreach ( $enforceDomain as $domain ) {
            if ( \str_ends_with( $string, \strtolower( $domain ) ) ) {
                return true;
            }
        }

        return true;
    }

    /**
     * Checks if a given value has a URL structure.
     *
     * ⚠️ Does **NOT** validate the URL in any capacity!
     */
    function isUrl( mixed $value, ?string $requiredProtocol = null ) : bool
    {
        // Stringify scalars and Stringable objects
        $string = isScalar( $value ) ? \trim( (string) $value ) : false;

        // Can not be an empty string
        if ( !$string ) {
            return false;
        }

        // Must not start with a number
        if ( \is_numeric( $string[ 0 ] ) ) {
            return false;
        }

        /**
         * Does the string resemble a URL-like structure?
         *
         * Ensures the string starts with a schema-like substring, and has a real-ish domain extension.
         *
         * - Will gladly accept bogus strings like `not-a-schema://d0m@!n.tld/`
         *
         */
        if ( !\preg_match( '#^([\w\-+]*?[:/]{2}).+\.[a-z0-9]{2,}#m', $string ) ) {
            return false;
        }

        // Check for required protocol if requested
        if ( $requiredProtocol && !\str_starts_with( $string, \rtrim( $requiredProtocol, ':/' ) . '://' ) ) {
            return false;
        }

        return true;
    }
}

namespace Number {

    /**
     * @param float  $value
     * @param float  $min
     * @param float  $max
     *
     * @return bool
     */
    function within( float $value, float $min, float $max ) : bool
    {
        return $value >= $min && $value <= $max;
    }

    /**
     * @param float      $value
     * @param float      $min
     * @param float      $max
     * @param bool|null  $isWithin
     *
     * @return float
     */
    function clamp( float $value, float $min, float $max, bool &$isWithin = null ) : float
    {
        $isWithin = within( $value, $min, $max );

        return \max( $min, \min( $value, $max ) );
    }

    /**
     *
     * @link https://stackoverflow.com/questions/5464919/find-a-matching-or-closest-value-in-an-array stackoverflow
     *
     * @param int    $match
     * @param int[]  $array
     * @param bool   $returnKey
     *
     * @return null|string|int
     */
    function closest( int $match, array $array, bool $returnKey = false ) : string | int | null
    {
        foreach ( $array as $key => $value ) {
            if ( $match <= $value ) {
                return $returnKey ? $key : $value;
            }
        }

        return null;
    }

    /**
     * @param float  $from
     * @param float  $to
     *
     * @return float
     */
    function percentDifference( float $from, float $to ) : float
    {
        if ( !$from || $from === $to ) {
            return 0;
        }
        return (float) \number_format( ( $from - $to ) / $from * 100, 2 );
    }

    /**
     * Human-readable size notation for a byte value.
     *
     * @param string|int|float  $bytes  Bytes to calculate
     *
     * @return string
     */
    function byteSize( string | int | float $bytes ) : string
    {
        if ( !\is_numeric( $bytes ) ) {
            $type  = \gettype( $bytes );
            $value = \print_r( $bytes, true );
            throw new \InvalidArgumentException(
                    __METHOD__ . " only accepts string, int, or float.\n'$type' of `$value` provided.",
            );
        }

        $bytes = (float) $bytes;

        $unitDecimalsByFactor = [
                [ 'B', 0 ],
                [ 'kB', 0 ],
                [ 'MB', 2 ],
                [ 'GB', 2 ],
                [ 'TB', 3 ],
                [ 'PB', 3 ],
        ];

        $factor = $bytes ? \floor( \log( (int) $bytes, 1024 ) ) : 0;
        $factor = (float) \min( $factor, \count( $unitDecimalsByFactor ) - 1 );

        $value = \round( $bytes / ( 1024 ** $factor ), (int) $unitDecimalsByFactor[ $factor ][ 1 ] );
        $units = (string) $unitDecimalsByFactor[ $factor ][ 0 ];

        return $value . $units;
    }
}

namespace String {

    use function Array\filter;
    use function Support\getProjectRootDirectory;
    use function Support\toString;
    use const Support\FILTER_JSON_ENCODE;
    use const Support\FILTER_STRING_COMMENTS;
    use const Support\URL_SAFE_CHARACTERS_UNICODE;

    const
    TAB          = "\t",
    EMPTY_STRING = '',
    WHITESPACE   = ' ';

    /** Line Feed  */
    const LF = "\n";
    /** Carriage Return */
    const CR = "\r";
    /** Carriage Return and Line Feed */
    const CRLF = "\r\n";

    //<editor-fold desc="Functions">

    /**
     * Ensures appropriate string encoding.
     *
     * Replacement for the deprecated {@see \mb_convert_encoding()}, see [PHP.watch](https://php.watch/versions/8.2/mbstring-qprint-base64-uuencode-html-entities-deprecated) for details.
     *
     * Directly inspired by [aleblanc](https://github.com/aleblanc)'s comment on [this GitHub issue](https://github.com/symfony/symfony/issues/44281#issuecomment-1647665965).
     *
     * @param null|string|\Stringable  $string
     * @param non-empty-string         $encoding
     *
     * @return string
     */
    function encode( null | string | \Stringable $string, string $encoding = \ENCODING ) : string
    {
        if ( !$string = (string) $string ) {
            return EMPTY_STRING;
        }

        $entities = \htmlentities( $string, ENT_NOQUOTES, $encoding, false );
        $decoded  = \htmlspecialchars_decode( $entities, ENT_NOQUOTES );
        $map      = [ 0x80, 0x10FFFF, 0, ~0 ];

        return \mb_encode_numericentity( $decoded, $map, \ENCODING );
    }

    /**
     * Compress a string by replacing consecutive whitespace characters with a single one.
     *
     * @param string  $string
     * @param bool    $whitespaceOnly  If true, only spaces are squished, leaving tabs and new lines intact.
     *
     * @return string The squished string with consecutive whitespace replaced by the defined whitespace character.
     */
    function squish( string $string, bool $whitespaceOnly = false ) : string
    {
        return (string) ( $whitespaceOnly
                ? \preg_replace( "# +#", WHITESPACE, $string )
                : \preg_replace( "#\s+#", WHITESPACE, $string ) );
    }

    /**
     * @param null|string|\Stringable  $string
     * @param non-empty-string         $separator
     * @param int                      $limit
     * @param bool                     $filter
     *
     * @return string[]
     */
    function explode(
            null | string | \Stringable $string,
            string                      $separator = ',',
            int                         $limit = PHP_INT_MAX,
            bool                        $filter = true,
    ) : array
    {
        $exploded = \explode( $separator, toString( $string ), $limit );

        return $filter ? filter( $exploded ) : $exploded;
    }

    /** Replace each key from `$map` with its value, when found in `$content`.
     *
     * @template From of non-empty-string|string
     * @template To of null|string|\Stringable
     *
     * @param array<From,To>  $map  [ From => To ]
     * @param string[]        $content
     *
     * @return array|string|string[] The processed `$content`, or null if `$content` is empty
     */
    function replaceEach(
            array          $map,
            string | array $content,
            bool           $caseSensitive = true,
    ) : string | array
    {
        if ( !$content ) {
            return $content;
        }

        $keys = \array_keys( $map );

        return $caseSensitive
                ? \str_replace( $keys, $map, $content )
                : \str_ireplace( $keys, $map, $content );
    }

    /**
     * @param string  $string
     * @param string  $substring
     * @param bool    $first
     * @param bool    $includeSubstring
     *
     * @return array{string, ?string}
     */
    function split(
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

    //</editor-fold>

    //<editor-fold desc="Substrings">

    /**
     * mix of stringStartsWith, stringEndsWith, stringContains
     *
     * ```
     * function stringContains(
     *     bool caseSensitive = false,
     * ) : bool | int | array | string
     * {
     *   foreach ( (array) $substring as $substring ) {
     *     if ( \%%str_CALLBACK%%( $string, $caseSensitive ? $substring : \strtolower( $substring ) ) ) {
     *       return true;
     *     }
     *   }
     * }
     *```
     *
     */

    function stringContains(
            string         $string,
            string | array $needle,
            bool           $returnNeedles = false,
            bool           $containsOnlyOne = false,
            bool           $containsAll = false,
            bool           $caseSensitive = false,
    ) : bool | int | array | string
    {
        $count    = 0;
        $contains = [];

        $find = static fn( string $string ) => $caseSensitive ? $string : \strtolower( $string );

        $string = $find( $string );

        if ( \is_string( $needle ) ) {
            $count = \substr_count( $string, $find( $needle ) );
        }
        else {
            foreach ( $needle as $index => $value ) {
                $match = \substr_count( $string, $find( $value ) );
                if ( $match ) {
                    $contains[] = $value;
                    $count      += $match;
                    unset( $needle[ $index ] );
                }
            }
        }

        if ( $containsOnlyOne && \count( $contains ) !== 1 ) {
            return false;
        }

        if ( $containsAll && empty( $needle ) ) {
            return true;
        }

        if ( $returnNeedles ) {
            return ( \count( (array) $needle ) === 1 ) ? \reset( $contains ) : $contains;
        }

        return $count;
    }

    function after( string $string, string $substring, bool $first = false ) : string
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

    function before( string $string, string $substring, bool $first = false ) : string
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

    function startsWith( string $string, string | array $substring, bool $caseSensitive = false ) : bool
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

    function endsWith( string $string, string | array $substring, bool $caseSensitive = false ) : bool
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

    function start( string $string, string $with, ?string $separator = null ) : string
    {
        if ( \str_starts_with( $string, $with ) ) {
            return $string;
        }
        return $with . $separator . $string;
    }

    function end( string $string, string $with, ?string $separator = null ) : string
    {
        if ( \str_ends_with( $string, $with ) ) {
            return $string;
        }
        return $string . $separator . $with;
    }

    //</editor-fold>

    // <editor-fold desc="Key Functions">

    /**
     * # Generate a deterministic key from a value.
     *
     *  - `$value` will be stringified using `json_encode()`.
     */
    function encodeKey( mixed ...$value ) : string
    {
        return \json_encode( $value, 64 | 256 | 512 )
                ?: throw new \InvalidArgumentException(
                        'Key cannot be encoded: ' . \json_last_error_msg(),
                );
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
    ) : string
    {
        // Use serialize if defined
        if ( 'serialize' === $encoder ) {
            $value = \serialize( $value );
        }
        // Implode if defined and $value is an array
        elseif ( 'implode' === $encoder && \is_array( $value ) ) {
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
     * # Generate a deterministic key from a system path string.
     *
     * The `$source` will be pass through {@see normalizeKey()}.
     *
     * If the resulting key starts with a normalized {@see getProjectRootDirectory()} string,
     * the returned key will start from the project root.
     *
     *  ```
     *  sourceKey( '/var/www/project/vendor/package/example.file' );
     *  // => 'vendor-package-example-file'
     *  ```
     */
    function sourceKey(
            string | \Stringable $source,
            string               $separator = '-',
            ?string              $fromRoot = null,
    ) : string
    {
        static $rootKey;
        $rootKey[ $separator ] ??= normalizeKey(
                [ getProjectRootDirectory(), $fromRoot ], $separator,
        );

        $source = normalizeKey( (string) $source, $separator );

        if ( \str_starts_with( $source, $rootKey[ $separator ] ) ) {
            return \substr( $source, \strlen( $rootKey[ $separator ] ) + 1 );
        }

        return $source;
    }

    // </editor-fold>

    // <editor-fold desc="Normalizers">

    /**
     * # Normalise a `string`, assuming returning it as a `key`.
     *
     * - Removes non-alphanumeric characters.
     * - Removes leading and trailing separators.
     * - Converts to lowercase.
     *
     * ```
     * normalizeKey( './assets/scripts/example.js' );
     * // => 'assets-scripts-example-js'
     * ```
     *
     * @param ?string[]  $string
     * @param string     $separator  = ['-', '_', ''][$any]
     */
    function normalizeKey(
            string | array | null $string,
            string                $separator = '-',
            int                   $characterLimit = 0,
            bool                  $throwOnIllegalCharacters = false,
    ) : string
    {
        // Convert to lowercase
        $string = \strtolower( \is_string( $string ) ? $string : \implode( $separator, $string ) );

        // Enforce characters
        if ( $throwOnIllegalCharacters && !\preg_match( "/^[a-zA-Z0-9_\-{$separator}]+$/", $string ) ) {
            throw new \InvalidArgumentException(
                    'The provided string contains illegal characters. It must only accept ASCII letters, numbers, hyphens, and underscores.',
            );
        }

        // Replace non-alphanumeric characters with the separator
        $string = (string) \preg_replace( "/[^a-z0-9{$separator}]+/i", $separator, $string );

        if ( $characterLimit && \strlen( $string ) >= $characterLimit ) {
            throw new \InvalidArgumentException(
                    "The normalized key string exceeds the maximum length of '$characterLimit' characters.",
            );
        }

        // Remove leading and trailing separators
        return \trim( $string, $separator );
    }

    /**
     * # Normalise a `string` or `string[]`, assuming it is a `path`.
     *
     * - If an array of strings is passed, they will be joined using the directory separator.
     * - Normalises slashes to system separator.
     * - Removes repeated separators.
     * - Valid paths will be added to the realpath cache.
     * - The resulting string will be cached for this process.
     * - Will throw a {@see \LengthException} if the resulting string exceeds {@see PHP_MAXPATHLEN}.
     *
     * ```
     * normalizePath( './assets\\\/scripts///example.js' );
     * // => '.\assets\scripts\example.js'
     * ```
     *
     * @param string[]  $string         the string to normalize
     * @param bool      $trailingSlash  append a trailing slash
     */
    function normalizePath(
            string | array $string,
            bool           $trailingSlash = false,
    ) : string
    {
        static $cache = [];

        return $cache[ \json_encode( [ $string, $trailingSlash ], 832 ) ] ??= (
        static function() use ( $string, $trailingSlash ) : string
        {
            // Normalize separators
            $normalize = \str_replace( [ '\\', '/' ], DIRECTORY_SEPARATOR, $string );

            // Explode strings for separator deduplication
            $exploded = \is_string( $normalize ) ? \explode( DIRECTORY_SEPARATOR, $normalize ) : $normalize;

            // Ensure each part does not start or end with illegal characters
            $exploded = \array_map(
                    static fn( $item ) => \trim(
                            string     : $item,
                            characters : " \n\r\t\v\0\\/",
                    ),
                    $exploded,
            );

            // Filter the exploded path, and implode using the directory separator
            $path = \implode( DIRECTORY_SEPARATOR, \array_filter( $exploded ) );

            if ( ( $length = \strlen( $path ) ) > ( $limit = \PHP_MAXPATHLEN - 2 ) ) {
                throw new \LengthException (
                        __FUNCTION__ . " resulted in a '$length' character string, exceeding the '$limit' limit.",
                );
            }

            // Add to realpath cache if valid
            $path = \realpath( $path ) ?: $path;

            // Return with or without a $trailingSlash
            return $trailingSlash ? $path . DIRECTORY_SEPARATOR : $path;
        } )();
    }

    /**
     * @param string[]  $string  $string
     */
    function normalizeUrl(
            string | array $string,
            bool           $trailingSlash = false,
    ) : string
    {
        static $cache = [];

        return $cache[ \json_encode( [ $string, $trailingSlash ], 832 ) ] ??= (
        static function() use ( $string, $trailingSlash ) : string
        {
            $string = \is_array( $string ) ? \implode( '/', $string ) : $string;

            $protocol = '/';
            $fragment = '';
            $query    = '';

            // Extract and lowercase the $protocol
            if ( \str_contains( $string, '://' ) ) {
                [ $protocol, $string ] = \explode( '://', $string, 2 );
                $protocol = \strtolower( $protocol ) . '://';
            }

            // Check if the $string contains $query and $fragment
            $matchQuery    = \strpos( $string, '?' );
            $matchFragment = \strpos( $string, '#' );

            // If the $string contains both
            if ( $matchQuery && $matchFragment ) {
                // To parse both regardless of order, we check which one appears first in the $string.
                // Split the $string by the first $match, which will then contain the other.

                // $matchQuery is first
                if ( $matchQuery < $matchFragment ) {
                    [ $string, $query ] = \explode( '?', $string, 2 );
                    [ $query, $fragment ] = \explode( '#', $query, 2 );
                }
                // $matchFragment is first
                else {
                    [ $string, $fragment ] = \explode( '#', $string, 2 );
                    [ $fragment, $query ] = \explode( '?', $fragment, 2 );
                }

                // After splitting, prepend the relevant identifiers.
                $query    = "?$query";
                $fragment = "#$fragment";
            }
            // If the $string only contains $query
            elseif ( $matchQuery ) {
                [ $string, $query ] = \explode( '?', $string, 2 );
                $query = "?$query";
            }
            // If the $string only contains $fragment
            elseif ( $matchFragment ) {
                [ $string, $fragment ] = \explode( '#', $string, 2 );
                $fragment = "#$fragment";
            }

            // Remove duplicate separators, and lowercase the $path
            $path = \strtolower( \implode( '/', \array_filter( \explode( '/', $string ) ) ) );

            // Prepend trailing separator if needed
            if ( $trailingSlash ) {
                $path .= '/';
            }

            // Assemble the URL
            return $protocol . $path . $query . $fragment;
        }
        )();
    }

    // </editor-fold>

    //<editor-fold desc="Escapes and Filters">
    //
    // Filter: safe string, may contain valid HTML
    // Escape: safe string, HTML entities encoded

    /**
     * Performs {@see \htmlspecialchars} on the provided string,
     * and converts template comments to HTML comments.
     *
     * @param null|string|\Stringable  $string
     * @param non-empty-string         $encoding
     *
     * @return string
     */
    function filterHtml( null | string | \Stringable $string, string $encoding = 'UTF-8' ) : string
    {
        if ( !$string = (string) $string ) {
            return EMPTY_STRING;
        }

        $string = \htmlspecialchars( $string, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, $encoding );

        return \strtr( $string, FILTER_STRING_COMMENTS );
    }

    /**
     * Escapes string using {@see \htmlentities}.
     *
     * @param null|string|\Stringable  $string
     * @param non-empty-string         $encoding
     *
     * @return string
     */
    function escapeHtml( null | string | \Stringable $string, string $encoding = 'UTF-8' ) : string
    {
        if ( !$string = (string) $string ) {
            return EMPTY_STRING;
        }

        return \htmlentities( $string, ENT_QUOTES | ENT_HTML5, $encoding );
    }

    /**
     * Escapes string for use inside CSS template.
     *
     * @param null|string|\Stringable  $string
     *
     * @return string
     *
     * @link http://www.w3.org/TR/2006/WD-CSS21-20060411/syndata.html#q6 W3C CSS Characters and case reference
     */
    function escapeCSS( null | string | \Stringable $string ) : string
    {
        trigger_deprecation( 'Northrook\\Functions', 'probe', __METHOD__ );
        // http://www.w3.org/TR/2006/WD-CSS21-20060411/syndata.html#q6

        if ( !$string = (string) $string ) {
            return EMPTY_STRING;
        }

        return \addcslashes( $string, "\x00..\x1F!\"#$%&'()*+,./:;<=>?@[\\]^`{|}~" );
    }

    /**
     * Escapes variables for use inside <script>.
     */
    function escapeJS( mixed $value ) : string
    {
        trigger_deprecation( 'Northrook\\Functions', 'probe', __METHOD__ );

        $json = \json_encode( $value, FILTER_JSON_ENCODE );
        if ( \json_last_error() ) {
            throw new \RuntimeException( \json_last_error_msg() );
        }

        if ( !$json ) {
            return EMPTY_STRING;
        }

        return \str_replace( [ ']]>', '<!', '</' ], [ ']]\u003E', '\u003C!', '<\/' ], $json );
    }

    /**
     * Escapes string for use inside HTML attribute value.
     */
    function escapeHtmlAttr( null | string | \Stringable $string, bool $double = true, string $encoding = "UTF-8",
    ) : string
    {
        trigger_deprecation( 'Northrook\\Functions', 'dev', __METHOD__ );
        $string = (string) $string;

        if ( \str_contains( $string, '`' ) && \strpbrk( $string, ' <>"\'' ) === false ) {
            $string .= ' '; // protection against innerHTML mXSS vulnerability nette/nette#1496
        }

        $string = \htmlspecialchars( $string, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, $encoding, $double );

        return \str_replace( '{', '&#123;', $string );
    }

    //</editor-fold>

    //<editor-fold desc="Filters and Escapes">

    //<editor-fold desc="URL">

    /**
     * Filter a string assuming it a URL.
     *
     * - Preserves Unicode characters.
     * - Removes tags by default.
     *
     * @param string  $string
     * @param bool    $preserveTags
     *
     * @return string
     */
    function filterUrl( string $string, bool $preserveTags = false ) : string
    {
        trigger_deprecation( 'Northrook\\Functions', 'dev', __METHOD__ );
        static $cache = [];
        return $cache[ \json_encode( [ $string, $preserveTags ], 832 ) ] ??= (
        static function() use ( $string, $preserveTags ) : string
        {
            $safeCharacters = URL_SAFE_CHARACTERS_UNICODE;

            if ( $preserveTags ) {
                $safeCharacters .= '{}|^`"><@';
            }

            return \preg_replace(
                           pattern     : "/[^$safeCharacters]/u",
                           replacement : EMPTY_STRING,
                           subject     : $string,
                   ) ?? EMPTY_STRING;
        } )();
    }

    /**
     * Sanitizes string for use inside href attribute.
     */
    function escapeUrl( null | string | \Stringable $string ) : string
    {
        trigger_deprecation( 'Northrook\\Functions', 'probing', __METHOD__ );
        // Sanitize the URL, preserving tags for escaping
        $string = filterUrl( (string) $string, true );

        // Escape special characters including tags
        return \htmlspecialchars( $string, ENT_QUOTES, 'UTF-8' );
    }

    //</editor-fold>

    /**
     * Escape each and every character in the provided string.
     *
     * ```
     *  escapeCharacters('Hello!');
     *  // => '\H\e\l\l\o\!'
     * ```
     *
     * @param string  $string
     *
     * @return string
     */
    function escapeCharacters( string $string ) : string
    {
        return \implode( '', \array_map( static fn( $char ) => '\\' . $char, \str_split( $string ) ) );
    }

    function stringStripTags( string $string, string $replacement = ' ', ?string ...$allowed_tags ) : string
    {
        return \str_replace(
                '  ', ' ',
                \strip_tags(
                        \str_replace( "<", "$replacement<", $string ),
                ),
        );
    }

    /**
     * Escapes string for use inside iCal template.
     *
     * @param string  $string
     *
     * @return string
     */
    function escapeICal( string $string ) : string
    {
        trigger_deprecation( 'Northrook\\Functions', 'probing', __METHOD__ );
        // https://www.ietf.org/rfc/rfc5545.txt
        $string = \str_replace( "\r", '', (string) $string );
        $string = \preg_replace( '#[\x00-\x08\x0B-\x1F]#', "\u{FFFD}", (string) $string );
        return \addcslashes( (string) $string, "\";\\,:\n" );
    }

    //</editor-fold>

    /**
     * Throws a {@see \LengthException} when the length of `$string` exceeds the provided `$limit`.
     *
     * @param string       $string
     * @param int          $limit
     * @param null|string  $caller  Class, method, or function name
     *
     * @return void
     */
    function characterLimit(
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
}

namespace Array {

    enum Filter : int
    {
        case USE_VALUE = 0;
        case USE_BOTH = 1;
        case USE_KEY = 2;
    }

    use function Assert\isIterable;

    /**
     * @template TKey of array-key
     * @template TValue of mixed
     * Default:
     * - Removes `null` and `empty` type values, retains `0` and `false`.
     *
     * @param array<TKey, TValue>  $array
     * @param ?callable            $callback
     * @param Filter               $mode
     *
     * @return array<TKey, TValue>
     */
    function filter( array $array, ?callable $callback = null, Filter $mode = Filter::USE_VALUE ) : array
    {
        return \array_filter( $array, $callback ?? '\Assert\isEmpty', $mode->value );
    }

    /**
     * Default:
     * - Removes `null` and `empty` type values, retains `0` and `false`.
     *
     * @param array<array-key, mixed>  $array
     * @param ?callable                $callback
     * @param Filter                   $mode
     *
     * @return array<array-key, mixed>
     */
    function filterRecursive(
            array     $array,
            ?callable $callback = null,
            Filter    $mode = Filter::USE_VALUE,
    ) : array
    {
        foreach ( $array as $key => $value ) {
            if ( \is_array( $value ) ) {
                $array[ $key ] = !$value
                        ? filterRecursive( $value, $callback, $mode )
                        : filter( $value, $callback, $mode );
            }
            else {
                $array[ $key ] = $value;
            }
        }
        return filter( $array );
    }

    /**
     * @param array<array-key, mixed>  $array
     * @param bool                     $filter
     *
     * @return array<array-key, mixed>
     */
    function flatten( array $array, bool $filter = false ) : array
    {
        $result = [];

        foreach ( $array as $key => $value ) {
            if ( isIterable( $value ) ) {
                $value  = \iterator_to_array( $value );
                $result = \array_merge( $result, flatten( $filter ? filter( $array ) : $value ) );
            }
            else {
                // Add the value while preserving the key
                $result[ $key ] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, scalar>  $array
     * @param bool                      $caseSensitive
     *
     * @return array<array-key, scalar>
     */
    function uniqueScalar( array $array, bool $caseSensitive = false ) : array
    {
        if ( !$caseSensitive ) {
            $array = \array_map( static fn( $value ) => \is_string( $value ) ? \strtolower( $value ) : $value, $array );
        }
        return \array_unique( $array, SORT_REGULAR );
    }

    /**
     *
     * @param array<array-key, mixed>  $array
     *
     * @return array<array-key, mixed>
     */
    function uniqueValues( array $array ) : array
    {
        $unique = [];
        foreach ( $array as $key => $value ) {
            // Check if value is already present
            $isDuplicate = false;
            foreach ( $unique as $existing ) {
                // If it's an object or array, use a deeper comparison
                if ( \is_object( $value ) || \is_array( $value ) ) {
                    if ( $value == $existing ) {
                        $isDuplicate = true;
                        break;
                    }
                }
                elseif ( $value === $existing ) { // For scalar values, use strict comparison
                    $isDuplicate = true;
                    break;
                }
            }

            // Add the value to the result array if it's unique
            if ( !$isDuplicate ) {
                $unique[ $key ] = $value;
            }
        }
        return $unique;
    }

    /**
     * Ensures the provided array contains all keys.
     *
     * @param array<array-key, mixed>  $array
     * @param array-key                ...$keys
     *
     * @return bool
     */
    function hasKeys( array $array, int | string ...$keys ) : bool
    {
        foreach ( $keys as $key ) {
            if ( !\array_key_exists( $key, $array ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<array-key, mixed>  $array
     * @param array-key                $key
     * @param array-key                $replacement
     *
     * @return array<array-key, mixed>
     */
    function replaceKey( array $array, int | string $key, int | string $replacement ) : array
    {
        $keys  = \array_keys( $array );
        $index = \array_search( $key, $keys, true );

        if ( $index !== false ) {
            $keys[ $index ] = $replacement;
            $array          = \array_combine( $keys, $array );
        }

        return $array;
    }

    /**
     * @param array<array-key,mixed>|object  $array
     * @param bool                           $filter
     *
     * @return object
     */
    function asObject( array | object $array, bool $filter = false ) : object
    {
        if ( $filter && \is_array( $array ) ) {
            $array = \array_filter( $array );
        }

        try {
            return (object) \json_decode(
                    \json_encode( $array, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT ), false, 512, JSON_THROW_ON_ERROR,
            );
        }
        catch ( \JsonException ) {
            return (object) $array;
        }
    }
}