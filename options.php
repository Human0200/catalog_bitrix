<?php

namespace Rodzeta\Redirect;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use \Bitrix\Main\Application;
use \Bitrix\Main\Localization\Loc;

$app = Application::getInstance();
$context = $app->getContext();
$request = $context->getRequest();

Loc::loadMessages(__FILE__);

$siteId = $request->get('site_id');
if (!$siteId)
{
	$site = \CSite::GetList($by = 'sort', $order = 'asc', [
		'DEFAULT' => 'Y',
	])->Fetch();
	if (!$site)
	{
		$site = \CSite::GetList($by = 'sort', $order = 'asc', [
			'ACTIVE' => 'Y',
		])->Fetch();
	}
	if (!$site)
	{
		$site = \CSite::GetList($by = 'sort', $order = 'asc')->Fetch();
	}
}
else
{
	$site = \CSite::GetByID($siteId)->Fetch();
}
if (empty($site['LID']))
{
	\CAdminMessage::ShowMessage(
		Loc::getMessage('RODZETA_REDIRECT_SITE_NOT_DEFINED')
	);
	return;
}

if (check_bitrix_sessid() && $request->isPost())
{
	if ($request->getPost('save') != '')
	{
		$data = $request->getPostList();
		if ($request->get('rodzeta_action') == 'redirects')
		{
			Update($data, $site['LID']);
		}
		else
		{
			OptionsUpdate($data, $site['LID']);
		}
		\CAdminMessage::ShowNote(
			Loc::getMessage('RODZETA_REDIRECT_SETTINGS_SAVED')
		);
	}
}
$currentOptions = Options($site['LID']);

$title = '[' . $site['LID'] . '] ' .  $site['NAME'];
if ($request->get('rodzeta_action') == 'redirects')
{
	$description = Loc::getMessage('RODZETA_REDIRECT_TITLE_REDIRECTS');
}
else
{
	$description = Loc::getMessage('RODZETA_REDIRECT_OPTIONS_TITLE');
}
$tabControl = new \CAdminTabControl('tabControl', [[
	'DIV' => 'edit1',
	'TAB' => $title,
	'TITLE' => $description,
]]);

$tabControl->begin();

?>

