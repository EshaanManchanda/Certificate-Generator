<?php
declare(strict_types=1);

namespace CertificateGenerator\Services;

use CertificateGenerator\Database\CustomTables;

/**
 * Manages additional/dynamic fields for entities.
 * Handles split/merge between core columns and extra_fields JSON.
 */
class FieldManager {

	/**
	 * Core field keys for each entity type.
	 * These get their own columns. Everything else goes to extra_fields JSON.
	 */
	private const CORE_FIELDS = array(
		'students' => array( 'student_name', 'email', 'phone', 'school_id', 'school_name', 'enrollment_date', 'graduation_date', 'status', 'wp_post_id', 'id', 'created_at', 'updated_at', 'extra_fields' ),
		'teachers' => array( 'teacher_name', 'email', 'phone', 'school_id', 'school_name', 'department', 'hire_date', 'status', 'wp_post_id', 'id', 'created_at', 'updated_at', 'extra_fields' ),
		'schools'  => array( 'school_name', 'address', 'city', 'state', 'country', 'postal_code', 'phone', 'email', 'website', 'principal_name', 'status', 'wp_post_id', 'id', 'created_at', 'updated_at', 'extra_fields' ),
	);

	/**
	 * Split flat data into core fields + extra fields JSON.
	 *
	 * @param string $entity_type 'students', 'teachers', 'schools'
	 * @param array  $flat_data All field data (core + extra)
	 * @return array{core: array, extra: array|null}
	 */
	public static function split( string $entity_type, array $flat_data ): array {
		$core_keys = self::CORE_FIELDS[ $entity_type ] ?? array();
		$core      = array();
		$extra     = array();

		foreach ( $flat_data as $key => $value ) {
			// Strip 'field_' prefix if present
			$clean_key = preg_replace( '/^field_/', '', $key );

			if ( in_array( $clean_key, $core_keys, true ) || in_array( $key, $core_keys, true ) ) {
				$core[ $clean_key ] = $value;
			} elseif ( $value !== '' && $value !== null ) {
				$extra[ $clean_key ] = $value;
			}
		}

		return array(
			'core'  => $core,
			'extra' => ! empty( $extra ) ? $extra : null,
		);
	}

	/**
	 * Merge core data + extra fields JSON into flat array for display/export.
	 *
	 * @param array       $core_data Core column data
	 * @param string|null $extra_json JSON string from extra_fields column
	 * @return array
	 */
	public static function merge( array $core_data, ?string $extra_json = null ): array {
		$extra = ! empty( $extra_json ) ? json_decode( $extra_json, true ) : array();
		if ( ! is_array( $extra ) ) {
			$extra = array();
		}

		// Remove internal fields from output
		unset( $core_data['extra_fields'] );

		return array_merge( $core_data, $extra );
	}

	/**
	 * Get all extra field keys across all records of an entity type.
	 * Used for CSV export header generation.
	 *
	 * @param string $entity_type
	 * @return string[]
	 */
	public static function get_all_extra_keys( string $entity_type ): array {
		global $wpdb;
		$table = CustomTables::instance()->get_table( $entity_type );
		if ( empty( $table ) ) {
			return array();
		}

		$rows     = $wpdb->get_col( "SELECT extra_fields FROM $table WHERE extra_fields IS NOT NULL" );
		$all_keys = array();

		foreach ( $rows as $json ) {
			$data = json_decode( $json, true );
			if ( is_array( $data ) ) {
				$all_keys = array_merge( $all_keys, array_keys( $data ) );
			}
		}

		return array_values( array_unique( $all_keys ) );
	}

	/**
	 * Prepare data for database insert/update.
	 * Splits flat data and encodes extra fields as JSON.
	 *
	 * @param string $entity_type
	 * @param array  $flat_data
	 * @return array Ready for $wpdb->insert() or $wpdb->update()
	 */
	public static function prepare_for_db( string $entity_type, array $flat_data ): array {
		$split = self::split( $entity_type, $flat_data );

		$db_data = $split['core'];
		if ( $split['extra'] !== null ) {
			$db_data['extra_fields'] = wp_json_encode( $split['extra'] );
		}

		return $db_data;
	}

	/**
	 * Prepare database row for display/API response.
	 * Merges core columns with extra fields.
	 *
	 * @param array $row Database row
	 * @return array
	 */
	public static function prepare_for_display( array $row ): array {
		return self::merge( $row, $row['extra_fields'] ?? null );
	}

	/**
	 * Query entities by extra field value.
	 *
	 * @param string $entity_type
	 * @param string $field_key Extra field key (without 'field_' prefix)
	 * @param string $value Value to match
	 * @param string $comparison '=', '!=', 'LIKE', etc.
	 * @return array
	 */
	public static function query_by_extra_field( string $entity_type, string $field_key, string $value, string $comparison = '=' ): array {
		global $wpdb;
		$table = CustomTables::instance()->get_table( $entity_type );
		if ( empty( $table ) ) {
			return array();
		}

		$allowed_ops = array( '=', '!=', 'LIKE', 'NOT LIKE', '>', '<', '>=', '<=' );
		if ( ! in_array( $comparison, $allowed_ops, true ) ) {
			$comparison = '=';
		}

		$sql = $wpdb->prepare(
			"SELECT * FROM $table WHERE extra_fields->>%s $comparison %s",
			'$.' . $field_key,
			$value
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return array_map( array( self::class, 'prepare_for_display' ), $rows );
	}
}
