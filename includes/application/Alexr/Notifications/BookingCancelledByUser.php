<?php

namespace Alexr\Notifications;

use Alexr\Enums\BookingStatus;
use Alexr\Models\Booking;
use Alexr\Models\User;
//use Carbon\Carbon;
use Evavel\Notifications\Notification;

class BookingCancelledByUser extends Notification
{
	public $booking;

	public function __construct(Booking $booking)
	{
		$this->booking = $booking;
	}

	public function toDatabase($user)
	{
		$data = parent::toDatabase($user);

		$data['data'] = [
			'icon' => 'cancelled',
			'booking_id' => $this->booking->id,
			'status' => $this->booking->status,
			'status_label' => BookingStatus::label($this->booking->status),
			'title' => $this->title($user),
			'text' => $this->text($user),
			'link' => $this->link($user)
		];

		$notification = \Alexr\Models\Notification::create($data);
		$notification->save();
	}

	protected function title($user)
	{
		return __eva('BOOKING CANCELLED');
	}

	protected function text($user)
	{
		//$d_formatted = Carbon::createFromFormat('Y-m-d', $this->booking->date)
		//                     ->locale($this->booking->language)
		//                     ->translatedFormat('l j F Y');

		$d_formatted = evavel_date_createFromFormatTranslate('Y-m-d',
			$this->booking->date, $this->booking->language, 'l j F Y');

		$hourminutes = evavel_seconds_to_Hm($this->booking->time);

		$html = '<div><strong>' . $d_formatted . ' - ' . $hourminutes.'</strong></div>';
		$html .= '<p>'.$this->booking->name.' (' . $this->booking->party . ')</p>';

		return $html;
	}

	protected function link($user)
	{
		// this.$router.push({name: 'bookings-list-date', params: {tenantId: this.tenantId, yearmonthday: currentDate} })
		return [
			'name' => 'bookings-list-date',
			'params' => ['tenantId' => $this->booking->restaurant_id, 'yearmonthday' => $this->booking->date]
		];
	}
}