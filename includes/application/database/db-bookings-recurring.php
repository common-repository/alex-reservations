<?php

use Evavel\Database\DB;

class SRR_DB_Bookings_Recurring extends DB
{
	public static $table_db = '';

	public static function init()
	{
		global $wpdb;
		self::$table_db = $wpdb->prefix.DB::$namespace.'_bookings_recurring';
	}

	public function __construct() {
		parent::__construct();
		$this->table_name = $this->prefix.'_bookings_recurring';
		$this->primary_key = 'id';
		$this->version     = '1.0';
	}

	public function sql_create()
	{
		return "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            restaurant_id bigint(20) DEFAULT NULL,
            original_booking_id bigint(20) NOT NULL,
            is_repeating tinyint DEFAULT 0,
            recurrence_type varchar(20) default 'week',
            every_counter int DEFAULT 1,
            num_occurrences int DEFAULT NULL,
            
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            day_of_week int DEFAULT NULL,
            day_of_month int DEFAULT NULL,
            
            date_created datetime NOT NULL,
			date_modified datetime NOT NULL,
            PRIMARY KEY (id),
            KEY `bookings_recurring_restaurant_id_foreign` (`restaurant_id`),
			CONSTRAINT `bookings_recurring_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `{$this->prefix}_restaurants` (`id`) ON DELETE CASCADE,        
            KEY `bookings_recurring_original_booking_id_foreign` (`original_booking_id`),
            CONSTRAINT `bookings_recurring_original_booking_id_foreign` FOREIGN KEY (`original_booking_id`) REFERENCES `{$this->prefix}_bookings` (`id`) ON DELETE CASCADE
        )";
	}

}

SRR_DB_Bookings_Recurring::init();
