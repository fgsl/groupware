<?php
namespace Fgsl\Groupware\Groupbase\Model;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 *  ModificationLog filter class
 * 
 * @package     Groupbase
 * @subpackage  Filter 
 */
class ModificationLogFilter extends FilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Groupbase';
    
    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = ModificationLog::class;
    
    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'id'                   => array('filter' => 'Tinebase_Model_Filter_Id'),
        'application_id'       => array('filter' => 'Tinebase_Model_Filter_Id'),
        'record_id'            => array('filter' => 'Tinebase_Model_Filter_Id'),
        'modification_account' => array('filter' => 'Tinebase_Model_Filter_Id'),
        'instance_id'          => array('filter' => 'Tinebase_Model_Filter_Id'),
        'modification_time'    => array('filter' => 'Tinebase_Model_Filter_DateTime'),
        'record_type'          => array('filter' => 'Tinebase_Model_Filter_Text'),
        'modified_attribute'   => array('filter' => 'Tinebase_Model_Filter_Text'),
        'old_value'            => array('filter' => 'Tinebase_Model_Filter_Text'),
        'change_type'          => array('filter' => 'Tinebase_Model_Filter_Text'),
        'seq'                  => array('filter' => 'Tinebase_Model_Filter_Int'),
        'instance_seq'         => array('filter' => 'Tinebase_Model_Filter_Int'),
    );
}
