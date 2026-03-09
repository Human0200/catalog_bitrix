<!doctype html>
<html lang="ru">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta content="Bitrix24 integration module" name="description" />
	<meta content="SProduction" name="author" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<!-- App css -->
	<link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i&display=swap&subset=cyrillic" rel="stylesheet">
	<link href="/bitrix/themes/.default/<?=$MODULE_ID?>/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
	<link href="/bitrix/themes/.default/<?=$MODULE_ID?>/css/bootstrap-vue.min.css" rel="stylesheet" type="text/css" />
	<link href="/bitrix/themes/.default/<?=$MODULE_ID?>/css/vueDatePick.css" rel="stylesheet" type="text/css" />
	<link href="/bitrix/themes/.default/<?=$MODULE_ID?>/css/vue2-datepicker.css" rel="stylesheet" type="text/css" />
	<link href="/bitrix/themes/.default/<?=$MODULE_ID?>/css/vue-select.css" rel="stylesheet" type="text/css" />
    <link href="/bitrix/themes/.default/<?=$MODULE_ID?>/css/icons.min.css" rel="stylesheet" type="text/css" />
	<link href="/bitrix/themes/.default/<?=$MODULE_ID?>/css/app.min.css" rel="stylesheet" type="text/css" />
	<link href="/bitrix/themes/.default/<?=$MODULE_ID?>/styles.css?<?=time();?>" rel="stylesheet" type="text/css" />
	<?foreach ($styles as $style_url):?>
    <link href="<?=$style_url;?>" rel="stylesheet" type="text/css" />
	<?endforeach;?>
    <script>
        messages = {
            ru: {
                main: {},
                page: {},
            },
        };
    </script>
</head>
<body class="<?= $BODY_CLASS ?? '' ?>">
