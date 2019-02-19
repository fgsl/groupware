<?php
namespace Fgsl\Groupware\Groupbase\Exception;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * AreaLocked exception
 * 
 * @package     Tinebase
 * @subpackage  Exception
 */
class AreaLocked extends SystemGeneric
{
    /**
     * @var string _('Area is locked')
     */
    protected $_title = 'Area is locked';

    /**
     * the locked area
     *
     * @var string
     */
    protected $_area = null;

    /**
     * Tinebase_Exception_AreaLocked constructor.
     * @param null $_message
     * @param int $_code
     */
    public function __construct($message, $code = 630)
    {
        parent::__construct($message, $code);
    }

    /**
     * @param $area
     */
    public function setArea($area)
    {
        $this->_area = $area;
    }

    /**
     * @return string
     */
    public function getArea()
    {
        return $this->_area;
    }

    /**
     * returns existing nodes info as array
     *
     * @return array
     */
    public function toArray()
    {
        $result = parent::toArray();
        $result['area'] = $this->getArea();
        return $result;
    }
}
