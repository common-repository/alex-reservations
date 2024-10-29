<?php

namespace Alexr\Http\Controllers;

use Alexr\Enums\BookingStatus;
use Alexr\Events\EventBookingRecurringCreated;
use Alexr\Events\EventBookingRecurringModified;
use Alexr\Models\Booking;
use Alexr\Models\BookingRecurring;
use Alexr\Models\Restaurant;
use Cake\Chronos\Chronos;
use Evavel\Eva;
use Evavel\Http\Controllers\Controller;
use Evavel\Http\Request\Request;
use Evavel\Query\Query;

class BookingsRecurringController extends Controller
{
	private $restaurant = null;

	public function index(Request $request)
	{
		$bookingId = $request->params['bookingId'];
		$booking = Booking::find($bookingId);

		// Determinar la reserva principal
		$parentBooking = $booking->original_booking_id
			? Booking::find($booking->original_booking_id)
			: $booking;

		// Obtener todas las reservas recurrentes asociadas
		/*$associatedBookings = $parentBooking->recurrences
			->filter(function($booking) use($parentBooking) { return $booking->id != $parentBooking->id; })
			->toArrayEachModel();*/

		// Obtener la configuración de recurrencia
		$configuration = BookingRecurring::where('original_booking_id', $parentBooking->id)->first();

		if (!$configuration) {
			$configuration = [
				'is_repeating' => false,
				'every_counter' => 1,
				'num_occurrences' => 12
			];
		}

		// Generar fechas futuras basadas en la configuración
		$bookings = [];
		if(is_object($configuration) && $configuration->is_repeating) {
			$startDate = Chronos::parse($parentBooking->date);
			$futureDates = $this->generateFutureDates($startDate, $configuration->every_counter, $configuration->num_occurrences);

			// Preparar el array de bookings
			foreach ($futureDates as $date) {
				$existingBooking = Booking::where('original_booking_id', $parentBooking->id)
				                          ->where('date', $date->toDateString())
				                          ->first();

				if ($existingBooking) {
					$bookings[$date->toDateString()] = $existingBooking;
				} else {
					// Aquí puedes definir la lógica para determinar el mensaje apropiado
					$result = $this->isDateAvailableForBooking($parentBooking, $date->toDateString());
					if ($result['success']) {
						$bookings[$date->toDateString()] = __eva("Booking does not exist");
					} else {
						$bookings[$date->toDateString()] = $result['error'];
					}
				}
			}
		}


		return $this->response([
			'success' => true,
			'parent_booking' => $parentBooking,
			//'bookings' => $associatedBookings,
			'bookings' => $bookings,
			'configuration' => $configuration
		]);
	}

	public function update(Request $request)
	{
		$params = $request->params;

		$bookingId = $params['booking_id'];
		$configuration = $params['configuration'];
		$booking = Booking::find($bookingId);

		// If configuration doesn't exist, create it
		$config_is_new = false;
		$recurringBooking = BookingRecurring::where('original_booking_id', $bookingId)->first();
		if (!$recurringBooking) {
			$config_is_new = true;
			$recurringBooking = new BookingRecurring();
		}
		$data = [
			'original_booking_id' => $booking->id,
			'restaurant_id' => $booking->restaurant_id,
			'is_repeating' => $configuration['is_repeating'],
			'every_counter' => $configuration['every_counter'],
			'num_occurrences' => $configuration['num_occurrences']
		];

		$old_data = [];
		if (!$config_is_new) {
			$old_data = [
				'is_repeating' => $recurringBooking->is_repeating,
				'every_counter' => $recurringBooking->every_counter,
				'num_occurrences' => $recurringBooking->num_occurrences
			];
		}

		foreach ($data as $key => $value) {
			$recurringBooking->{$key} = $value;
		}
		$recurringBooking->save();

		// Event
		$user = Eva::make('user');
		if ($config_is_new) {
			evavel_event(new EventBookingRecurringCreated($booking, $data, $user));
		} else {
			evavel_event(new EventBookingRecurringModified($booking, $data, $old_data, $user));
		}

		if ($configuration['is_repeating']) {
			$booking->is_recurring = true;
			$booking->original_booking_id = $booking->id;
			$booking->save();

			$this->createFutureBookings($booking, $configuration);
			$message = __eva('Recurring bookings have been updated successfully.');
		}
		else {
			$booking->is_recurring = false;
			$booking->original_booking_id = null;
			$booking->save();
			$this->deleteFutureBookings($bookingId, $booking->date);
			$message = __eva('Recurring bookings have been disabled and future bookings have been deleted.');
		}

		return $this->response([
			'success' => true,
			'message' => $message
		]);
	}

	private function createFutureBookings(Booking $parentBooking, array $configuration)
	{
		$startDate = Chronos::parse($parentBooking->date);
		$dates = $this->generateFutureDates($startDate, $configuration['every_counter'], $configuration['num_occurrences']);

		foreach ($dates as $date) {
			$existingBooking = Booking::where('original_booking_id', $parentBooking->id)
			                          ->where('date', $date->toDateString())
			                          ->first();

			if (!$existingBooking)
			{
				$result = $this->isDateAvailableForBooking($parentBooking, $date->toDateString());
				if ($result['success']) {
					$newBooking = $parentBooking->replicate(['gateway', 'gateway_token', 'amount']);
					$newBooking->date = $date->toDateString();
					$newBooking->is_recurring = true;
					if ($newBooking->status != BookingStatus::BOOKED) {
						$newBooking->status = BookingStatus::BOOKED;
					}
					$newBooking->original_booking_id = $parentBooking->id;
					$newBooking->save();
				}

			}
		}

		// Borrar las que sobran
		$last_date = end($dates);
		$this->deleteFutureBookings($parentBooking->id, $last_date->toDateString());
	}

	private function isDateAvailableForBooking($parentBooking, $dateString)
	{
		if (!$this->restaurant) {
			$this->restaurant = Restaurant::find($parentBooking->restaurant_id);
		}

		if ($this->restaurant->isDateClosed($dateString)) {
			return ['success' => false, 'error' => __eva('Restaurant is closed')];
		}

		$service = alexr_get_service($parentBooking->shift_event_id);
		if (!$service) {
			return ['success' => false, 'error' => __eva('Service not found')];
		}

		if (!$service->isDateTimeBookable($dateString, $parentBooking->time)) {
			return ['success' => false, 'error' => __eva('Date time not available for shift')];
		}

		if ($service->isSlotClosed($dateString, $parentBooking->time)) {
			return ['success' => false, 'error' => __eva('Slot closed for shift')];
		}

		return ['success' => true];
	}

	private function generateFutureDates(Chronos $startDate, int $everyCounter, int $numOccurrences)
	{
		$dates = [];
		$currentDate = $startDate->addWeeks($everyCounter); // Start from next occurrence

		for ($i = 1; $i <= $numOccurrences; $i++) {
			$dates[] = $currentDate;
			$currentDate = $currentDate->addWeeks($everyCounter);
		}

		return $dates;
	}

	private function deleteFutureBookings(int $parentBookingId, string $startDate)
	{
		Booking::where('original_booking_id', $parentBookingId)
		       ->where('date', '>', $startDate)
				->where('status', BookingStatus::BOOKED)
		       ->delete();
	}
}
