<?php
/**
 * Remote diagnostics logs page
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use \Bitrix\Main\Localization\Loc;

class RemoteDiagLogs
{
	const LOG_FILE_PATH = '/upload/sprod_integr_log.txt';

	/**
	 * Получить путь к файлу логов
	 * @return string
	 */
	private static function getLogFilePath() {
		return $_SERVER['DOCUMENT_ROOT'] . self::LOG_FILE_PATH;
	}

	/**
	 * Проверить существование файла логов
	 * @return bool
	 */
	public static function logFileExists() {
		return file_exists(self::getLogFilePath());
	}

	/**
	 * Прочитать содержимое файла логов (устаревший метод, используйте потоковое чтение)
	 * @return string|false
	 * @deprecated
	 */
	private static function readLogFile() {
		if (!self::logFileExists()) {
			return false;
		}
		return file_get_contents(self::getLogFilePath());
	}

	/**
	 * Потоковое чтение файла логов с обработкой построчно
	 * @param callable $lineProcessor Функция-обработчик строк (принимает номер строки, содержимое строки и байтовую позицию)
	 * @return bool
	 */
	private static function processLogFileStream($lineProcessor) {
		if (!self::logFileExists()) {
			return false;
		}

		$handle = fopen(self::getLogFilePath(), 'r');
		if (!$handle) {
			return false;
		}

		$lineNumber = 0;
		while (($line = fgets($handle)) !== false) {
			$lineNumber++;
			$position = ftell($handle) - strlen($line);
			$lineProcessor($lineNumber, rtrim($line), $position);
		}

		fclose($handle);
		return true;
	}

	/**
	 * Прочитать блок строк из файла начиная с указанной байтовой позиции
	 * @param int $startPosition Байтовая позиция начала чтения
	 * @param int $maxLines Максимальное количество строк для чтения
	 * @param bool $readBackward Читать назад от позиции (для поиска метки перед найденной строкой)
	 * @return array Массив строк
	 */
	private static function readLogBlockAtPosition($startPosition, $maxLines = 50, $readBackward = false) {
		if (!self::logFileExists()) {
			return [];
		}

		$handle = fopen(self::getLogFilePath(), 'r');
		if (!$handle) {
			return [];
		}

		$lines = [];
		
		if ($readBackward) {
			// Читаем назад от позиции
			// Находим начало строки, с которой начнем чтение
			$readStart = max(0, $startPosition - 8192); // Читаем максимум 8KB назад
			fseek($handle, $readStart);
			
			// Пропускаем первую неполную строку, если не в начале файла
			if ($readStart > 0) {
				fgets($handle);
			}
			
			$buffer = [];
			$targetPosition = $startPosition;
			
			// Читаем строки до целевой позиции
			while (($line = fgets($handle)) !== false) {
				$currentPos = ftell($handle);
				$buffer[] = rtrim($line);
				
				if ($currentPos >= $targetPosition) {
					break;
				}
				
				// Ограничиваем размер буфера
				if (count($buffer) > $maxLines * 2) {
					array_shift($buffer);
				}
			}
			
			// Берем последние maxLines строк
			$lines = array_slice($buffer, -$maxLines);
		} else {
			// Читаем вперед от позиции
			fseek($handle, $startPosition);
			$linesRead = 0;
			
			while (($line = fgets($handle)) !== false && $linesRead < $maxLines) {
				$lines[] = rtrim($line);
				$linesRead++;
			}
		}

		fclose($handle);
		return $lines;
	}

	/**
	 * Найти уникальные метки по ID заказа и сделки с временем первого появления
	 * @param int|null $order_id
	 * @param int|null $deal_id
	 * @return array [['label' => string, 'timestamp' => string, 'search_type' => 'order'|'deal'], ...]
	 */
	public static function findLabelsByOrderAndDeal($order_id, $deal_id) {
		$all_labels = [];

		// Поиск по заказу, если указан order_id
		if ($order_id) {
			$order_labels = self::findLabelsWithType($order_id, 'order', '/order\s+' . $order_id . '\b/');
			$all_labels = array_merge($all_labels, $order_labels);
		}

		// Поиск по сделке, если указан deal_id
		if ($deal_id) {
			$deal_labels = self::findLabelsWithType($deal_id, 'deal', '/item\s+' . $deal_id . '\b/');
			$all_labels = array_merge($all_labels, $deal_labels);
		}

		// Убираем дубликаты по названию метки (оставляем первую найденную по времени)
		$unique_labels = [];
		$label_timestamps = [];

		foreach ($all_labels as $label_data) {
			if (!isset($label_timestamps[$label_data['label']])) {
				$label_timestamps[$label_data['label']] = $label_data['timestamp'];
				$unique_labels[] = $label_data;
			}
		}

		// Сортируем по времени (от старых к новым)
		usort($unique_labels, function($a, $b) {
			return strcmp($a['timestamp'], $b['timestamp']);
		});

		return $unique_labels;
	}

	/**
	 * Найти уникальные метки по ID заказа с временем первого появления
	 * @param int $order_id
	 * @return array [['label' => string, 'timestamp' => string], ...]
	 * @deprecated Используйте findLabelsByOrderAndDeal для поиска по обоим типам
	 */
	public static function findLabelsByOrder($order_id) {
		return self::findLabelsByOrderAndDeal($order_id, null);
	}

	/**
	 * Вспомогательный метод для поиска меток с указанием типа поиска
	 * @param int $id
	 * @param string $search_type
	 * @param string $pattern
	 * @return array
	 */
	private static function findLabelsWithType($id, $search_type, $pattern) {
		$labels = [];
		$label_timestamps = [];
		$matching_positions = [];

		// Находим все строки с указанным ID и сохраняем их байтовые позиции
		self::processLogFileStream(function($lineNumber, $line, $position) use ($pattern, &$matching_positions) {
			if (preg_match($pattern, $line)) {
				$matching_positions[] = $position;
			}
		});

		// Для каждой найденной позиции ищем метку в блоке
		foreach ($matching_positions as $position) {
			// Читаем блок назад от найденной строки (метка обычно перед разделителем "---")
			$block_lines = self::readLogBlockAtPosition($position, 30, true);

			// Ищем метку в блоке (ищем разделитель "---" и следующую за ним строку с меткой)
			$label_data = self::findLabelWithTimestampInBlock($block_lines, 0);

			if ($label_data && !isset($label_timestamps[$label_data['label']])) {
				$label_timestamps[$label_data['label']] = $label_data['timestamp'];
				$labels[] = array_merge($label_data, ['search_type' => $search_type]);
			}
		}

		return $labels;
	}

	/**
	 * Найти уникальные метки по ID сделки с временем первого появления
	 * @param int $deal_id
	 * @return array [['label' => string, 'timestamp' => string], ...]
	 * @deprecated Используйте findLabelsByOrderAndDeal для поиска по обоим типам
	 */
	public static function findLabelsByDeal($deal_id) {
		return self::findLabelsByOrderAndDeal(null, $deal_id);
	}

	/**
	 * Найти метку с временем в блоке логов
	 * @param array $lines Массив строк блока
	 * @param int $start_index Начальный индекс для поиска (0 для поиска во всем блоке)
	 * @return array|null ['label' => string, 'timestamp' => string]
	 */
	private static function findLabelWithTimestampInBlock($lines, $start_index = 0) {
		// Ищем разделитель "---" и следующую за ним строку с меткой
		// Проходим блок с конца (метка обычно в конце блока перед данными)
		for ($i = count($lines) - 1; $i >= $start_index; $i--) {
			if (trim($lines[$i]) === '---') {
				// Следующая строка должна содержать метку и время
				if (isset($lines[$i + 1])) {
					$label_line = trim($lines[$i + 1]);
					// Формат строки: ДД.ММ.ГГГГ ЧЧ:ММ:СС label МЕТКА
					// Ищем метку и время в одной строке
					if (preg_match('/^(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}:\d{2})\s+label\s+([^\s]+)/', $label_line, $matches)) {
						return [
							'label' => $matches[2],
							'timestamp' => $matches[1]
						];
					}
					// Если формат не совпадает, но метка есть
					if (preg_match('/label\s+([^\s]+)/', $label_line, $matches)) {
						Loc::loadMessages(__FILE__);
						return [
							'label' => $matches[1],
							'timestamp' => Loc::getMessage('SP_CI_REMOTEDIAG_LOGS_NO_TIMESTAMP') ?: 'Время не указано'
						];
					}
				}
				// Если разделитель найден, но метки нет в следующей строке, продолжаем поиск
			}
		}
		
		// Если не нашли через разделитель, ищем метку напрямую в блоке
		for ($i = count($lines) - 1; $i >= $start_index; $i--) {
			$line = trim($lines[$i]);
			if (preg_match('/^(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}:\d{2})\s+label\s+([^\s]+)/', $line, $matches)) {
				return [
					'label' => $matches[2],
					'timestamp' => $matches[1]
				];
			}
			if (preg_match('/label\s+([^\s]+)/', $line, $matches)) {
				Loc::loadMessages(__FILE__);
				return [
					'label' => $matches[1],
					'timestamp' => Loc::getMessage('SP_CI_REMOTEDIAG_LOGS_NO_TIMESTAMP') ?: 'Время не указано'
				];
			}
		}
		
		return null;
	}

	/**
	 * Получить все строки логов с указанной меткой
	 * @param string $label
	 * @return array
	 */
	public static function getLogLinesByLabel($label) {
		$result = [];
		$previous_lines = [];
		$last_label_position = -1; // Позиция последней найденной метки (любой)

		if (!self::logFileExists()) {
			return $result;
		}

		$handle = fopen(self::getLogFilePath(), 'r');
		if (!$handle) {
			return $result;
		}

		while (($line = fgets($handle)) !== false) {
			$line = rtrim($line);

			// Если нашли строку с меткой (любой)
			if (preg_match('/^.*label\s+\w+.*$/', $line)) {
				// Если нашли метку нужного типа, добавляем строки от предыдущей метки до текущей
				if (preg_match('/label\s+' . preg_quote($label, '/') . '\b/', $line)) {
					if (!empty($previous_lines)) {
						$result = array_merge($result, $previous_lines);
					}
					// Добавляем саму строку с меткой
					$result[] = $line;
				}
				// Очищаем буфер после любой метки
				$previous_lines = [];
			} else {
				// Если это не метка, добавляем строку в буфер предыдущих строк
				$previous_lines[] = $line;
			}
		}

		fclose($handle);

		return $result;
	}

	/**
	 * Получить все строки логов с указанными метками
	 * @param array $labels Массив меток
	 * @return array
	 */
	public static function getLogLinesByLabels($labels) {
		if (!is_array($labels) || empty($labels)) {
			return [];
		}

		$result = [];
		$previous_lines = [];
		$found_labels = []; // Отслеживаем найденные метки для группировки

		if (!self::logFileExists()) {
			return $result;
		}

		$handle = fopen(self::getLogFilePath(), 'r');
		if (!$handle) {
			return $result;
		}

		while (($line = fgets($handle)) !== false) {
			$line = rtrim($line);

			// Если нашли строку с меткой (любой)
			if (preg_match('/^.*label\s+\w+.*$/', $line)) {
				// Проверяем, является ли эта метка одной из искомых
				$current_label = null;
				foreach ($labels as $label) {
					if (preg_match('/label\s+' . preg_quote($label, '/') . '\b/', $line)) {
						$current_label = $label;
						break;
					}
				}

				if ($current_label) {
					// Если нашли нужную метку, добавляем содержимое
					if (!empty($previous_lines)) {
						$result = array_merge($result, $previous_lines);
						$result[] = $line;
					}
				}
				// Очищаем буфер после любой метки
				$previous_lines = [];
			} else {
				// Если это не метка, добавляем строку в буфер предыдущих строк
				$previous_lines[] = $line;
			}
		}

		fclose($handle);

		return $result;
	}

}
