<?php
/**
 * Shared From Name / From Email form rows partial.
 *
 * Required variables:
 *   string $from_name   — Current from name value
 *   string $from_email  — Current from email value
 *
 * Optional variables:
 *   string $name_field_name  — HTML name for from name  (default: cg_email_from_name)
 *   string $email_field_name — HTML name for from email (default: cg_email_from_email)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$_name_field  = $name_field_name ?? 'cg_email_from_name';
$_email_field = $email_field_name ?? 'cg_email_from_email';
?>
<tr>
	<th scope="row"><label for="cg_from_name_field">From Name</label></th>
	<td>
		<input type="text"
				id="cg_from_name_field"
				name="<?php echo esc_attr( $_name_field ); ?>"
				value="<?php echo esc_attr( $from_name ?? '' ); ?>"
				class="regular-text">
	</td>
</tr>
<tr>
	<th scope="row"><label for="cg_from_email_field">From Email</label></th>
	<td>
		<input type="email"
				id="cg_from_email_field"
				name="<?php echo esc_attr( $_email_field ); ?>"
				value="<?php echo esc_attr( $from_email ?? '' ); ?>"
				class="regular-text">
	</td>
</tr>
