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
 *  notes filter class
 * 
 * @package     Groupbase
 * @subpackage  Notes 
 */
class NoteFilter extends FilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Groupbase';

    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = 'Fgsl\Groupware\Groupbase\Model\Note';
    
    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'creation_time'  => array('filter' => 'Tinebase_Model_Filter_Date'),
        'query'          => array('filter' => 'Tinebase_Model_Filter_Query', 'options' => array('fields' => array('note'))),
        'note'           => array('filter' => 'Tinebase_Model_Filter_Text'),
        'record_id'      => array('filter' => 'Tinebase_Model_Filter_Text'),
        'record_model'   => array('filter' => 'Tinebase_Model_Filter_Text'),
        'record_backend' => array('filter' => 'Tinebase_Model_Filter_Text'),
        'note_type_id'   => array('filter' => 'Tinebase_Model_Filter_Id'),
    );
}
