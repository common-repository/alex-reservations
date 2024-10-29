<?php

namespace Alexr\Models\Traits;

use Alexr\Models\Booking;
use Alexr\Models\BookingRecurring;

trait BookingHasRecurring {

	public function recurringConfiguration()
	{
		return $this->hasOne(BookingRecurring::class, 'original_booking_id');
	}

	public function originalBooking()
	{
		return $this->belongsTo(Booking::class, 'original_booking_id');
	}

	public function recurrences()
	{
		return $this->hasMany(Booking::class, 'original_booking_id');
	}
}
