<?php

/**
 * @wordpress-plugin
 * Plugin Name:     Kntnt Block Editor Defaults
 * Plugin URI:      https://github.com/Kntnt/kntnt-block-editor-defaults
 * Description:     Provides defaults for featured image, title and excerpt in the block editor.
 * Version:         1.0.0
 * Author:          Thomas Barregren
 * Author URI:      https://www.kntnt.com/
 * License:         GPL-3.0+
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.txt
 * Requires PHP:    8.3+
 */

declare( strict_types=1 );

namespace Kntnt\Block_Editor_Defaults;

defined( 'ABSPATH' ) && new Plugin;

class Plugin {

	private array $text_blocks = [ 'core/paragraph' ];

	private array $heading_blocks = [ 'core/heading' ];

	private array $image_blocks = [ 'core/image', 'core/media-text', 'core/cover' ];

	private int $title_max_len = 70;

	private bool $trim_excerpt = true;

	public function __construct() {
		add_filter( 'wp_insert_post_data', $this->apply_filters( ... ), 9 );
		add_filter( 'wp_insert_post_data', $this->update_title_on_save( ... ), 10, 2 );
		add_filter( 'wp_insert_post_data', $this->update_excerpt_on_save( ... ), 10, 2 );
		add_filter( 'wp_insert_post_data', $this->update_featured_image_on_save( ... ), 10, 2 );
	}

	public function apply_filters( array $data ): array {
		$this->text_blocks    = apply_filters( 'kntnt-block-editor-defaults-text-blocks', $this->text_blocks );
		$this->heading_blocks = apply_filters( 'kntnt-block-editor-defaults-heading-blocks', $this->heading_blocks );
		$this->image_blocks   = apply_filters( 'kntnt-block-editor-defaults-image-blocks', $this->image_blocks );
		$this->title_max_len  = apply_filters( 'kntnt-block-editor-defaults-trim-excerpts', $this->title_max_len );
		$this->trim_excerpt   = apply_filters( 'kntnt-block-editor-defaults-trim-excerpts', $this->trim_excerpt );
		return $data;
	}

	public function update_title_on_save( array $data, array $postarr ): array {
		if ( empty( $data['post_title'] ) && isset( $data['post_content'] ) ) {
			$blocks = parse_blocks( $data['post_content'] );
			[ 'title' => $title ] = $this->find_title_in_blocks( $blocks, [ 'title' => '', 'level' => 8 ] );
			if ( $title ) {
				$data['post_title'] = $title;
			}
		}
		return $data;
	}

	public function update_excerpt_on_save( array $data, array $postarr ): array {
		if ( empty( $data['post_excerpt'] ) && isset( $data['post_content'] ) ) {
			$text = $this->find_excerpt_in_blocks( parse_blocks( $data['post_content'] ) );
			if ( $text ) {
				if ( $this->trim_excerpt ) {
					wp_trim_excerpt( $text );
				}
				$data['post_excerpt'] = $text;
			}
		}
		return $data;
	}

	public function update_featured_image_on_save( array $data, array $postarr ): array {
		if ( ! has_post_thumbnail( $postarr['ID'] ) && isset( $data['post_content'] ) ) {
			$image_id = $this->find_featured_image_in_blocks( parse_blocks( $data['post_content'] ) );
			if ( $image_id ) {
				set_post_thumbnail( $postarr['ID'], $image_id );
			}
		}
		return $data;
	}

	private function find_title_in_blocks( array $blocks, array $result ): array {

		foreach ( $blocks as $block ) {

			if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) ) {
				$result = $this->find_title_in_blocks( $block['innerBlocks'], $result );
			}

			$innerHTML = $block['innerHTML'] ?? '';
			$text      = trim( wp_strip_all_tags( $innerHTML ) );

			if ( empty( $text ) ) {
				continue;
			}

			if ( $result['level'] === 8 && in_array( $block['blockName'], $this->text_blocks, true ) ) {
				$result = [
					'title' => strlen( $text ) > $this->title_max_len ? substr( $text, 0, $this->title_max_len ) . '…' : $text,
					'level' => 7,
				];
			}
			elseif ( in_array( $block['blockName'], $this->heading_blocks, true ) ) {
				if ( preg_match( '/<h(?<level>[1-6])/', $innerHTML, $matches ) ) {
					$level = (int) $matches['level'];
					if ( $level < $result['level'] ) {
						$result = [
							'title' => $text,
							'level' => $level,
						];
					}
				}
			}

		}

		return $result;

	}

	private function find_excerpt_in_blocks( array $blocks, string $text = '' ): string {

		foreach ( $blocks as $block ) {

			if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) ) {
				$text = $this->find_excerpt_in_blocks( $block['innerBlocks'], $text );
			}

			if ( in_array( $block['blockName'], $this->text_blocks, true ) ) {
				$newText = trim( wp_strip_all_tags( $block['innerHTML'] ?? '' ) );
				if ( ! empty( $newText ) ) {
					return $newText;
				}
			}

		}

		return $text;

	}

	private function find_featured_image_in_blocks( array $blocks, int $image_id = 0 ): int {

		foreach ( $blocks as $block ) {

			if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) ) {
				$image_id = $this->find_featured_image_in_blocks( $block['innerBlocks'], $image_id );
			}

			if ( in_array( $block['blockName'], $this->image_blocks, true ) ) {
				preg_match( '/wp-image-(?<image_id>\d+)/', $block['innerHTML'] ?? '', $matches );
				if ( isset( $matches['image_id'] ) ) {
					return (int) $matches['image_id'];
				}
			}

			if ( $image_id !== 0 ) {
				break;
			}

		}

		return $image_id;

	}
}
