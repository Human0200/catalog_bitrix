<?php
/**
 *    Timeline
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
	Bitrix\Main\Localization\Loc,
    Bitrix\Main\Config\Option;

class CrmNotif
{
	const TYPE_INFO = 0;
    const TYPE_ERROR = 1;
    const TYPE_WARNING = 2;
    const TYPE_SUCCESS = 3;

	public static function sendMsg($message, $code, $type=self::TYPE_INFO, $deal_id=false) {
		$result = false;
		// Send message for system notification
		if (Settings::get('notif_errors') && $type == self::TYPE_ERROR) {
			$deal_link = '';
			if ($deal_id) {
				$deal_link = Loc::getMessage('SP_CI_CRMNOTIF_DEAL_LINK', [
					'#DEAL_ID#'  => $deal_id,
					'#DEAL_URL#' => '/crm/deal/details/' . $deal_id . '/',
				]);
			}
			$message_format = Loc::getMessage('SP_CI_CRMNOTIF_NOTIF', [
				'#DEAL_LINK#' => $deal_link,
				'#TYPE_NAME#' => Loc::getMessage('SP_CI_CRMNOTIF_MSG_' . $type),
				'#CODE#'      => $code,
				'#MESSAGE#'   => $message,
			]);
			self::sendNotifMsg($message_format);
		}
		// Send message for deal timeline
		if ($deal_id && UpdateLock::isChanged($deal_id . '_' . $code, 'timeline', $type . $message, true)) {
			switch ($type) {
				case self::TYPE_INFO:
					$timeline_title_color = '#2f3192';
					break;
				case self::TYPE_ERROR:
					$timeline_title_color = '#ee1d24';
					break;
				case self::TYPE_WARNING:
					$timeline_title_color = '#f7977a';
					break;
				case self::TYPE_SUCCESS:
					$timeline_title_color = '#00a650';
					break;
			}
			$message_format = Loc::getMessage('SP_CI_CRMNOTIF_TIMELINE', [
				'#TITLE_COLOR#' => $timeline_title_color,
				'#TYPE_NAME#' => Loc::getMessage('SP_CI_CRMNOTIF_MSG_' . $type),
				'#CODE#' => $code,
				'#MESSAGE#' => $message,
			]);
			$result = self::sendTimelineMsg($deal_id, $message_format);
		}
		return $result;
	}

	public static function sendTimelineMsg($deal_id, $message) {
		$message_id = Rest::execute('crm.timeline.comment.add', [
			'fields' => [
				'ENTITY_ID' => $deal_id,
				'ENTITY_TYPE' => 'deal',
				'COMMENT' => $message,
			]
		]);
		return $message_id;
	}

	public static function sendNotifMsg($message) {
		$req_list = [];
		$req_list['profile'] = [
			'method' => 'profile',
			'params' => []
		];
		$req_list['notify_add'] = [
			'method' => 'im.notify.system.add',
			'params' => [
				'USER_ID' => '$result[profile][ID]',
				'MESSAGE' => $message,
			]
		];
		$resp = Rest::batch($req_list);
		$result = $resp['notify_add'];
		return $result;
	}
}
