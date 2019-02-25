<?php
namespace Fgsl\Groupware\Groupbase;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Groupware Twig class
 *
 * @package     Tinebase
 * @subpackage  Twig
 *
 */
class Twig
{
    const TWIG_AUTOESCAPE = 'autoEscape';
    const TWIG_LOADER = 'loader';
    const TWIG_CACHE = 'cache';

    /**
     * @var Twig_Environment
     */
    protected $_twigEnvironment = null;

    /**
     * translation object
     *
     * @var Zend_Translate
     */
    protected $_translate;

    /**
     * locale object
     *
     * @var Zend_Locale
     */
    protected $_locale;

    public function __construct(Zend_Locale $_locale, Zend_Translate $_translate, array $_options = [])
    {
        $this->_locale = $_locale;
        $this->_translate = $_translate;

        if (isset($_options[self::TWIG_LOADER])) {
            $twigLoader = $_options[self::TWIG_LOADER];
        } else {
            $twigLoader = new Twig_Loader_Filesystem(['./'], dirname(__DIR__));
        }

        if (TINE20_BUILDTYPE === 'DEVELOPMENT' || (isset($_options[self::TWIG_CACHE]) && !$_options[self::TWIG_CACHE])) {
            $cacheDir = false;
        } else {
            $cacheDir = rtrim(Core::getCacheDir(), '/') . '/tine20Twig';
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0777, true);
            }
        }

        $options = [
            'cache' => $cacheDir
        ];

        if (isset($_options[self::TWIG_AUTOESCAPE])) {
            $options['autoescape'] = $_options[self::TWIG_AUTOESCAPE];
        }
        $this->_twigEnvironment = new Twig_Environment($twigLoader, $options);
        
        /** @noinspection PhpUndefinedMethodInspection */
        /** @noinspection PhpUnusedParameterInspection */
        $this->_twigEnvironment->getExtension('core')->setEscaper('json', function($twigEnv, $string, $charset) {
            return json_encode($string);
        });

        $this->_addTwigFunctions();
    }

    /**
     * @param string $_filename
     * @return Twig_TemplateWrapper
     */
    public function load($_filename)
    {
        return $this->_twigEnvironment->load($_filename);
    }

    /**
     * @param Twig_LoaderInterface $loader
     */
    public function addLoader(Twig_LoaderInterface $loader)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $this->_twigEnvironment->getLoader()->addLoader($loader);
    }

    /**
     * @return Twig_Environment
     */
    public function getEnvironment()
    {
        return $this->_twigEnvironment;
    }

    /**
     * adds twig function to the twig environment to be used in the templates
     */
    protected function _addTwigFunctions()
    {
        $this->_twigEnvironment->addFunction(new Twig_SimpleFunction('config', function ($key, $app='') {
            $config = Config::getInstance();
            if ($app) {
                $config = $config->{$app};
            }
            return $config->{$key};
        }));

        $locale = $this->_locale;
        $translate = $this->_translate;
        $this->_twigEnvironment->addFunction(new Twig_SimpleFunction('translate',
            function ($str) use($locale, $translate) {
                $translatedStr = $translate->translate($str, $locale);
                if ($translatedStr == $str) {
                    $translatedStr = Translation::getTranslation('Tinebase', $locale)->translate($str, $locale);
                }

                return $translatedStr;
            }));
        $this->_twigEnvironment->addFunction(new Twig_SimpleFunction('_',
            function ($str) use($locale, $translate) {
                $translatedStr = $translate->translate($str, $locale);
                if ($translatedStr == $str) {
                    $translatedStr = Translation::getTranslation('Tinebase', $locale)->translate($str, $locale);
                }

                return $translatedStr;
            }));
        $this->_twigEnvironment->addFunction(new Twig_SimpleFunction('ngettext',
            function ($singular, $plural, $number) use($locale, $translate) {
                $translatedStr =  $translate->plural($singular, $plural, $number, $locale);
                if (in_array($translatedStr, [$singular, $plural])) {
                    $translatedStr = Translation::getTranslation('Tinebase', $locale)->plural($singular, $plural, $number, $locale);
                }

                return $translatedStr;
            }));
        $this->_twigEnvironment->addFunction(new Twig_SimpleFunction('addNewLine',
            function ($str) {
                return (is_scalar($str) && strlen($str) > 0) ? $str . "\n" : $str;
            }));
        $this->_twigEnvironment->addFunction(new Twig_SimpleFunction('dateFormat', function ($date, $format) {
            if (!($date instanceof DateTime)) {
                $date = new DateTime($date, Core::getUserTimezone());
            }
            
            return Translation::dateToStringInTzAndLocaleFormat($date, null, null, $format);
        }));
        $this->_twigEnvironment->addFunction(new Twig_SimpleFunction('relationTranslateModel', function ($model) {
            if (! class_exists($model)) return $model;
            return $model::getRecordName();
        }));
        $this->_twigEnvironment->addFunction(new Twig_SimpleFunction('keyField', function ($appName, $keyFieldName, $key, $locale = null) {
            $config = Config::getAppConfig($appName)->$keyFieldName;
            $keyFieldRecord = $config && $config->records instanceof Record_RecordSet ? $config->records->getById($key) : false;

            if ($locale !== null) {
                $locale = Translation::getLocale($locale);
            }
            
            $translation = Translation::getTranslation($appName, $locale);
            return $keyFieldRecord ? $translation->translate($keyFieldRecord->value) : $key;
        }));
        $this->_twigEnvironment->addFunction(new Twig_SimpleFunction('renderTags', function ($tags) {
            if (!($tags instanceof Record_RecordSet)) {
                return '';   
            }
            
            return implode(', ', $tags->getTitle());
        }));
    }

    public function addExtension(Twig_ExtensionInterface $extension)
    {
        $this->_twigEnvironment->addExtension($extension);
    }
}
