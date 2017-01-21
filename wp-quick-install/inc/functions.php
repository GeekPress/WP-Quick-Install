<?php

if ( ! function_exists( '_' ) ) {
	function _( $str ) {
		echo $str;
	}
}

function sanit( $str ) {
	$str = basename( $str );
	return addcslashes( str_replace( array( ';', "\n" ), '', $str ), '\\' );
}
