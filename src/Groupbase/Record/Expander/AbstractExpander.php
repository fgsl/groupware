<?php
namespace Fgsl\Groupware\Groupbase\Record\Expander;

use Fgsl\Groupware\Groupbase\Record\RecordSet;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

abstract class AbstractExpander
{
    const EXPANDER_PROPERTIES = 'properties';
    const EXPANDER_PROPERTY_CLASSES = 'propertyClasses';
    const EXPANDER_REPLACE_GET_TITLE = 'replaceGetTitle';

    const PROPERTY_CLASS_USER = 'user';

    const DATA_FETCH_PRIO_DEPENDENTRECORD = 100;
    const DATA_FETCH_PRIO_CONTAINER = 950;
    const DATA_FETCH_PRIO_USER = 1000;
    const DATA_FETCH_PRIO_RELATION = 800;
    const DATA_FETCH_PRIO_NOTES = 900;

    protected $_model;
    protected $_subExpanders = [];

    /**
     * @var AbstractExpander
     */
    protected $_rootExpander;

    public function __construct($_model, $_expanderDefinition, AbstractExpander $_rootExpander)
    {
        /** @var Tinebase_Record_Abstract $_model */
        $this->_model = $_model;
        $this->_rootExpander = $_rootExpander;
        if (isset($_expanderDefinition[self::EXPANDER_PROPERTIES])) {
            foreach ($_expanderDefinition[self::EXPANDER_PROPERTIES] as $prop => $definition) {
                $this->_subExpanders[] = ExpanderFactory::create($_model, $definition, $prop,
                    $this->_rootExpander);
            }
        }
        if (isset($_expanderDefinition[self::EXPANDER_PROPERTY_CLASSES])) {
            foreach ($_expanderDefinition[self::EXPANDER_PROPERTY_CLASSES] as $propClass => $definition) {
                $this->_subExpanders[] = ExpanderFactory::createPropClass($_model, $definition,
                    $propClass, $this->_rootExpander);
            }
        }
    }

    public function expand(RecordSet $_records)
    {
        /** @var Tinebase_Record_Expander_Sub $expander */
        foreach ($this->_subExpanders as $expander) {
            /** @noinspection Annotator */
            $expander->_lookForDataToFetch($_records);
        }
    }

    protected abstract function _lookForDataToFetch(RecordSet $_records);
    protected abstract function _setData(RecordSet $_data);
    protected abstract function _registerDataToFetch(DataRequest $_dataRequest);
}