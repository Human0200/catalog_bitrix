<?
/*
 * Diagnostics page
 */
$secure_code = htmlspecialchars($_REQUEST['sc']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="initial-scale=1.0, width=device-width">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title></title>
    <link rel="stylesheet" type="text/css" href="/bitrix/themes/.default/sproduction.integration/styles.css">
    <style>
        .sprod-integr-frame { height: 600px; }
    </style>
    <script src="https://code.jquery.com/jquery-1.12.4.min.js" integrity="sha256-ZosEbRLbNQzLpnKIkEdrPv7lOy9C27hHQ+Xp8a4MxAQ=" crossorigin="anonymous"></script>
    <script type="text/javascript" src="/bitrix/js/sproduction.integration/admin_scripts.js"></script>
    <script>
        function iframeResize() {
            var iFrameID = document.getElementById('sprod_integr_profile_edit_frame');
            if (iFrameID) {
                var cont_h = (iFrameID.contentWindow.document.body.scrollHeight + 30);
                $('#sprod_integr_profile_edit_frame').height(cont_h + 'px');
            }
        }
    </script>
</head>
<body>
	<iframe src="/bitrix/sprod_integr_diagnostics_page.php?sc=<?=$secure_code;?>" class="sprod-integr-frame" id="sprod_integr_profile_edit_frame"></iframe>
</body>
</html>