<?php

namespace Alexr\Models;

use Evavel\Models\Model;

class BookingRecurring extends Model
{
	public static $table_name = 'bookings_recurring';

	protected $casts = [
		//'start_date' => 'date',
		//'end_date' => 'date',
		'day_of_week' => 'integer',
		'day_of_month' => 'integer',
		'is_repeating' => 'boolean',
		'num_occurrences' => 'integer',
		'every_counter' => 'integer'
	];

	/**
	 * Get the original booking that owns the recurring configuration.
	 */
	public function originalBooking()
	{
		return $this->belongsTo(Booking::class, 'original_booking_id');
	}

	/**
	 * Get all the recurring bookings associated with this configuration.
	 */
	/*public function recurringBookings()
	{
		return $this->hasMany(Booking::class, 'original_booking_id', 'original_booking_id')
		            ->where('is_recurring', true);
	}*/

}
