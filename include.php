<?php

namespace Rodzeta\Redirect;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\IO\Directory;

const ID = 'rodzeta.redirect';
const APP = __DIR__ . '/';
const LIB = APP . 'lib/';

define(
	__NAMESPACE__ . '\\CONFIG_DIR',
	$_SERVER['DOCUMENT_ROOT'] . '/local/config'
);
const CONFIG = CONFIG_DIR . '/.' . ID . '.';

const IGNORED_TEMPLATES = [
	'bitrix24' => 1,
	'desktop_app' => 1,
	'learning' => 1,
	'login' => 1,
	'mail_user' => 1,
	'mobile_app' => 1,
	'pub' => 1,
];

require LIB . 'encoding/include.php';

function AppendValues($data, $n, $v)
{
	yield from $data;
	for ($i = 0; $i < $n; ++$i)
	{
		yield  $v;
	}
}

function Options($siteId)
{
	$fname = OptionsFilename('options', $siteId);
	return is_readable($fname) ?
		include $fname : [
			'redirect_www' => 'Y',
			'redirect_https' => '',
			'redirect_slash' => 'Y',
			'redirect_index' => 'Y',
			'use_redirect_urls' => 'N',
			'ignore_query' => 'Y',
			'redirect_from_404' => 'N',
		];
}

function OptionsUpdate($data, $siteId)
{
	$fname = OptionsFilename('options', $siteId);
	if (!is_dir(CONFIG_DIR))
	{
		Directory::createDirectory(CONFIG_DIR);
	}
	\Encoding\PhpArray\Write($fname, [
		'redirect_www' => $data['redirect_www'],
		'redirect_https' => $data['redirect_https'],
		'redirect_slash' => $data['redirect_slash'],
		'redirect_index' => $data['redirect_index'],
		'redirect_multislash' => $data['redirect_multislash'],
		'use_redirect_urls' => $data['use_redirect_urls'],
		'ignore_query' => $data['ignore_query'],
		'redirect_from_404' => $data['redirect_from_404'],
	]);
}

function OptionsFilename($group, $siteId, $ext = '.php')
{
	return CONFIG . $group . '.' . $siteId . $ext;
}

function Select($fromCsv, $siteId)
{
	if ($fromCsv)
	{
		return \Encoding\Csv\Read(OptionsFilename('urls', $siteId, '.csv'));
	}
	$fname = OptionsFilename('urls', $siteId);
	return is_readable($fname) ? include $fname : [];
}

function Update($data, $siteId)
{
	$urls = [];
	$urlsMap = [];
	foreach ($data['redirect_urls'] as $url)
	{
		$from = trim($url[0]);
		$to = trim($url[1]);
		if ('' != $from && '' != $to)
		{
			$urls[] = $url;
			$urlsMap[$from] = [$to, trim($url[2]), trim($url[3])];
		}
	}
	if (!is_dir(CONFIG_DIR))
	{
		Directory::createDirectory(CONFIG_DIR);
	}
	\Encoding\Csv\Write(OptionsFilename('urls', $siteId, '.csv'), $urls);
	\Encoding\PhpArray\Write(OptionsFilename('urls', $siteId), $urlsMap);
}

//FIX for php 8.0
function parse_url($uri, $component = -1)
{
	// NOTE $uri without scheme and host
	$url = 'https://localhost' . $uri;

	$result = \parse_url($url, $component);

	return $result;
}

