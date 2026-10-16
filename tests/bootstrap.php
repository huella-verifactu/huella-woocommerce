<?php
/**
 * Unit tests run without WordPress: the classes under test are pure PHP.
 *
 * @package Huella\Verifactu
 */

declare(strict_types=1);

// The pure classes translate their messages; without WordPress, text passes through unchanged.
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string { // phpcs:ignore
		return $text;
	}
}

require dirname( __DIR__ ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/autoload.php';
