<?
$MESS["SP_CI_FBASKET_SETTINGS_TITLE"] = "Настройки забытых корзин";
$MESS["SP_CI_FBASKET_SETTINGS_INFO"] = "Настройка параметров работы с забытыми корзинами пользователей";
$MESS["SP_CI_SETTINGS_INFO"] = "Информация";

// Messages from settings page for forgotten basket component
$MESS["SP_CI_SETTINGS_FORGOTTEN_BASKET_TITLE"] = "Параметры забытых корзин";
$MESS["SP_CI_SETTINGS_FORGOTTEN_BASKET_SUBTITLE"] = "Параметры обработки забытых корзин покупателей";
$MESS["SP_CI_SETTINGS_FORGOTTEN_BASKET_DAYS"] = "Через сколько часов считать корзину забытой";
$MESS["SP_CI_SETTINGS_FORGOTTEN_BASKET_DAYS_DEFAULT"] = "По умолчанию - 72 часа (3 дня)";
$MESS["SP_CI_SETTINGS_FORGOTTEN_BASKET_DEAL_FIELD"] = "Поле сделки для хранения связи с корзинами сайта";
$MESS["SP_CI_SETTINGS_FORGOTTEN_BASKET_DEAL_FIELD_DEFAULT"] = "Скрытое поле ORIGIN_ID";
$MESS["SP_CI_SETTINGS_FORGOTTEN_BASKET_SYNC_ACTIVE"] = "Синхронизация забытых корзин активна";
$MESS["SP_CI_SETTINGS_FORGOTTEN_BASKET_INFO_TITLE"] = "Синхронизация забытых корзин";
$MESS["SP_CI_SETTINGS_FORGOTTEN_BASKET_INFO_TEXT"] = "<p>Функция \"Забытые корзины\" позволяет выявлять и обрабатывать брошенные пользователями корзины покупок.</p>
<p>Модуль анализирует корзины покупателей и автоматически создает сделки в Bitrix24 при обнаружении забытых корзин.</p>
<p>Вы можете настроить время, через которое корзина считается забытой, и указать поле сделки для хранения связи с корзиной сайта.</p>";
$MESS["SP_CI_SETTINGS_FORGOTTEN_BASKET_PROFILES_LINK"] = "<a href=\"/bitrix/admin/sprod_integr_fbasket_profiles.php?lang=ru\" target=\"_parent\">Профили забытых корзин</a>";
$MESS["SP_CI_SETTINGS_SAVE"] = "Сохранить";

// Background sync settings
$MESS["SP_CI_SETTINGS_BACKGROUND_SYNC_TITLE"] = "Фоновая синхронизация данных";
$MESS["SP_CI_SETTINGS_BACKGROUND_SYNC_SUBTITLE"] = "Настройка автоматической синхронизации данных";
$MESS["SP_CI_SETTINGS_BACKGROUND_SYNC_INFO_TITLE"] = "Фоновая синхронизация";
$MESS["SP_CI_SETTINGS_BACKGROUND_SYNC_INFO_TEXT"] = "<p>Фоновая синхронизация позволяет автоматически синхронизировать данные между сайтом и Bitrix24.</p>
<p>Вы можете настроить расписание автоматического запуска синхронизации или отключить ее совсем.</p>
<p>Синхронизация работает в фоне и не влияет на производительность сайта.</p>";

// Sync schedule messages
$MESS["SP_CI_FBASKET_SYNC_SCHEDULE"] = "Расписание синхронизации";
$MESS["SP_CI_SETTINGS_FBASKET_SYNC_SCHEDULE_DISABLED"] = "Не запускать";
$MESS["SP_CI_SETTINGS_FBASKET_SYNC_SCHEDULE_1H"] = "Запускать каждый час";
$MESS["SP_CI_SETTINGS_FBASKET_SYNC_SCHEDULE_1D"] = "Запускать каждый день";

// Sync start date notification (forgotten baskets)
$MESS["SP_CI_FBASKET_SYNC_START_DATE_NOTICE"] = "Синхронизация учитывает дату начала синхронизации из раздела «Настройки подключения»:";

// Manual fbasket sync messages
$MESS["SP_CI_SETTINGS_MAN_FBASKET_SYNC_TITLE"] = "Ручная синхронизация";
$MESS["SP_CI_SETTINGS_MAN_FBASKET_SYNC_SUBTITLE"] = "Запуск полной выгрузки забытых корзин";
$MESS["SP_CI_SETTINGS_MAN_FBASKET_SYNC_RUN"] = "Запустить синхронизацию";
$MESS["SP_CI_SETTINGS_MAN_FBASKET_SYNC_INFO_TITLE"] = "Ручная синхронизация забытых корзин";
$MESS["SP_CI_SETTINGS_MAN_FBASKET_SYNC_INFO_TEXT"] = "<p>Ручная синхронизация позволяет запустить полную выгрузку забытых корзин в Bitrix24.</p>
<p>Процесс может занять продолжительное время в зависимости от количества данных.</p>";
?>