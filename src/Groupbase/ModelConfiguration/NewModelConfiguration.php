<?php
namespace Fgsl\Groupware\Groupbase\ModelConfiguration;

use Fgsl\Groupware\Groupbase\Exception\Record\DefinitionFailure;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Tinebase_NewModelConfiguration
 *
 * @package     Groupbasebase
 * @subpackage  Configuration
 *
 */

class NewModelConfiguration  extends ModelConfiguration
{
    /**
     * This maps field types to their default converter
     *
     * @var array
     */
    protected $_converterDefaultMapping = array(
        'json'      => [Tinebase_Model_Converter_Json::class],
        'date'      => [Tinebase_Model_Converter_Date::class],
        'datetime'  => [Tinebase_Model_Converter_DateTime::class],
    );

    /**
     * the constructor (must be called in a singleton per model fashion, each model maintains its own singleton)
     *
     * @var array $modelClassConfiguration
     * @throws DefinitionFailure
     */
    public function __construct($modelClassConfiguration)
    {
        try {
            parent::__construct($modelClassConfiguration);
        } catch (DefinitionFailure $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new DefinitionFailure('exception: ' . $e->getMessage(), $e->getCode(), $e);
        }


    }

    public function setValidators($_validators)
    {
        $this->_validators = $_validators;
        foreach ($this->_validators as $prop => $val) {
            if (!isset($this->_fields[$prop])) {
                $this->_fields[$prop] = [];
            }
        }
    }
}
