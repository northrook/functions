<?php

/* Core Array Functions

 License: MIT
 Copyright (c) 2024
 Martin Nielsen <mn@northrook.com>

*/

declare( strict_types = 1 );

namespace Northrook {

    use JetBrains\PhpStorm\ExpectedValues;


    /**
     * TODO : [med] Only considers the first level of the array
     * TODO : [high] $caseSensitive assumes all keys are strings
     *
     * @param array  $array
     * @param bool   $caseSensitive
     *
     * @return array
     */
    function arrayUnique( array $array, bool $caseSensitive = false ) : array
    {
        if ( !$caseSensitive ) {
            $array = array_map( "strtolower", $array );
        }

        return array_flip( array_flip( $array ) );
    }

    function arrayFlatten( array $array, bool $filter = false, bool $unique = false ) : array
    {
        $result = [];

        // if ( $filter ) {
        //     \array_walk_recursive(
        //         $array,
        //         static function ( $item ) use ( &$result ) {
        //             if ( !empty( $item ) ) {
        //                 $result[] = $item;
        //             }
        //
        //         },
        //     );
        // }
        // else {
        //     \array_walk_recursive(
        //         $array,
        //         static function ( $item ) use ( &$result ) {
        //             $result[] = $item;
        //         },
        //     );
        // }

        if ( $filter ) {
            $array = arrayFilterRecursive( $array );
        }

        \array_walk_recursive(
            $array,
            static function( $item ) use ( &$result )
            {
                $result[] = $item;
            },
        );

        return $unique ? arrayUnique( $result ) : $result;
    }

    // TODO [low] Add option for match any, match all, and match none.
    /**
     * @param array           $array
     * @param int[]|string[]  $keys
     *
     * @return bool
     */
    function arrayKeyExists( array $array, array $keys ) : bool
    {
        foreach ( $keys as $key ) {
            if ( !\array_key_exists( $key, $array ) ) {
                return false;
            }
        }

        return true;
    }

    function arrayAsObject( array | object $array, bool $filter = false ) : object
    {
        if ( $filter && \is_array( $array ) ) {
            $array = \array_filter( $array );
        }

        try {
            return \json_decode(
                \json_encode( $array, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT ), false, 512, JSON_THROW_ON_ERROR,
            );
        }
        catch ( \JsonException ) {
            return (object) $array;
        }
    }

    /**
     * Default:
     * - Removes `null` and `empty` type values, retains `0` and `false`.
     *
     * @param array      $array
     * @param ?callable  $callback
     * @param int        $mode
     *
     * @return array
     */
    function arrayFilter(
        array     $array,
        ?callable $callback = null,
        #[ExpectedValues( [ ARRAY_FILTER_USE_VALUE, ARRAY_FILTER_USE_KEY, ARRAY_FILTER_USE_BOTH ] )]
        int       $mode = ARRAY_FILTER_USE_VALUE,
    ) : array
    {
        return \array_filter( $array, $callback ?? 'Northrook\isEmpty', $mode );
    }

    function arrayFilterRecursive( array $array ) : array
    {
        foreach ( $array as $key => $value ) {
            if ( \is_array( $value ) ) {
                $array[ $key ] = !$value ? arrayFilterRecursive( $value ) : arrayFilter( $value );
            }
            else {
                $array[ $key ] = $value;
            }
        }
        return arrayFilter( $array );
    }

    function arrayReplaceKey( array $array, string $target, string $replacement ) : array
    {
        $keys  = \array_keys( $array );
        $index = \array_search( $target, $keys, true );

        if ( $index !== false ) {
            $keys[ $index ] = $replacement;
            $array          = \array_combine( $keys, $array );
        }

        return $array;
    }

    function arraySearchKeys( array $array, string ...$key ) : array
    {
        $get = [];
        foreach ( $key as $match ) {
            if ( isset( $array[ $match ] ) ) {
                $get[ $match ] = $array[ $match ];
            }
        }

        return $get;
    }
}