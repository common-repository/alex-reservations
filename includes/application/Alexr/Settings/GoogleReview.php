<?php

namespace Alexr\Settings;

use Evavel\Http\Request\AppSettingsRequest;
use Evavel\Models\SettingCustomized;

class GoogleReview extends SettingCustomized
{
	public static $table_name = 'restaurant_setting';
	public static $meta_key = 'google_review';
	public static $pivot_tenant_field = 'restaurant_id';

	public static $custom_component = 'EditGoogleReview';

	public static function label()
	{
		return __eva('Google Review');
	}

	public static function getId($tenantId)
	{
		$googleReview =GoogleReview::where(self::$pivot_tenant_field, $tenantId)->first();

		if ($googleReview) {
			return $googleReview->id;
		}

		// Create if does not exists yet
		$googleReview = GoogleReview::create([
			'restaurant_id' => $tenantId,
			'meta_value' => [
				'config' => [
					'enabled' => false,
					'place_id' => '',
					'stars' => 4
				]
			]
		]);

		return $googleReview->id;
	}

	public static function getItems(AppSettingsRequest $request, $tenantId)
	{
		$googleReviewId = GoogleReview::getId($tenantId);
		$googleReview = GoogleReview::find($googleReviewId);

		return $googleReview->config;
	}

}
