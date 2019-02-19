<?php
use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;
use Fgsl\Groupware\Groupbase\Model\Filter\Relation;

/**
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Relation Filter Class
 * 
 * @package     Groupbase
 * @subpackage  Relation
 * 
 */
class RelationFilter extends FilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Tinebase';
    
    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = Relation::class;
    
    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'id'                     => array('filter' => 'Tinebase_Model_Filter_Id'),
        'own_model'              => array('filter' => 'Tinebase_Model_Filter_Text'),
        'own_backend'            => array('filter' => 'Tinebase_Model_Filter_Text'),
        'own_id'                 => array('filter' => 'Tinebase_Model_Filter_Id'),
        'related_degree'         => array('filter' => 'Tinebase_Model_Filter_Text'),
        'related_model'          => array('filter' => 'Tinebase_Model_Filter_Text'),
        'related_backend'        => array('filter' => 'Tinebase_Model_Filter_Text'),
        'related_id'             => array('filter' => 'Tinebase_Model_Filter_Id'),
        'type'                   => array('filter' => 'Tinebase_Model_Filter_Text'),
        'remark'                 => array('filter' => 'Tinebase_Model_Filter_Text'),
        'created_by'             => array('filter' => 'Tinebase_Model_Filter_Text'),
        'creation_time'          => array('filter' => 'Tinebase_Model_Filter_DateTime'),
        'last_modified_by'       => array('filter' => 'Tinebase_Model_Filter_Text'),
        'last_modified_time'     => array('filter' => 'Tinebase_Model_Filter_DateTime'),
        'is_deleted'             => array('filter' => 'Tinebase_Model_Filter_Text'),
        'deleted_time'           => array('filter' => 'Tinebase_Model_Filter_DateTime'),
        'deleted_by'             => array('filter' => 'Tinebase_Model_Filter_Text'),
    );
}
