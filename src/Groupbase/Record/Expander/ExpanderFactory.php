<?php
namespace Fgsl\Groupware\Groupbase\Record\Expander;
use Fgsl\Groupware\Groupbase\Record\Expander\Expander;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Fgsl\Groupware\Groupbase\Record\Expander\AbstractExpander;
use Sabre\DAV\Exception\NotImplemented;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

class ExpanderFactory
{
    /**
     * @param string $_model
     * @param array $_definition
     * @param string $_property
     * @param Expander $_rootExpander
     * @return Property
     * @throws InvalidArgument
     * @throws NotImplemented
     */
    public static function create($_model, array $_definition, $_property, Expander $_rootExpander)
    {
        /** @var Tinebase_Record_Abstract $_model */
        if (null === ($mc = $_model::getConfiguration())) {
            throw new InvalidArgument($_model . ' doesn\'t have a modelconfig');
        }
        if (!$mc->hasField($_property)) {
            throw new InvalidArgument($_model . ' doesn\'t have property ' . $_property);
        }
        if (null === ($propModel = $mc->getFieldModel($_property)) ) {
            throw new NotImplemented($_model . '::' . $_property . ' has a unknown model');
        }
        $fieldDef = $mc->getFields()[$_property];
        if (!isset($fieldDef['type'])) {
            throw new InvalidArgument($_model . '::' . $_property . ' has not type');
        }
        $prio = null;
        switch ($fieldDef['type']) {
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'user':
                $prio = AbstractExpander::DATA_FETCH_PRIO_USER;
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'container':
                if (null === $prio) {
                    $prio = AbstractExpander::DATA_FETCH_PRIO_CONTAINER;
                }
            case 'record':
                return new RecordProperty($propModel, $_property, $_definition, $_rootExpander,
                     $prio ?: AbstractExpander::DATA_FETCH_PRIO_DEPENDENTRECORD);
            case 'records':
                return new RecordsProperty($propModel, $_property, $_definition,
                    $_rootExpander, $prio ?: AbstractExpander::DATA_FETCH_PRIO_DEPENDENTRECORD);
            case 'relation':
                return new Expander_Relations($_model, $propModel, $_property, $_definition,
                    $_rootExpander);
            case 'tag':
                return new Expander_Tags($propModel, $_property, $_definition, $_rootExpander);
            case 'note':
                return new Expander_Note($propModel, $_property, $_definition, $_rootExpander);
            case 'attachments':
                return new Expander_Attachments($propModel, $_property, $_definition, $_rootExpander);
        }

        throw new InvalidArgument($_model . '::' . $_property . ' of type ' . $fieldDef['type'] .
            ' is not supported');
    }

    /**
     * @param string $_model
     * @param array $_definition
     * @param string $_class
     * @param Expander $_rootExpander
     * @return Expander_Sub
     * @throws InvalidArgument
     */
    public static function createPropClass($_model, array $_definition, $_class,
            Expander $_rootExpander)
    {
        /** @var Tinebase_Record_Abstract $_model */
        if (null === ($mc = $_model::getConfiguration())) {
            throw new InvalidArgument($_model . ' doesn\'t have a modelconfig');
        }

        switch ($_class) {
            case AbstractExpander::PROPERTY_CLASS_USER:
                // we pass here exceptionally the model of the parent class, the constructor will resolve that
                // and pass along the model of the properties (tinebase_model_[full]user)
                return new Expander_PropertyClass_User($_model, $_definition, $_rootExpander);
                break;
        }

        throw new InvalidArgument($_class . ' is not supported');
    }
}