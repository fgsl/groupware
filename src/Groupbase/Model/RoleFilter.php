<?php
namespace Fgsl\Groupware\Groupbase\Model;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;

/**
 *
 * @package Groupbase
 * @license http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 */
/**
 * Role Filter Class
 * @package     Groupbase
 * @subpackage  Model
 * 
 */
class RoleFilter extends FilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Tinebase';

    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = 'Tinebase_Model_Role';

    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'id'                    => array('filter' => 'Tinebase_Model_Filter_Int'),
        'query'                 => array(
            'filter' => 'Tinebase_Model_Filter_Query',
            'options' => array('fields' => array('name', 'description'))
        ),
        'name'                  => array('filter' => 'Tinebase_Model_Filter_Text'),
        'description'           => array('filter' => 'Tinebase_Model_Filter_Text'),
    );
}
