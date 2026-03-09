
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/vue.min.js"></script>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/axios.min.js"></script>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/bootstrap-vue.min.js"></script>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/vue-i18n.js"></script>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/vue-ladda.js"></script>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/vuejs-datepicker.js"></script>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/vuejs-datepicker-ru.js"></script>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/vue2-datepicker.js"></script>
<?if(defined('BX_UTF') && BX_UTF === true):?>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/vue2-datepicker-ru.js"></script>
<?else:?>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/vue2-datepicker-ru_win.js"></script>
<?endif;?>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/vue-select.js"></script>
<!--<script src="https://unpkg.com/vee-validate"></script>-->
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/vendor.min.js"></script>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/jquery-3.4.1.min.js"></script>
<?if($_REQUEST['PLACEMENT']):?>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/placement_scripts.js?<?=time();?>"></script>
<?else:?>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/scripts.js?<?=time();?>"></script>
<?endif;?>
<script type="text/javascript" src="/bitrix/js/<?=$MODULE_ID;?>/components.js?<?=time();?>"></script>
<?foreach ($scripts as $script_url):?>
<script type="text/javascript" src="<?=$script_url;?>?<?=time();?>"></script>
<?endforeach;?>
</body>
</html>