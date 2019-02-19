<?php
namespace Fgsl\Groupware\Groupbase\Model;

use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * application filter class
 * 
 * @package     Groupbase
 * @subpackage  Filter 
 */
class ApplicationFilter extends FilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Groupbase';
    
    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = Application::class;
    
    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'id'    => array('filter' => 'Tinebase_Model_Filter_Id'),
        'query' => array('filter' => 'Tinebase_Model_Filter_Query', 'options' => array('fields' => array('name'))),
        'name'  => array('filter' => 'Tinebase_Model_Filter_Text')
    );
}
