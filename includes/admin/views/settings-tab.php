<?php
/**
 * View: generic settings form for one section (Staging, Performance, Security).
 *
 * @package WDOD\SiteToolkit
 *
 * @var string $section     Section slug, e.g. "performance".
 * @var string $title       Section title.
 * @var string $description Intro paragraph.
 * @var array  $fields      key => array( type, label, description, min, max, step ).
 * @var array  $values      Current values for the section.
 * @var string $option_name Option name (Settings::OPTION_NAME).
 * @var string $group       Settings group (Settings::OPTION_GROUP).
 */

defined( 'ABSPATH' ) || exit;
?>
<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" class="wdod-toolkit__form">
	<?php settings_fields( $group ); ?>
	<input type="hidden" name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $section ); ?>][_submitted]" value="1">

	<h2><?php echo esc_html( $title ); ?></h2>
	<p><?php echo esc_html( $description ); ?></p>

	<table class="form-table" role="presentation">
		<tbody>
		<?php foreach ( $fields as $wdod_site_toolkit_key => $wdod_site_toolkit_field ) : ?>
			<?php
			$wdod_site_toolkit_field_id   = 'wdod-toolkit-' . $section . '-' . $wdod_site_toolkit_key;
			$wdod_site_toolkit_field_name = $option_name . '[' . $section . '][' . $wdod_site_toolkit_key . ']';
			$wdod_site_toolkit_value      = isset( $values[ $wdod_site_toolkit_key ] ) ? $values[ $wdod_site_toolkit_key ] : '';
			$wdod_site_toolkit_type       = isset( $wdod_site_toolkit_field['type'] ) ? $wdod_site_toolkit_field['type'] : 'checkbox';
			?>
			<tr>
				<th scope="row">
					<?php if ( 'checkbox' === $wdod_site_toolkit_type ) : ?>
						<?php echo esc_html( $wdod_site_toolkit_field['label'] ); ?>
					<?php else : ?>
						<label for="<?php echo esc_attr( $wdod_site_toolkit_field_id ); ?>"><?php echo esc_html( $wdod_site_toolkit_field['label'] ); ?></label>
					<?php endif; ?>
				</th>
				<td>
					<?php if ( 'checkbox' === $wdod_site_toolkit_type ) : ?>
						<label for="<?php echo esc_attr( $wdod_site_toolkit_field_id ); ?>">
							<input type="checkbox" id="<?php echo esc_attr( $wdod_site_toolkit_field_id ); ?>" name="<?php echo esc_attr( $wdod_site_toolkit_field_name ); ?>" value="1" <?php checked( ! empty( $wdod_site_toolkit_value ) ); ?>>
							<?php echo esc_html( $wdod_site_toolkit_field['label'] ); ?>
						</label>
					<?php elseif ( 'number' === $wdod_site_toolkit_type ) : ?>
						<input type="number" class="small-text" id="<?php echo esc_attr( $wdod_site_toolkit_field_id ); ?>" name="<?php echo esc_attr( $wdod_site_toolkit_field_name ); ?>" value="<?php echo esc_attr( $wdod_site_toolkit_value ); ?>"
							<?php echo isset( $wdod_site_toolkit_field['min'] ) ? 'min="' . esc_attr( $wdod_site_toolkit_field['min'] ) . '"' : ''; ?>
							<?php echo isset( $wdod_site_toolkit_field['max'] ) ? 'max="' . esc_attr( $wdod_site_toolkit_field['max'] ) . '"' : ''; ?>
							<?php echo isset( $wdod_site_toolkit_field['step'] ) ? 'step="' . esc_attr( $wdod_site_toolkit_field['step'] ) . '"' : ''; ?>>
					<?php else : ?>
						<input type="text" class="regular-text" id="<?php echo esc_attr( $wdod_site_toolkit_field_id ); ?>" name="<?php echo esc_attr( $wdod_site_toolkit_field_name ); ?>" value="<?php echo esc_attr( $wdod_site_toolkit_value ); ?>">
					<?php endif; ?>

					<?php if ( ! empty( $wdod_site_toolkit_field['description'] ) ) : ?>
						<p class="description"><?php echo esc_html( $wdod_site_toolkit_field['description'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php submit_button( __( 'Save changes', 'wdod-site-toolkit' ) ); ?>
</form>
