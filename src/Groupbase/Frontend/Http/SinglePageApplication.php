<?php
namespace Fgsl\Groupware\Groupbase\Frontend\Http;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
class SinglePageApplication {

    /**
     * generates initial client html
     *
     * @param string|array  $entryPoint
     * @param string        $template
     * @return \Zend\Diactoros\Response
     */
    public static function getClientHTML($entryPoint, $template='Tinebase/views/singlePageApplication.html.twig', $context = []) {
        $entryPoints = is_array($entryPoint) ? $entryPoint : [$entryPoint];

        $twig = new Tinebase_Twig(Core::getLocale(), Translation::getTranslation('Tinebase'));
        $twig->getEnvironment()->addFunction(new Twig_SimpleFunction('jsInclude', function ($file) {
            $fileMap = self::getAssetsMap();
            if (isset($fileMap[$file]['js'])) {
                $file = $fileMap[$file]['js'];
            } else {
                $file .= (strpos($file, '?') ? '&' : '?') . 'version=' . Tinebase_Frontend_Http_SinglePageApplication::getAssetHash();
            }

            $baseUrl = Core::getUrl() ;

            if (GROUPWARE_BUILDTYPE == 'DEBUG') {
                $file = preg_replace('/\.js$/', '.debug.js', $file);
            }

            return '<script type="text/javascript" src="' . $baseUrl . '/' . $file .'"></script>';
        }, ['is_safe' => ['all']]));

        $textTemplate = $twig->load($template);

        $context += [
            'assetHash' => Tinebase_Frontend_Http_SinglePageApplication::getAssetHash(),
            'jsFiles' => $entryPoints,
        ];

        return new \Zend\Diactoros\Response\HtmlResponse($textTemplate->render($context), 200, self::getHeaders());
    }

    /**
     * gets headers for initial client html pages
     *
     * @return array
     */
    public static function getHeaders()
    {
        $header = [];

        $frameAncestors = implode(' ' ,array_merge(
            (array) Core::getConfig()->get(Tinebase_Config::ALLOWEDJSONORIGINS, array()),
            array("'self'")
        ));

        // set Content-Security-Policy header against clickjacking and XSS
        // @see https://developer.mozilla.org/en/Security/CSP/CSP_policy_directives
        $scriptSrcs = array("'self'", "'unsafe-eval'", 'https://versioncheck.tine20.net');
        if (TINE20_BUILDTYPE == 'DEVELOPMENT') {
            $scriptSrcs[] = Core::getUrl('protocol') . '://' . Core::getUrl('host') . ":10443";
        }
        $scriptSrc = implode(' ', $scriptSrcs);
        $header += [
            "Content-Security-Policy" => "default-src 'self'",
            "Content-Security-Policy" => "script-src $scriptSrc",
            "Content-Security-Policy" => "frame-ancestors $frameAncestors",

            // headers for IE 10+11
            "X-Content-Security-Policy" => "default-src 'self'",
            "X-Content-Security-Policy" => "script-src $scriptSrc",
            "X-Content-Security-Policy" => "frame-ancestors $frameAncestors",
        ];

        // set Strict-Transport-Security; used only when served over HTTPS
        $headers['Strict-Transport-Security'] = 'max-age=16070400';

        // cache mainscreen for one day in production
        $maxAge = ! defined('TINE20_BUILDTYPE') || TINE20_BUILDTYPE != 'DEVELOPMENT' ? 86400 : -10000;
        $header += [
            'Cache-Control' => 'private, max-age=' . $maxAge,
            'Expires' => gmdate('D, d M Y H:i:s', Tinebase_DateTime::now()->addSecond($maxAge)->getTimestamp()) . " GMT",
        ];

        return $header;
    }

    /**
     * get map of asset files
     *
     * @param boolean $asJson
     * @throws Exception
     * @return string|array
     */
    public static function getAssetsMap($asJson = false)
    {
        $json = file_get_contents(__DIR__ . '/../../../' . $jsonFile);

        return $asJson ? $json : json_decode($json, true);
    }

    /**
     *
     * @param  bool     $userEnabledOnly    this is needed when server concats js
     * @return string
     * @throws Exception
     * @throws InvalidArgument
     */
    public static function getAssetHash($userEnabledOnly = false)
    {
        $map = self::getAssetsMap();

        if ($userEnabledOnly) {
            $enabledApplications = Application::getInstance()->getApplicationsByState(Application::ENABLED);
            foreach($map as $asset => $ressources) {
                if (! $enabledApplications->filter('name', basename($asset))->count()) {
                    unset($map[$asset]);
                }
            }
        }

        return sha1(json_encode($map) . GROUPWARE_BUILDTYPE);
    }
}