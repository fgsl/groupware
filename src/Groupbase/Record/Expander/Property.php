<?php
namespace Fgsl\Groupware\Groupbase\Record\Expander;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
abstract class Property extends Sub
{
    protected $_prio;
    protected $_property;

    public function __construct($_model, $_property, $_expanderDefinition, $_rootExpander,
        $_prio = AbstractExpander::DATA_FETCH_PRIO_DEPENDENTRECORD)
    {
        $this->_prio = $_prio;
        $this->_property = $_property;

        parent::__construct($_model, $_expanderDefinition, $_rootExpander);
    }
}