<form method="post" action="">
	<?= bitrix_sessid_post() ?>

	<?php $tabControl->beginNextTab() ?>

	<?php if ($request->get('rodzeta_action') == 'redirects') { ?>

	<tr>
		<td colspan="2">

			<table width="100%" class="js-table-autoappendrows">
				<tbody>
					<?php
					$i = 0;
					foreach (AppendValues(Select(true, $site['LID']), 5, ['', '', '']) as $url) {
						$i++;
					?>
						<tr data-idx="<?= $i ?>">
							<td>
								<input type="text" placeholder="<?=
										Loc::getMessage("RODZETA_REDIRECT_URLS_FROM") ?>"
									name="redirect_urls[<?= $i ?>][0]"
									value="<?= htmlspecialcharsex($url[0]) ?>"
									style="width:96%;">
							</td>
							<td>
								<input type="text" placeholder="<?=
										Loc::getMessage("RODZETA_REDIRECT_URLS_TO") ?>"
									name="redirect_urls[<?= $i ?>][1]"
									value="<?= htmlspecialcharsex($url[1]) ?>"
									style="width:96%;">
							</td>
							<td>
								<select name="redirect_urls[<?= $i ?>][2]"
										title="<?= Loc::getMessage("RODZETA_REDIRECT_URLS_STATUS") ?>"
										style="width:96%;">
									<option value="301" <?= $url[2] == "301"? "selected" : "" ?>>301</option>
									<option value="302" <?= $url[2] == "302"? "selected" : "" ?>>302</option>
								</select>
							</td>
							<td>
								<input name="redirect_urls[<?= $i ?>][3]" value="Y" type="checkbox"
									title="<?= Loc::getMessage("RODZETA_REDIRECT_URLS_IS_PART_URL") ?>"
									<?= $url[3] == "Y"? "checked" : "" ?>>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>

		</td>
	</tr>

	<?php } else { ?>

	<tr>
		<td class="adm-detail-content-cell-l" width="50%">
			<label>
				<?= Loc::getMessage("RODZETA_REDIRECT_OPTIONS_WWW_TITLE") ?>
			</label>
		</td>
		<td class="adm-detail-content-cell-r" width="50%">
			<input name="redirect_www" value="Y" type="checkbox"
				<?= $currentOptions["redirect_www"] == "Y"? "checked" : "" ?>>
		</td>
	</tr>

	<tr>
		<td class="adm-detail-content-cell-l" width="50%">
			<label>
				<?= Loc::getMessage("RODZETA_REDIRECT_OPTIONS_HTTPS_TITLE") ?>
			</label>
		</td>
		<td class="adm-detail-content-cell-r" width="50%">
			<label>
				<input name="redirect_https" value="" type="radio"
					<?= $currentOptions["redirect_https"] == ""? "checked" : "" ?>>
				<?= Loc::getMessage("RODZETA_REDIRECT_OPTIONS_HTTPS_NO") ?>
			</label>
			<br>
			<label>
				<input name="redirect_https" value="to_https" type="radio"
					<?= $currentOptions["redirect_https"] == "to_https"? "checked" : "" ?>>
				<?= Loc::getMessage("RODZETA_REDIRECT_OPTIONS_HTTPS_HTTPS") ?>
			</label>
			<br>
			<label>
				<input name="redirect_https" value="to_http" type="radio"
					<?= $currentOptions["redirect_https"] == "to_http"? "checked" : "" ?>>
				<?= Loc::getMessage("RODZETA_REDIRECT_OPTIONS_HTTPS_HTTP") ?>
			</label>
		</td>
	</tr>

	<tr>
		<td class="adm-detail-content-cell-l" width="50%">
			<label>
				<?= Loc::getMessage("RODZETA_REDIRECT_OPTIONS_SLASH_TITLE") ?>
			</label>
		</td>
		<td class="adm-detail-content-cell-r" width="50%">
			<input name="redirect_slash" value="Y" type="checkbox"
				<?= $currentOptions["redirect_slash"] == "Y"? "checked" : "" ?>>
		</td>
	</tr>

	<tr>
		<td class="adm-detail-content-cell-l" width="50%">
			<label>
				<?= Loc::getMessage("RODZETA_REDIRECT_OPTIONS_INDEX_TITLE") ?>
			</label>
		</td>
		<td class="adm-detail-content-cell-r" width="50%">
			<input name="redirect_index" value="Y" type="checkbox"
				<?= $currentOptions["redirect_index"] == "Y"? "checked" : "" ?>>
		</td>
	</tr>

	<tr>
		<td class="adm-detail-content-cell-l" width="50%">
			<label>
				<?= Loc::getMessage("RODZETA_REDIRECT_OPTIONS_MULTISLASH_TITLE") ?>
			</label>
		</td>
		<td class="adm-detail-content-cell-r" width="50%">
			<input name="redirect_multislash" value="Y" type="checkbox"
				<?= $currentOptions["redirect_multislash"] == "Y"? "checked" : "" ?>>
		</td>
	</tr>

	<tr>
		<td class="adm-detail-content-cell-l" width="50%">
			<label>
				<?= Loc::getMessage("RODZETA_REDIRECT_IGNORE_QUERY") ?>
			</label>
		</td>
		<td class="adm-detail-content-cell-r" width="50%">
			<input name="ignore_query" value="Y" type="checkbox"
				<?= $currentOptions["ignore_query"] == "Y"? "checked" : "" ?>>
		</td>
	</tr>

	<tr>
		<td class="adm-detail-content-cell-l" width="50%">
			<label>
				<?= Loc::getMessage("RODZETA_REDIRECT_FROM_404") ?>
			</label>
		</td>
		<td class="adm-detail-content-cell-r" width="50%">
			<input name="redirect_from_404" value="Y" type="checkbox"
				<?= $currentOptions["redirect_from_404"] == "Y"? "checked" : "" ?>>
		</td>
	</tr>

	<tr>
		<td class="adm-detail-content-cell-l" width="50%">
			<label>
				<?= Loc::getMessage("RODZETA_REDIRECT_URLS_TITLE") ?>
			</label>
		</td>
		<td class="adm-detail-content-cell-r" width="50%">
			<input name="use_redirect_urls" value="Y" type="checkbox"
				<?= $currentOptions["use_redirect_urls"] == "Y"? "checked" : "" ?>>
			<?= str_replace($_SERVER['DOCUMENT_ROOT'], '', OptionsFilename('urls', $site['LID'], '.csv')) ?>
		</td>
	</tr>

	<?php } ?>

	<?php $tabControl->buttons(); ?>

	<input class="adm-btn-save" type="submit" name="save"
		value="<?= Loc::getMessage("RODZETA_REDIRECT_SAVE_SETTINGS") ?>">

</form>

<?php

$tabControl->end();

?>

<script>

BX.ready(function () {
	"use strict";
	// autoappend rows
	function makeAutoAppend($table) {
		function bindEvents($row) {
			for (let $input of $row.querySelectorAll('input[type="text"]')) {
				$input.addEventListener("change", function (event) {
					let $tr = event.target.closest("tr");
					let $trLast = $table.rows[$table.rows.length - 1];
					if ($tr != $trLast) {
						return;
					}
					$table.insertRow(-1);
					$trLast = $table.rows[$table.rows.length - 1];
					$trLast.innerHTML = $tr.innerHTML;
					let idx = parseInt($tr.getAttribute("data-idx")) + 1;
					$trLast.setAttribute("data-idx", idx);
					for (let $input of $trLast.querySelectorAll("input,select")) {
						let name = $input.getAttribute("name");
						if (name) {
							$input.setAttribute("name", name.replace(/([a-zA-Z0-9])\[\d+\]/, "$1[" + idx + "]"));
						}
					}
					bindEvents($trLast);
				});
			}
		}
		for (let $row of document.querySelectorAll(".js-table-autoappendrows tr")) {
			bindEvents($row);
		}
	}
	for (let $table of document.querySelectorAll(".js-table-autoappendrows")) {
		makeAutoAppend($table);
	}
});

</script>
