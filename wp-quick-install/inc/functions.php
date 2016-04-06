<?php

if ( ! function_exists( '_' ) ) {
	function _( $str ) {
		echo $str;
	}
}

function sanit( $str ) {
	return addcslashes( str_replace( array( ';', "\n" ), '', $str ), '\\' );
}

function random_capletters( $number = 0, &$excludes ) {
   // Capital letters except I, L, O, Q
   $letters =  array_merge( range('A', 'H'), range('J', 'N'), array('P'), range('R', 'Z') );
   if ( is_array( $excludes ) ) {
      $letters = array_diff( $letters, $excludes );
   }
   shuffle($letters);
   if ( $number == 0 || $number > sizeof( $letters ) ) {
      return $letters;
   } else {
      return array_slice( $letters, 0, $number );
   }

}
function random_lcaseletters( $number = 0, &$excludes ) {
   // Lowercase letters except I, L, O, Q
   $letters = array_merge( range('a', 'h'), range('j', 'n'), array('p'), range('r', 'z') );
   if ( is_array( $excludes ) ) {
      $letters = array_diff( $letters, $excludes );
   }
   shuffle( $letters );
   if ( $number == 0 || $number > sizeof( $letters ) ) {
      return $letters;
   } else {
      return array_slice( $letters, 0, $number );
   }
}
function random_digits( $number = 0, &$excludes ) {
   // Omit 0 and 1 as too similar to O and L
   $numbers =  range( '2','9');
   if ( is_array( $excludes ) ) {
      $numbers = array_diff( $numbers, $excludes );
   }
   $numbers = array_diff( $numbers, $excludes );
   shuffle($numbers);
   if ( $number == 0 || $number > sizeof( $numbers ) ) {
      return $numbers;
   } else {
      return array_slice( $numbers, 0, $number );
   }
}
function random_specialchars( $number = 0, &$excludes ) {
   $chars =   array( '!', '@', '#', '%', '=', '-', '_', '?', '<', '>' ) ;
   if ( is_array( $excludes ) ) {
      $chars = array_diff( $chars, $excludes );
   }
   shuffle( $chars );
   if ( $number == 0 || $number > sizeof( $chars ) ) {
      return $chars;
   } else {
      return array_slice( $chars, 0, $number );
   }
}
function random_pw( $length = 8, array $excludes = array() ) {
   // Min length is 8
   $length = $length < 8 ? 8 : $length;

   $getlength = rand( 2, intval( $length / 4 ) );  // Allow at least two of each type
   $remainder = $length - $getlength;
   $special = random_specialchars( $getlength, $excludes );

   $getlength = rand( 2, intval( $remainder  / 3 ) );  // Allow at least two of each type
   $remainder = $remainder - $getlength;
   $digits = random_digits( $getlength, $excludes );

   $getlength = rand( 2, intval( $remainder / 2 ) );  // Allow at least two of each type
   $remainder = $remainder - $getlength;
   $caps = random_capletters( $getlength, $excludes );

   $lower = random_lcaseletters( $remainder, $excludes );

   $pw = array_merge( $caps, $lower, $digits, $special );
   shuffle( $pw );
   return implode( '', $pw );
}
function random_table_prefix() {
   $first2 = random_lcaseletters(2);
   $last2 = random_digits(2);
   $prefix = implode( '', $first2) . implode( '', $last2) . '_';
   return $prefix;
}
