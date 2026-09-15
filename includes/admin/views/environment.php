<?php
/**
 * View: Environment tab.
 *
 * @package WDOD\SiteToolkit
 *
 * @var array  $report      Sections from Environment_Report::to_array().
 * @var string $report_text Plain-text version for copy & paste.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wdod-toolkit__intro">
	<p><?php esc_html_e( 'A snapshot of the server, WordPress and active code. Use "Copy report" to paste it into a support ticket or a hand-over document.', 'wdod-site-toolkit' ); ?></p>
	<p>
		<button type="button" class="button button-primary" id="wdod-toolkit-copy-report" data-target="wdod-toolkit-report-text">
			<?php esc_html_e( 'Copy report', 'wdod-site-toolkit' ); ?>
		</button>
		<span class="wdod-toolkit__feedback" id="wdod-toolkit-copy-feedback" aria-live="polite"></span>
	</p>
</div>

<div class="wdod-toolkit__grid">
	<?php foreach ( $report as $wdod_site_toolkit_section_slug => $wdod_site_toolkit_section ) : ?>
		<div class="wdod-toolkit__card" id="wdod-toolkit-section-<?php echo esc_attr( $wdod_site_toolkit_section_slug ); ?>">
			<h2><?php echo esc_html( $wdod_site_toolkit_section['label'] ); ?></h2>
			<table class="widefat striped wdod-toolkit__table">
				<tbody>
				<?php foreach ( $wdod_site_toolkit_section['items'] as $wdod_site_toolkit_item_key => $wdod_site_toolkit_item ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $wdod_site_toolkit_item['label'] ); ?></th>
						<td><code><?php echo esc_html( $wdod_site_toolkit_item['value'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endforeach; ?>
</div>

<h2><?php esc_html_e( 'Plain-text report', 'wdod-site-toolkit' ); ?></h2>
<textarea id="wdod-toolkit-report-text" class="large-text code wdod-toolkit__textarea" rows="18" readonly><?php echo esc_textarea( $report_text ); ?></textarea>
