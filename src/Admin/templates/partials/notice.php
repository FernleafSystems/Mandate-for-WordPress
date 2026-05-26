<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

if ( !$notice[ 'is_visible' ] ) {
	return;
}
?>
<div class="<?php echo esc_attr( $notice[ 'classes' ] ); ?>"><p><?php echo esc_html( $notice[ 'text' ] ); ?></p></div>
