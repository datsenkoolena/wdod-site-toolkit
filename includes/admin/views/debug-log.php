<?php
/**
 * View: Debug Log tab.
 *
 * @package WDOD\SiteToolkit
 *
 * @var array  $tail         Result of Debug_Log::tail().
 * @var string $log_path     Absolute path of the log file.
 * @var bool   $log_exists   Whether the file exists.
 * @var bool   $log_writable Whether the file can be truncated.
 * @var int    $log_size     File size in bytes.
 * @var string $clear_url    Nonce'd admin-post URL for clearing.
 * @var string $download_url Nonce'd admin-post URL for downloading.
 * @var bool   $debug_log_on Whether WP_DEBUG_LOG is enabled.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wdod-toolkit__intro">
	<?php if ( ! $debug_log_on ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php
				printf(
					/* translators: 1: WP_DEBUG, 2: WP_DEBUG_LOG */
					esc_html__( '%1$s / %2$s are not enabled, so WordPress is not writing to this file right now. Add them to wp-config.php to start collecting notices.', 'wdod-site-toolkit' ),
					'<code>WP_DEBUG</code>',
					'<code>WP_DEBUG_LOG</code>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<table class="widefat wdod-toolkit__table wdod-toolkit__table--meta">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'File', 'wdod-site-toolkit' ); ?></th>
				<td><code><?php echo esc_html( $log_path ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'wdod-site-toolkit' ); ?></th>
				<td>
					<?php if ( $log_exists ) : ?>
						<?php
						printf(
							/* translators: %s: human readable file size */
							esc_html__( 'Exists, %s', 'wdod-site-toolkit' ),
							'<span id="wdod-toolkit-log-size">' . esc_html( size_format( $log_size ) ) . '</span>'
						);
						?>
						<?php echo $log_writable ? '' : ' <em>' . esc_html__( '(read-only for PHP)', 'wdod-site-toolkit' ) . '</em>'; ?>
					<?php else : ?>
						<?php esc_html_e( 'Not created yet', 'wdod-site-toolkit' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>

	<p class="wdod-toolkit__actions">
		<a href="<?php echo esc_url( $download_url ); ?>" class="button<?php echo $log_exists ? '' : ' disabled'; ?>" <?php echo $log_exists ? '' : 'aria-disabled="true"'; ?>>
			<?php esc_html_e( 'Download log', 'wdod-site-toolkit' ); ?>
		</a>
		<a href="<?php echo esc_url( $clear_url ); ?>" class="button button-secondary wdod-toolkit__danger<?php echo ( $log_exists && $log_writable ) ? '' : ' disabled'; ?>" data-confirm="<?php esc_attr_e( 'Clear the debug log? This cannot be undone.', 'wdod-site-toolkit' ); ?>">
			<?php esc_html_e( 'Clear log', 'wdod-site-toolkit' ); ?>
		</a>
		<label class="wdod-toolkit__refresh">
			<input type="checkbox" id="wdod-toolkit-log-autorefresh" checked>
			<?php esc_html_e( 'Auto-refresh every 10 seconds', 'wdod-site-toolkit' ); ?>
		</label>
		<span class="wdod-toolkit__feedback" id="wdod-toolkit-log-status" aria-live="polite"></span>
	</p>
</div>

<?php if ( $tail['truncated'] ) : ?>
	<p class="description" id="wdod-toolkit-log-truncated">
		<?php
		printf(
			/* translators: %s: size in kilobytes */
			esc_html__( 'Showing only the last %s of the file. Download it to see everything.', 'wdod-site-toolkit' ),
			esc_html( size_format( \WDOD\SiteToolkit\Debug_Log::DEFAULT_TAIL_BYTES ) )
		);
		?>
	</p>
<?php else : ?>
	<p class="description" id="wdod-toolkit-log-truncated" hidden>
		<?php
		printf(
			/* translators: %s: size in kilobytes */
			esc_html__( 'Showing only the last %s of the file. Download it to see everything.', 'wdod-site-toolkit' ),
			esc_html( size_format( \WDOD\SiteToolkit\Debug_Log::DEFAULT_TAIL_BYTES ) )
		);
		?>
	</p>
<?php endif; ?>

<pre id="wdod-toolkit-log" class="wdod-toolkit__log" tabindex="0" data-exists="<?php echo $log_exists ? '1' : '0'; ?>">
<?php
if ( ! $log_exists ) {
	esc_html_e( 'No debug.log file exists yet.', 'wdod-site-toolkit' );
} elseif ( '' === trim( $tail['content'] ) ) {
	esc_html_e( 'The log file is empty.', 'wdod-site-toolkit' );
} else {
	echo esc_html( $tail['content'] );
}
?>
</pre>
