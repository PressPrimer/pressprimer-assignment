<?php
/**
 * Upgrade page view.
 *
 * Rendered by PressPrimer_Assignment_Upgrade_Page::render_page(). Receives
 * the following variables in scope:
 *
 * @var array  $features           Comparison features array (from get_comparison_features).
 * @var array  $tiers              Tier metadata array (from get_tiers), URLs already UTM-tagged.
 * @var string $pricing_hero_url   UTM-tagged pricing URL for the hero CTA.
 * @var string $pricing_footer_url UTM-tagged pricing URL for the footer CTA.
 * @var string $pricing_sticky_url UTM-tagged pricing URL for the sticky bar.
 * @var string $logo_url           URL of the white PressPrimer logo SVG.
 * @var string $hero_mascot_url    URL of the hero mascot image.
 * @var string $footer_mascot_url  URL of the footer mascot image.
 * @var PressPrimer_Assignment_Upgrade_Page $upgrade_page The controller instance, for render_cell_value().
 *
 * @package PressPrimer_Assignment
 * @subpackage Admin
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Allowlist for the comparison cell markup produced by render_cell_value().
$ppa_upgrade_cell_allowed_html = [
	'span' => [
		'class'      => true,
		'aria-label' => true,
	],
];
?>
<div class="wrap ppa-upgrade-page">

	<section class="ppa-upgrade-hero">
		<div class="ppa-upgrade-hero-content">
			<img src="<?php echo esc_url( $logo_url ); ?>"
				alt="<?php esc_attr_e( 'PressPrimer', 'pressprimer-assignment' ); ?>"
				class="ppa-upgrade-hero-logo" />
			<h1><?php esc_html_e( 'Unlock the Full PressPrimer Assignment Experience', 'pressprimer-assignment' ); ?></h1>
			<p class="ppa-upgrade-intro">
				<?php esc_html_e( 'You\'re already using the most capable free assignment plugin for WordPress. Premium add-ons add the groups, rubrics, AI-assisted grading, and compliance tools that turn it into a complete assessment platform for your organization.', 'pressprimer-assignment' ); ?>
			</p>
			<a href="<?php echo esc_url( $pricing_hero_url ); ?>"
				class="button button-primary button-hero ppa-upgrade-cta"
				target="_blank"
				rel="noopener noreferrer">
				<?php esc_html_e( 'View Pricing & Upgrade', 'pressprimer-assignment' ); ?>
				<span class="ppa-upgrade-cta-arrow" aria-hidden="true">&rarr;</span>
			</a>
		</div>
		<?php if ( $hero_mascot_url ) : ?>
			<div class="ppa-upgrade-hero-mascot-wrap" aria-hidden="true">
				<img src="<?php echo esc_url( $hero_mascot_url ); ?>"
					alt=""
					class="ppa-upgrade-hero-mascot"
					role="presentation" />
			</div>
		<?php endif; ?>
	</section>

	<section class="ppa-upgrade-tiers" aria-labelledby="ppa-upgrade-tiers-heading">
		<header class="ppa-upgrade-section-header">
			<h2 id="ppa-upgrade-tiers-heading"><?php esc_html_e( 'Choose the Plan That Fits Your Program', 'pressprimer-assignment' ); ?></h2>
			<p><?php esc_html_e( 'Every premium tier includes priority support and a 14-day money-back guarantee.', 'pressprimer-assignment' ); ?></p>
		</header>

		<div class="ppa-upgrade-tier-cards">
			<?php foreach ( $tiers as $tier_slug => $tier ) : ?>
				<?php
				$is_featured = ! empty( $tier['featured'] );
				$card_class  = 'ppa-upgrade-tier-card ppa-upgrade-tier-' . sanitize_html_class( $tier_slug );
				if ( $is_featured ) {
					$card_class .= ' is-featured';
				}
				?>
				<div class="<?php echo esc_attr( $card_class ); ?>">
					<?php if ( $is_featured ) : ?>
						<span class="ppa-upgrade-tier-badge">
							<?php esc_html_e( 'Most Popular', 'pressprimer-assignment' ); ?>
						</span>
					<?php endif; ?>

					<h3 class="ppa-upgrade-tier-name"><?php echo esc_html( $tier['name'] ); ?></h3>
					<p class="ppa-upgrade-tier-tagline"><?php echo esc_html( $tier['tagline'] ); ?></p>
					<p class="ppa-upgrade-tier-description"><?php echo esc_html( $tier['description'] ); ?></p>

					<?php if ( ! empty( $tier['highlights'] ) && is_array( $tier['highlights'] ) ) : ?>
						<ul class="ppa-upgrade-tier-highlights">
							<?php foreach ( $tier['highlights'] as $highlight ) : ?>
								<li>
									<span class="ppa-upgrade-tier-check" aria-hidden="true">&#10003;</span>
									<?php echo esc_html( $highlight ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<a href="<?php echo esc_url( $tier['url'] ); ?>"
						class="button ppa-upgrade-tier-link <?php echo $is_featured ? 'button-primary' : 'button-secondary'; ?>"
						target="_blank"
						rel="noopener noreferrer">
						<?php
						printf(
							/* translators: %s: tier name (Educator, School, or Enterprise) */
							esc_html__( 'Get %s', 'pressprimer-assignment' ),
							esc_html( $tier['name'] )
						);
						?>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="ppa-upgrade-comparison" aria-labelledby="ppa-upgrade-comparison-heading">
		<header class="ppa-upgrade-section-header">
			<h2 id="ppa-upgrade-comparison-heading"><?php esc_html_e( 'Compare Every Feature', 'pressprimer-assignment' ); ?></h2>
			<p><?php esc_html_e( 'A complete side-by-side of what\'s in each plan.', 'pressprimer-assignment' ); ?></p>
		</header>

		<div class="ppa-upgrade-table-scroller">
			<table class="ppa-upgrade-table">
				<thead>
					<tr>
						<th scope="col" class="ppa-upgrade-col-feature">
							<?php esc_html_e( 'Feature', 'pressprimer-assignment' ); ?>
						</th>
						<th scope="col" class="ppa-upgrade-col-tier ppa-upgrade-col-free">
							<span class="ppa-upgrade-col-name"><?php esc_html_e( 'Free', 'pressprimer-assignment' ); ?></span>
						</th>
						<th scope="col" class="ppa-upgrade-col-tier ppa-upgrade-col-educator">
							<span class="ppa-upgrade-col-name"><?php esc_html_e( 'Educator', 'pressprimer-assignment' ); ?></span>
						</th>
						<th scope="col" class="ppa-upgrade-col-tier ppa-upgrade-col-school">
							<span class="ppa-upgrade-col-name"><?php esc_html_e( 'School', 'pressprimer-assignment' ); ?></span>
							<span class="ppa-upgrade-col-pill"><?php esc_html_e( 'Popular', 'pressprimer-assignment' ); ?></span>
						</th>
						<th scope="col" class="ppa-upgrade-col-tier ppa-upgrade-col-enterprise">
							<span class="ppa-upgrade-col-name"><?php esc_html_e( 'Enterprise', 'pressprimer-assignment' ); ?></span>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$ppa_upgrade_current_category = '';
					foreach ( $features as $row ) :
						$row_category = isset( $row['category'] ) ? (string) $row['category'] : '';

						// Print a category header row whenever the category changes.
						if ( $row_category !== $ppa_upgrade_current_category ) :
							$ppa_upgrade_current_category = $row_category;
							?>
							<tr class="ppa-upgrade-category-row">
								<th colspan="5" scope="colgroup">
									<?php echo esc_html( $ppa_upgrade_current_category ); ?>
								</th>
							</tr>
							<?php
						endif;
						?>
						<tr>
							<th scope="row" class="ppa-upgrade-cell-label">
								<?php echo esc_html( isset( $row['feature'] ) ? (string) $row['feature'] : '' ); ?>
							</th>
							<?php foreach ( [ 'free', 'educator', 'school', 'enterprise' ] as $tier_key ) : ?>
								<td class="ppa-upgrade-cell ppa-upgrade-cell-<?php echo esc_attr( $tier_key ); ?>">
									<?php
									echo wp_kses(
										$upgrade_page->render_cell_value( isset( $row[ $tier_key ] ) ? $row[ $tier_key ] : false ),
										$ppa_upgrade_cell_allowed_html
									);
									?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>

	<section class="ppa-upgrade-footer-cta" aria-labelledby="ppa-upgrade-footer-heading">
		<?php if ( $footer_mascot_url ) : ?>
			<div class="ppa-upgrade-footer-mascot-wrap" aria-hidden="true">
				<img src="<?php echo esc_url( $footer_mascot_url ); ?>"
					alt=""
					class="ppa-upgrade-footer-mascot"
					role="presentation" />
			</div>
		<?php endif; ?>
		<div class="ppa-upgrade-footer-content">
			<h2 id="ppa-upgrade-footer-heading"><?php esc_html_e( 'Try Risk-Free for 14 Days', 'pressprimer-assignment' ); ?></h2>
			<p>
				<?php esc_html_e( 'Every premium plan comes with a 14-day money-back guarantee. If PressPrimer Assignment isn\'t the right fit, we\'ll refund your purchase — no questions asked.', 'pressprimer-assignment' ); ?>
			</p>
			<a href="<?php echo esc_url( $pricing_footer_url ); ?>"
				class="button button-primary ppa-upgrade-footer-button"
				target="_blank"
				rel="noopener noreferrer">
				<?php esc_html_e( 'View Pricing & Upgrade', 'pressprimer-assignment' ); ?>
			</a>
		</div>
	</section>

	<div class="ppa-upgrade-sticky-bar" role="region" aria-label="<?php esc_attr_e( 'Upgrade actions', 'pressprimer-assignment' ); ?>">
		<div class="ppa-upgrade-sticky-bar-inner">
			<div class="ppa-upgrade-sticky-bar-text">
				<strong><?php esc_html_e( 'Ready to upgrade PressPrimer Assignment?', 'pressprimer-assignment' ); ?></strong>
				<span><?php esc_html_e( 'Compare plans and pick the tier that fits your program. 14-day money-back guarantee.', 'pressprimer-assignment' ); ?></span>
			</div>
			<a href="<?php echo esc_url( $pricing_sticky_url ); ?>"
				class="button button-primary button-hero ppa-upgrade-sticky-bar-cta"
				target="_blank"
				rel="noopener noreferrer">
				<?php esc_html_e( 'Upgrade', 'pressprimer-assignment' ); ?>
			</a>
		</div>
	</div>

</div>
