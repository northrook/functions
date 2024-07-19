<?php

/* Core Number Functions

 License: MIT
 Copyright (c) 2024
 Martin Nielsen <mn@northrook.com>

*/

declare( strict_types = 1 );

namespace Northrook;

/**
 *
 * @link https://stackoverflow.com/questions/5464919/find-a-matching-or-closest-value-in-an-array stackoverflow
 *
 * @param int    $match
 * @param array  $array
 * @param bool   $returnKey
 *
 * @return mixed
 */
function numberClosest( int $match, array $array, bool $returnKey = false ) : mixed {

    foreach ( $array as $key => $value ) {
        if ( $match <= $value ) {
            return $returnKey ? $key : $value;
        }

    }

    return null;
}

function numberPercentDifference(
    float $from,
    float $to,
) : float {
    return (float) number_format( ( $from - $to ) / $from * 100, 2 );
}

function numberDecimals( float $number, int $decimals = 2, string $separator = '.', string $thousands = '' ) : float {
    return (float) \number_format( $number, $decimals, $separator, $thousands );
}

function isNumberBetween( float $value, float $min, float $max ) : bool {
    return $value >= $min && $value <= $max;
}

function numberBetween( float $value, float $min, float $max ) : float {
    return \max( \min( $max, $value ), $min );
}

function intWithin( float $value, float $min, float $max, ) : float {
    return match ( true ) {
        $value >= $max => $max,
        $value < $min  => $min,
        default        => $value
    };

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