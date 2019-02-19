<?php
namespace Fgsl\Groupware\Groupbase\Exception;

use Fgsl\Groupware\Groupbase\Translation;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * generic system exception which does not trigger an error reporting assistent
 * 
 * used to signal installation problems like:
 * - server connection problems
 * - missconfiguration
 * - ...
 * 
 * @package     Groupbase
 * @subpackage  Exception
 */
class SystemGeneric extends ProgramFlow
{
    /**
     * @var string _('Generic System Exception')
     */
    protected $_title = 'Generic System Exception';
    
    /**
     * @var string
     */
    protected $_appName = 'Groupbase';
    
    public function __construct($message, $code=600)
    {
        parent::__construct($message, $code);
    }
    
    /**
     * get the title
     * @return string
     */
    public function getTitle() {
        return $this->_title;
    }
    
    /**
     * set application name
     * used to get the translation object
     * 
     * @param string $appName
     */
    public function setAppName($appName)
    {
        $this->_appName = $appName;
    }
    
    /**
     * set custom title
     * 
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->_title = $title;
    }
    
    /**
     * returns existing nodes info as array
     *
     * @return array
     */
    public function toArray()
    {
        try {
            $translation = Translation::getTranslation($this->_appName);
            
            return array(
                'code'          => $this->getCode(),
                'message'       => $translation->_($this->getMessage()),
                'title'         => $translation->_($this->getTitle()),
            );
        } catch (\Exception $e) {
            return array(
                'code'          => $this->getCode(),
                'message'       => $this->getMessage(),
                'title'         => $this->getTitle(),
            );
        }
    }
    
}