function HandlerRedirectUrl()
{
	if (('GET' != $_SERVER['REQUEST_METHOD']) && ('HEAD' != $_SERVER['REQUEST_METHOD']))
	{
		//NOTE redirect only for GET and HEAD
		return;
	}
	if (defined('SITE_TEMPLATE_ID') && isset(IGNORED_TEMPLATES[SITE_TEMPLATE_ID]))
	{
		//NOTE ignore some bitrix24 templates
		return;
	}
	if (('cli' == php_sapi_name())
			|| defined('BX_CRONTAB')
			|| \CSite::InDir('/bitrix/'))
	{
		//NOTE ignore redirect for scripts from /bitrix/, cli scripts and cron scripts
		return;
	}

	$currentOptions = Options(SITE_ID);
	$host = $_SERVER['SERVER_NAME'];
	$protocol = (!empty($_SERVER['HTTPS'])
		&& ('off' != $_SERVER['HTTPS'])) ? 'https' : 'http';
	$port = (!empty($_SERVER['SERVER_PORT'])
				&& ('80' != $_SERVER['SERVER_PORT'])
				&& ('443' != $_SERVER['SERVER_PORT'])) ?
			(':' . $_SERVER['SERVER_PORT']) : '';

	$currentUri = $_SERVER['REQUEST_URI'];
	$url = null;
	$isAbsoluteUrl = false;

	if (('Y' == $currentOptions['redirect_www'])
			&& ('www.' == substr($_SERVER['SERVER_NAME'], 0, 4)))
	{
		$host = substr($_SERVER['SERVER_NAME'], 4);
		$url = $currentUri;
	}

	$toProtocol = $currentOptions['redirect_https'];
	if (('to_https' == $toProtocol) && ('http' == $protocol))
	{
		$protocol = 'https';
		$url = $currentUri;
	}
	elseif (('to_http' == $toProtocol) && ('https' == $protocol))
	{
		$protocol = 'http';
		$url = $currentUri;
	}

	if (('Y' == $currentOptions['redirect_index'])
			|| ('Y' == $currentOptions['redirect_slash'])
			|| ('Y' == $currentOptions['redirect_multislash']))
	{
		$changed = false;
		$u = parse_url($currentUri);
		if ('Y' == $currentOptions['redirect_index'])
		{
			$tmp = rtrim($u['path'], '/');
			if ('index.php' == basename($tmp))
			{
				$dname = dirname($tmp);
				$u['path'] = (DIRECTORY_SEPARATOR != $dname ? $dname : '') . '/';
				$changed = true;
			}
		}
		if ('Y' == $currentOptions['redirect_slash'])
		{
			$tmp = basename(rtrim($u['path'], '/'));
			// add slash to url
			if (('/' != substr($u['path'], -1, 1))
					&& ('.php' != substr($tmp, -4))
					&& ('.htm' != substr($tmp, -4))
					&& ('.html' != substr($tmp, -5)))
			{
				$u['path'] .= '/';
				$changed = true;
			}
		}
		if ('Y' == $currentOptions['redirect_multislash'])
		{
			if (false !== strpos($u['path'], '//'))
			{
				$u['path'] = preg_replace('{/+}s', '/', $u['path']);
				$changed = true;
			}
		}
		if ($changed)
		{
			$url = $u['path'];
			if (!empty($u['query']))
			{
				$url .= '?' . $u['query'];
			}
		}
	}

	$status = '';
	if ('Y' == $currentOptions['use_redirect_urls'])
	{
		if ('Y' == $currentOptions['ignore_query'])
		{
			$currentUri = parse_url($currentUri, PHP_URL_PATH);
		}
		$redirects = Select(false, SITE_ID);
		if (isset($redirects[$currentUri]))
		{
			list($url, $status) = $redirects[$currentUri];
			if ('http' == substr($url, 0, 4))
			{
				$isAbsoluteUrl = true;
			}
		}
		else
		{
			// find part url
			foreach ($redirects as $fromUri => $v)
			{
				list($toUri, $status, $partUrl) = $v;
				if ('Y' != $partUrl)
				{
					continue;
				}
				$reFromUri = '{' . str_replace("\*\*\*", '(.+?)', preg_quote($fromUri)) . '}s';
				if (preg_match($reFromUri, $currentUri, $m))
				{
					$tmp = [];
					foreach ($m as $matchIdx => $matchValue)
					{
						if ($matchIdx > 0)
						{
							$tmp['{' . $matchIdx . '}'] = $matchValue;
						}
					}
					$url = str_replace(array_keys($tmp), array_values($tmp), $toUri);
					break;
				}
			}
		}
	}
	$status = '302' == $status ?
		'302 Found' : '301 Moved Permanently';

	if (!empty($url))
	{
		if ($isAbsoluteUrl)
		{
			LocalRedirect($url, true, $status);
		}
		else
		{
			LocalRedirect($protocol . '://' . $host . $port . $url, true, $status);
		}
		exit;
	}
}

function OnEpilog()
{
	if (!defined('ERROR_404') || ERROR_404 != 'Y')
	{
		return;
	}
	$options = Options(SITE_ID);
	if ('Y' != $options['redirect_from_404'])
	{
		return;
	}

	global $APPLICATION;
	// get parent level url
	$originalUri = $uri = parse_url($APPLICATION->GetCurPage(false), PHP_URL_PATH);
	$segments = explode('/', trim($uri, '/'));
	array_pop($segments);
	if (count($segments) > 0)
	{
		$uri = '/' . implode('/', $segments) . '/';
	}
	else
	{
		$uri = '/';
	}
	if ($originalUri != $uri)
	{
		// redirect
		LocalRedirect($uri, false, '301 Moved Permanently');
		exit;
	}
}

function init()
{
	Loc::loadMessages(__FILE__);

	AddEventHandler(
		'main',
		'OnBeforeProlog',
		__NAMESPACE__ . '\\HandlerRedirectUrl'
	);
	AddEventHandler(
		'main',
		'OnEpilog',
		__NAMESPACE__ . '\\OnEpilog'
	);

	AddEventHandler('main', 'OnAdminListDisplay', function (&$list) {
		if ($list->table_id != 'tbl_site')
		{
			return;
		}

		\CJSCore::init('sidepanel');
		$urlSettings = '/bitrix/admin/settings.php?lang=ru&mid='
			. ID . '&IFRAME=Y';
		foreach ($list->aRows as &$row)
		{
			$url = $urlSettings . '&rodzeta_action=settings&site_id='
				. $row->arRes['LID'];
			$row->aActions[] = [
				'TEXT' => Loc::getMessage('RODZETA_REDIRECT_MODULE_NAME')
					. Loc::getMessage('RODZETA_REDIRECT_MENU_ITEM_SETTINGS'),
				'ACTION' => 'BX.SidePanel? BX.SidePanel.Instance.open("' . $url . '") : (location.href="' . $url . '");',
			];
			$url = $urlSettings . '&rodzeta_action=redirects&site_id='
				. $row->arRes['LID'];
			$row->aActions[] = [
				'TEXT' => Loc::getMessage('RODZETA_REDIRECT_MODULE_NAME')
					. Loc::getMessage('RODZETA_REDIRECT_MENU_ITEM_REDIRECTS'),
				'ACTION' => 'BX.SidePanel? BX.SidePanel.Instance.open("' . $url . '") : (location.href="' . $url . '");',
			];
		}
	});
}

init();
