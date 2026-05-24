<?php
/**
 * Shared media-library input partial.
 *
 * Required variables (set before including):
 *   string $field_id    — HTML id for the <input>
 *   string $field_name  — HTML name for the <input>
 *   string $value       — Current URL value
 *
 * Optional variables:
 *   string $placeholder — Input placeholder text
 *   string $button_label — Button text (default "Choose Image")
 *   string $description — Help text below the control
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string $field_id */
/** @var string $field_name */
/** @var string|null $value */
$_cg_placeholder  = $placeholder ?? 'Paste URL or choose from media library';
$_cg_button_label = $button_label ?? 'Choose Image';
$_cg_description  = $description ?? '';
$_cg_preview_id   = $field_id . '-preview';
?>
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
	<input type="url"
			id="<?php echo esc_attr( $field_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			value="<?php echo esc_url( $value ?? '' ); ?>"
			class="regular-text"
			style="flex:1"
			placeholder="<?php echo esc_attr( $_cg_placeholder ); ?>">
	<button type="button"
			class="button button-secondary cg-media-btn"
			data-input="#<?php echo esc_attr( $field_id ); ?>"
			data-preview="#<?php echo esc_attr( $_cg_preview_id ); ?>"
			data-title="<?php echo esc_attr( $_cg_button_label ); ?>"
			data-button-text="<?php echo esc_attr( $_cg_button_label ); ?>">
		📁 <?php echo esc_html( $_cg_button_label ); ?>
	</button>
</div>
<?php if ( ! empty( $_cg_description ) ) : ?>
	<p class="description"><?php echo esc_html( $_cg_description ); ?></p>
<?php endif; ?>
<div id="<?php echo esc_attr( $_cg_preview_id ); ?>"
	style="margin-top:10px;<?php echo empty( $value ) ? 'display:none' : ''; ?>">
	<?php if ( ! empty( $value ) ) : ?>
		<img src="<?php echo esc_url( $value ); ?>"
			style="max-width:300px;max-height:180px;border:1px solid #ddd;border-radius:4px;display:block;">
	<?php endif; ?>
</div>
