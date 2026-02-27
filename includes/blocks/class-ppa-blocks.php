<?php
/**
 * Blocks Registration
 *
 * Registers all Gutenberg blocks for the plugin.
 *
 * @package PressPrimer_Assignment
 * @subpackage Blocks
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocks class
 *
 * Handles registration and rendering of Gutenberg blocks.
 *
 * @since 1.0.0
 */
class PressPrimer_Assignment_Blocks {

	/**
	 * Initialize blocks
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Register block category filter.
		add_filter( 'block_categories_all', [ $this, 'register_block_category' ], 10, 2 );

		// Register blocks on init.
		add_action( 'init', [ $this, 'register_blocks' ] );
	}

	/**
	 * Register block category
	 *
	 * Creates a custom category for PressPrimer Assignment blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param array                   $categories Array of block categories.
	 * @param WP_Block_Editor_Context $context Block editor context.
	 * @return array Modified categories array.
	 */
	public function register_block_category( $categories, $context ) {
		// Check if category already exists.
		foreach ( $categories as $category ) {
			if ( 'pressprimer-assignment' === $category['slug'] ) {
				return $categories;
			}
		}

		// Add our category at the beginning.
		return array_merge(
			[
				[
					'slug'  => 'pressprimer-assignment',
					'title' => __( 'PressPrimer Assignment', 'pressprimer-assignment' ),
					'icon'  => 'media-document',
				],
			],
			$categories
		);
	}

	/**
	 * Register all blocks
	 *
	 * @since 1.0.0
	 */
	public function register_blocks() {
		// Check if block registration is available.
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Register Assignment block.
		$this->register_assignment_block();

		// Register My Submissions block.
		$this->register_my_submissions_block();
	}

	/**
	 * Register Assignment block
	 *
	 * @since 1.0.0
	 */
	private function register_assignment_block() {
		// Get asset file for dependencies and version.
		$asset_file = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/blocks/assignment/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Register block script.
		wp_register_script(
			'pressprimer-assignment-assignment-block-editor',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/blocks/assignment/index.js',
			$asset['dependencies'],
			$asset['version']
		);

		// Register editor style.
		wp_register_style(
			'pressprimer-assignment-assignment-block-editor-style',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'blocks/assignment/editor.css',
			[],
			PRESSPRIMER_ASSIGNMENT_VERSION
		);

		// Register frontend style.
		wp_register_style(
			'pressprimer-assignment-assignment-block-style',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'blocks/assignment/style.css',
			[],
			PRESSPRIMER_ASSIGNMENT_VERSION
		);

		// Register block type.
		register_block_type(
			'pressprimer-assignment/assignment',
			[
				'api_version'     => 3,
				'title'           => __( 'PPA Assignment', 'pressprimer-assignment' ),
				'description'     => __( 'Display an assignment for users to submit.', 'pressprimer-assignment' ),
				'category'        => 'pressprimer-assignment',
				'icon'            => 'media-document',
				'supports'        => [
					'html'  => false,
					'align' => [ 'wide', 'full' ],
				],
				'editor_script'   => 'pressprimer-assignment-assignment-block-editor',
				'editor_style'    => 'pressprimer-assignment-assignment-block-editor-style',
				'style'           => 'pressprimer-assignment-assignment-block-style',
				'render_callback' => [ $this, 'render_assignment_block' ],
				'attributes'      => [
					'assignmentId'     => [
						'type'    => 'number',
						'default' => 0,
					],
					'showDescription'  => [
						'type'    => 'boolean',
						'default' => true,
					],
					'showInstructions' => [
						'type'    => 'boolean',
						'default' => true,
					],
					'showMaxPoints'    => [
						'type'    => 'boolean',
						'default' => true,
					],
					'showFileInfo'     => [
						'type'    => 'boolean',
						'default' => true,
					],
				],
			]
		);
	}

