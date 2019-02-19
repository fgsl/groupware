<?php
namespace Fgsl\Groupware\Groupbase\Model;

use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;
use Fgsl\Groupware\Groupbase\Model\Filter\AbstractFilter;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Core;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 *  persistent filter filter class
 * 
 * @package     Groupbase
 * @subpackage  Filter 
 */
class PathFilter extends FilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Tinebase';
    
    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = 'Tinebase_Model_Path';
    
    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = [
        'id'             => ['filter' => 'Tinebase_Model_Filter_Id'],
        //ATTENTION the query filter does its own split! we do not want that split, so we use query as alias on path!
        'query'          => ['filter' => 'Tinebase_Model_Filter_FullText', 'options' => ['field' =>'path']],
        'path'           => ['filter' => 'Tinebase_Model_Filter_FullText'],
        'shadow_path'    => ['filter' => 'Tinebase_Model_Filter_StrictFullText'],
    ];

    /**
     * creates a new filter based on the definition of this filtergroup
     *
     * @param  string|array $_fieldOrData
     * @param  string $_operator
     * @param  mixed  $_value
     * @return AbstractFilter|FilterGroup
     */
    public function createFilter($_fieldOrData, $_operator = NULL, $_value = NULL)
    {
        if (is_array($_fieldOrData)) {
            $data = $_fieldOrData;
        } else {
            if (Core::isLogLevel(LogLevel::TRACE)) {
                Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' '
                    . ' Using deprecated function syntax. Please pass all filter data in one array (field: ' . $_fieldOrData . ')');
            }

            $data = array(
                'field' => $_fieldOrData,
                'operator' => $_operator,
                'value' => $_value,
            );
        }

        if (isset($data['field']))

        return parent::createFilter($data);
    }
}
