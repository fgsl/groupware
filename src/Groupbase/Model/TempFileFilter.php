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
 * TempFile filter class
 * 
 * @package     Groupbase
 * @subpackage  Filter 
 */
class TempFileFilter extends FilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Tinebase';
    
    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = 'Tinebase_Model_TempFile';
    
    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'id'     => array('filter' => 'Tinebase_Model_Filter_Id'),
        'time'   => array('filter' => 'Tinebase_Model_Filter_DateTime'),
        'name'   => array('filter' => 'Tinebase_Model_Filter_Text')
    );
}
