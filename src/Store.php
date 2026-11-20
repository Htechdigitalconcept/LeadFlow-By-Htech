<?php
namespace HTECH\LeadFlow;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

final class Store {
	public const SCHEMA = '1';
	public const STAGES = array(
		'new'        => 'New',
		'contacted'  => 'Contacted',
		'interested' => 'Interested',
		'qualified'  => 'Qualified',
		'converted'  => 'Converted',
		'lost'       => 'Lost',
	);
	public const CAPS   = array( 'view', 'edit', 'delete', 'export', 'forms', 'settings', 'analytics' );
	public const TABLES = array( 'forms', 'leads', 'notes', 'activities', 'followups', 'guard' );

	public static function table( string $name ): string {
		global $wpdb;
		if ( ! in_array( $name, self::TABLES, true ) ) {
			throw new \InvalidArgumentException( 'Unknown table' ); }
		return $wpdb->prefix . 'htecl_' . $name;
	}

	public static function activate( bool $network = false ): void {
		if ( $network && is_multisite() ) {
			wp_die( 'Activate HTECH LeadFlow separately on each site.' ); }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collation = $wpdb->get_charset_collate();
		$schema    = array(
			'forms'      => 'id bigint unsigned NOT NULL AUTO_INCREMENT,
                title varchar(190) NOT NULL,
                active tinyint NOT NULL DEFAULT 1,
                config longtext NOT NULL,
                created datetime NOT NULL,
                PRIMARY KEY  (id)',
			'leads'      => "id bigint unsigned NOT NULL AUTO_INCREMENT,
                form_id bigint unsigned NOT NULL DEFAULT 0,
                name varchar(190) NOT NULL DEFAULT '',
                email varchar(190) NOT NULL DEFAULT '',
                phone varchar(32) NOT NULL DEFAULT '',
                company varchar(190) NOT NULL DEFAULT '',
                message text NOT NULL,
                source varchar(190) NOT NULL DEFAULT 'Unavailable',
                status varchar(20) NOT NULL DEFAULT 'new',
                assigned bigint unsigned NOT NULL DEFAULT 0,
                score smallint unsigned NOT NULL DEFAULT 0,
                fields longtext NOT NULL,
                attribution longtext NOT NULL,
                landing varchar(512) NOT NULL DEFAULT '',
                campaign varchar(190) NOT NULL DEFAULT '',
                fingerprint char(64) DEFAULT NULL,
                created datetime NOT NULL,
                updated datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY fingerprint (fingerprint),
                KEY created (created),
                KEY status_date (status,created),
                KEY form_id (form_id),
                KEY email (email),
                KEY phone (phone),
                KEY assigned (assigned),
                KEY source_date (source,created),
                KEY campaign (campaign)",
			'notes'      => 'id bigint unsigned NOT NULL AUTO_INCREMENT,
                lead_id bigint unsigned NOT NULL,
                actor bigint unsigned NOT NULL DEFAULT 0,
                body text NOT NULL,
                created datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY lead_id (lead_id,id)',
			'activities' => 'id bigint unsigned NOT NULL AUTO_INCREMENT,
                lead_id bigint unsigned NOT NULL,
                actor bigint unsigned NOT NULL DEFAULT 0,
                body text NOT NULL,
                created datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY lead_id (lead_id,id)',
			'followups'  => 'id bigint unsigned NOT NULL AUTO_INCREMENT,
                lead_id bigint unsigned NOT NULL,
                due date NOT NULL,
                body text NOT NULL,
                done tinyint NOT NULL DEFAULT 0,
                created datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY lead_id (lead_id),
                KEY done_due (done,due)',
			'guard'      => 'token char(64) NOT NULL,
                hits int unsigned NOT NULL DEFAULT 1,
                expires bigint unsigned NOT NULL,
                PRIMARY KEY  (token),
                KEY expires (expires)',
		);
		foreach ( $schema as $name => $definition ) {
			$table = self::table( $name );
			dbDelta( "CREATE TABLE $table ($definition\n) ENGINE=InnoDB $collation;" );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				return; }
		}
		$role = get_role( 'administrator' );
		if ( $role ) {
			foreach ( self::CAPS as $cap ) {
				$role->add_cap( 'htecl_' . $cap ); }
		}
		add_option( 'htecl_options', Settings::defaults(), '', false );
		update_option( 'htecl_schema', self::SCHEMA, false );
		if ( ! wp_next_scheduled( 'htecl_cleanup' ) ) {
			wp_schedule_event( time() + 300, 'daily', 'htecl_cleanup' ); }
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'htecl_cleanup' ); }
	public static function now(): string {
		return gmdate( 'Y-m-d H:i:s' ); }
	public static function json( $value ): string {
		return wp_json_encode( $value, JSON_INVALID_UTF8_SUBSTITUTE ) ?: '{}'; }
	public static function decode( $value ): array {
		return (array) ( json_decode( (string) $value, true ) ?? array() ); }
	public static function get( string $table, int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', self::table( $table ), $id ), ARRAY_A ) ?: null;
	}
	public static function insert( string $table, array $values ): int {
		global $wpdb;
		return $wpdb->insert( self::table( $table ), $values ) ? (int) $wpdb->insert_id : 0;
	}
	public static function update( string $table, int $id, array $values ): bool {
		global $wpdb;
		return false !== $wpdb->update( self::table( $table ), $values, array( 'id' => $id ) );
	}
	public static function transaction( callable $work ): bool {
		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false; }
		$committed = false;
		try {
			$committed = (bool) $work() && false !== $wpdb->query( 'COMMIT' );
			return $committed;
		} finally {
			if ( ! $committed ) {
				$wpdb->query( 'ROLLBACK' ); }
		}
	}
	public static function lock( int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d FOR UPDATE', self::table( 'leads' ), $id ), ARRAY_A ) ?: null;
	}
	public static function activity( int $lead, string $body ): int {
		return self::insert(
			'activities',
			array(
				'lead_id' => $lead,
				'actor'   => get_current_user_id(),
				'body'    => $body,
				'created' => self::now(),
			)
		);
	}
	public static function create( array $values ): int {
		$id = 0;
		$ok = self::transaction(
			static function () use ( $values, &$id ) {
				$id = self::insert(
					'leads',
					$values + array(
						'created' => self::now(),
						'updated' => self::now(),
					)
				);
				return $id && self::activity( $id, 'Lead created' );
			}
		);
		return $ok ? $id : 0;
	}
	public static function delete( int $id ): bool {
		global $wpdb;
		return self::transaction(
			static function () use ( $id, $wpdb ) {
				if ( ! self::lock( $id ) ) {
					return false; }
				foreach ( array( 'notes', 'activities', 'followups' ) as $child ) {
					if ( false === $wpdb->delete( self::table( $child ), array( 'lead_id' => $id ) ) ) {
						return false; }
				}
				return 1 === $wpdb->delete( self::table( 'leads' ), array( 'id' => $id ) );
			}
		);
	}
	public static function children( string $table, int $id, int $limit = 100 ): array {
		global $wpdb;
		if ( ! in_array( $table, array( 'activities', 'notes', 'followups' ), true ) ) {
			return array(); }
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE lead_id=%d ORDER BY id DESC LIMIT %d', self::table( $table ), $id, $limit ), ARRAY_A ) ?: array();
	}
	public static function duplicates( string $email, string $phone, int $except = 0 ): array {
		global $wpdb;
		if ( $email === '' && $phone === '' ) {
			return array(); }
		return $wpdb->get_results( $wpdb->prepare( "SELECT id,name,email,phone FROM %i WHERE id<>%d AND ((email<>'' AND email=%s) OR (phone<>'' AND phone=%s)) ORDER BY id DESC LIMIT 20", self::table( 'leads' ), $except, $email, $phone ), ARRAY_A ) ?: array();
	}
	public static function forms( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', self::table( 'forms' ), $limit, $offset ), ARRAY_A ) ?: array();
	}
	public static function where( array $filters, string $date = 'created' ): array {
		global $wpdb;
		if ( ! in_array( $date, array( 'created' ), true ) ) {
			$date = 'created'; }
		$query = ' WHERE 1=1';
		$args  = array();
		foreach ( array( 'source', 'status' ) as $key ) {
			$value = Security::text( $filters[ $key ] ?? '' );
			if ( $value !== '' && ( $key !== 'status' || isset( self::STAGES[ $value ] ) ) ) {
				$query .= " AND $key=%s";
				$args[] = $value; }
		}
		foreach ( array( 'from', 'to' ) as $key ) {
			$value = Security::date( Security::text( $filters[ $key ] ?? '' ) );
			if ( $value ) {
				$midnight = new \DateTimeImmutable( $value, wp_timezone() );
				if ( $key === 'to' ) {
					$midnight = $midnight->modify( '+1 day' ); }
				$query .= ' AND ' . $date . ( $key === 'from' ? '>=' : '<' ) . '%s';
				$args[] = $midnight->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			}
		}
		$search = Security::text( $filters['q'] ?? '' );
		if ( $search !== '' ) {
			$query .= ' AND (name LIKE %s OR email LIKE %s OR phone LIKE %s OR company LIKE %s)';
			$args   = array_merge( $args, array_fill( 0, 4, '%' . $wpdb->esc_like( $search ) . '%' ) );
		}
		return array( $query, $args );
	}
	public static function leads( array $filters, int $limit = 30, int $offset = 0 ): array {
		global $wpdb;
		[$where, $args] = self::where( $filters );
		$sort           = Security::text( $filters['sort'] ?? '' );
		$order          = array(
			'old'   => 'created ASC,id ASC',
			'name'  => 'name ASC,id DESC',
			'score' => 'score DESC,id DESC',
		)[ $sort ] ?? 'created DESC,id DESC';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i $where ORDER BY $order LIMIT %d OFFSET %d", array_merge( array( self::table( 'leads' ) ), $args, array( $limit, $offset ) ) ), ARRAY_A ) ?: array();
	}
	public static function groups( string $column, array $filters, int $limit = 12, bool $nonempty = false ): array {
		global $wpdb;
		if ( ! in_array( $column, array( 'source', 'status', 'form_id', 'landing', 'campaign' ), true ) ) {
			return array(); }
		[$where, $args] = self::where( $filters );
		if ( $nonempty ) {
			$where .= " AND $column<>''"; }
		return $wpdb->get_results( $wpdb->prepare( "SELECT $column AS label, COUNT(*) AS total FROM %i $where GROUP BY $column ORDER BY total DESC,label ASC LIMIT %d", array_merge( array( self::table( 'leads' ) ), $args, array( $limit ) ) ), ARRAY_A ) ?: array();
	}
	public static function count( array $filters = array() ): int {
		global $wpdb;
		[$where, $args] = self::where( $filters );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i $where", array_merge( array( self::table( 'leads' ) ), $args ) ) );
	}
	public static function conversion( int $converted, int $total ): float {
		return $total > 0 ? round( 100 * $converted / $total, 1 ) : 0.0; }
}
