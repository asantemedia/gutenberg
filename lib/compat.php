<?php
/**
 * Temporary compatibility shims for features present in Gutenberg, pending
 * upstream commit to the WordPress core source repository. Functions here
 * exist only as long as necessary for corresponding WordPress support, and
 * each should be associated with a Trac ticket.
 *
 * @package gutenberg
 */

/**
 * Filters allowed CSS attributes to include `flex-basis`, included in saved
 * markup of the Column block.
 *
 * This can be removed when plugin support requires WordPress 5.3.0+.
 *
 * @see https://core.trac.wordpress.org/ticket/47281
 * @see https://core.trac.wordpress.org/changeset/45363
 *
 * @since 5.7.0
 *
 * @param string[] $attr Array of allowed CSS attributes.
 *
 * @return string[] Filtered array of allowed CSS attributes.
 */
function gutenberg_safe_style_css_column_flex_basis( $attr ) {
	$attr[] = 'flex-basis';

	return $attr;
}
add_filter( 'safe_style_css', 'gutenberg_safe_style_css_column_flex_basis' );

/**
 * Filters inserted post data to unset any auto-draft assigned post title. The status
 * of an auto-draft should be read from its `post_status` and not inferred via its
 * title. A post with an explicit title should be created with draft status, not
 * with auto-draft status. It will also update an existing post's status to draft if
 * currently an auto-draft. This is intended to ensure that a post which is
 * explicitly updated should no longer be subject to auto-draft purge.
 *
 * @see https://core.trac.wordpress.org/ticket/43316#comment:88
 * @see https://core.trac.wordpress.org/ticket/43316#comment:89
 *
 * @param array $data    An array of slashed post data.
 * @param array $postarr An array of sanitized, but otherwise unmodified post
 *                        data.
 *
 * @return array Filtered post data.
 */
function gutenberg_filter_wp_insert_post_data( $data, $postarr ) {
	if ( 'auto-draft' === $postarr['post_status'] ) {
		if ( ! empty( $postarr['ID'] ) ) {
			$data['post_status'] = 'draft';
		} else {
			$data['post_title'] = '';
		}
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'gutenberg_filter_wp_insert_post_data', 10, 2 );


/**
 * Shim that hooks into `pre_render_block` so as to override `render_block`
 * with a function that passes `render_callback` the block object as the
 * argument.
 *
 * @param string $pre_render The pre-rendered content. Default null.
 * @param array  $block The block being rendered.
 *
 * @return string String of rendered HTML.
 */
function gutenberg_provide_render_callback_with_block_object( $pre_render, $block ) {
	global $post;

	$source_block = $block;

	/** This filter is documented in src/wp-includes/blocks.php */
	$block = apply_filters( 'render_block_data', $block, $source_block );

	$block_type    = WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] );
	$is_dynamic    = $block['blockName'] && null !== $block_type && $block_type->is_dynamic();
	$block_content = '';
	$index         = 0;

	foreach ( $block['innerContent'] as $chunk ) {
		$block_content .= is_string( $chunk ) ? $chunk : render_block( $block['innerBlocks'][ $index++ ] );
	}

	if ( ! is_array( $block['attrs'] ) ) {
		$block['attrs'] = array();
	}

	if ( $is_dynamic ) {
		$global_post = $post;

		$block_type->prepare_attributes_for_render( $block['attrs'] );
		$block_content = (string) call_user_func( $block_type->render_callback, $block['attrs'], $block_content, $block );

		$post = $global_post;
	}

	/** This filter is documented in src/wp-includes/blocks.php */
	return apply_filters( 'render_block', $block_content, $block );
}
add_filter( 'pre_render_block', 'gutenberg_provide_render_callback_with_block_object', 10, 2 );