	/**
	 * Render Assignment block
	 *
	 * @since 1.0.0
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered block HTML.
	 */
	public function render_assignment_block( $attributes ) {
		// Get assignment ID from attributes.
		$assignment_id = isset( $attributes['assignmentId'] ) ? absint( $attributes['assignmentId'] ) : 0;

		// If no assignment selected, show placeholder.
		if ( ! $assignment_id ) {
			return '<div class="wp-block-pressprimer-assignment-assignment ppa-block-placeholder">' .
				'<p>' . esc_html__( 'Please select an assignment to display.', 'pressprimer-assignment' ) . '</p>' .
				'</div>';
		}

		// Use the shortcode handler to render.
		if ( ! class_exists( 'PressPrimer_Assignment_Shortcodes' ) ) {
			return '<div class="ppa-error">' . esc_html__( 'Assignment renderer not available.', 'pressprimer-assignment' ) . '</div>';
		}

		// Build shortcode attributes (convert camelCase to snake_case).
		$shortcode_atts = [
			'id'                => $assignment_id,
			'show_description'  => isset( $attributes['showDescription'] ) ? ( $attributes['showDescription'] ? 'true' : 'false' ) : 'true',
			'show_instructions' => isset( $attributes['showInstructions'] ) ? ( $attributes['showInstructions'] ? 'true' : 'false' ) : 'true',
			'show_max_points'   => isset( $attributes['showMaxPoints'] ) ? ( $attributes['showMaxPoints'] ? 'true' : 'false' ) : 'true',
			'show_file_info'    => isset( $attributes['showFileInfo'] ) ? ( $attributes['showFileInfo'] ? 'true' : 'false' ) : 'true',
		];

		// Call the shortcode handler.
		$shortcodes = new PressPrimer_Assignment_Shortcodes();
		$output     = $shortcodes->render_assignment( $shortcode_atts );

		// Wrap in block div.
		return '<div class="wp-block-pressprimer-assignment-assignment">' . $output . '</div>';
	}

	/**
	 * Register My Submissions block
	 *
	 * @since 1.0.0
	 */
	private function register_my_submissions_block() {
		// Get asset file for dependencies and version.
		$asset_file = PRESSPRIMER_ASSIGNMENT_PLUGIN_PATH . 'build/blocks/my-submissions/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Register block script.
		wp_register_script(
			'pressprimer-assignment-my-submissions-block-editor',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'build/blocks/my-submissions/index.js',
			$asset['dependencies'],
			$asset['version']
		);

		// Register editor style.
		wp_register_style(
			'pressprimer-assignment-my-submissions-block-editor-style',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'blocks/my-submissions/editor.css',
			[],
			PRESSPRIMER_ASSIGNMENT_VERSION
		);

		// Register frontend style.
		wp_register_style(
			'pressprimer-assignment-my-submissions-block-style',
			PRESSPRIMER_ASSIGNMENT_PLUGIN_URL . 'blocks/my-submissions/style.css',
			[],
			PRESSPRIMER_ASSIGNMENT_VERSION
		);

		// Register block type.
		register_block_type(
			'pressprimer-assignment/my-submissions',
			[
				'api_version'     => 3,
				'title'           => __( 'PPA My Submissions', 'pressprimer-assignment' ),
				'description'     => __( 'Display a list of the current user\'s assignment submissions.', 'pressprimer-assignment' ),
				'category'        => 'pressprimer-assignment',
				'icon'            => 'list-view',
				'supports'        => [
					'html'  => false,
					'align' => true,
				],
				'editor_script'   => 'pressprimer-assignment-my-submissions-block-editor',
				'editor_style'    => 'pressprimer-assignment-my-submissions-block-editor-style',
				'style'           => 'pressprimer-assignment-my-submissions-block-style',
				'render_callback' => [ $this, 'render_my_submissions_block' ],
				'attributes'      => [
					'perPage'    => [
						'type'    => 'number',
						'default' => 10,
					],
					'showStatus' => [
						'type'    => 'boolean',
						'default' => true,
					],
					'showScore'  => [
						'type'    => 'boolean',
						'default' => true,
					],
					'showDate'   => [
						'type'    => 'boolean',
						'default' => true,
					],
				],
			]
		);
	}

	/**
	 * Render My Submissions block
	 *
	 * @since 1.0.0
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered block HTML.
	 */
	public function render_my_submissions_block( $attributes ) {
		// Get attributes with defaults.
		$per_page    = isset( $attributes['perPage'] ) ? absint( $attributes['perPage'] ) : 10;
		$show_status = isset( $attributes['showStatus'] ) ? ( $attributes['showStatus'] ? 'true' : 'false' ) : 'true';
		$show_score  = isset( $attributes['showScore'] ) ? ( $attributes['showScore'] ? 'true' : 'false' ) : 'true';
		$show_date   = isset( $attributes['showDate'] ) ? ( $attributes['showDate'] ? 'true' : 'false' ) : 'true';

		// Use the shortcode handler to render.
		if ( ! class_exists( 'PressPrimer_Assignment_Shortcodes' ) ) {
			return '<div class="ppa-error">' . esc_html__( 'My Submissions renderer not available.', 'pressprimer-assignment' ) . '</div>';
		}

		// Build shortcode attributes.
		$shortcode_atts = [
			'per_page'    => $per_page,
			'show_status' => $show_status,
			'show_score'  => $show_score,
			'show_date'   => $show_date,
		];

		// Call the shortcode handler.
		$shortcodes = new PressPrimer_Assignment_Shortcodes();
		$output     = $shortcodes->render_my_submissions( $shortcode_atts );

		// Wrap in block div.
		return '<div class="wp-block-pressprimer-assignment-my-submissions">' . $output . '</div>';
	}
}
