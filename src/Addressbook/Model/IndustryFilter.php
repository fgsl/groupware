<?php
namespace Fgsl\Groupware\Addressbook\Model;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;

/**
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * IndustryFilter
 * 
 * @package     Addressbook
 * @subpackage  Filter
 */
class IndustryFilter extends FilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Addressbook';
    
    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = Industry::class;
    
    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'id'                    => array(
            'filter' => 'Tinebase_Model_Filter_Id'
        ),
        'query'                => array(
            'filter' => 'Tinebase_Model_Filter_Query', 
            'options' => array('fields' => array('name'))
        ),
        'name'                 => array('filter' => 'Tinebase_Model_Filter_Text'),
    );
}
