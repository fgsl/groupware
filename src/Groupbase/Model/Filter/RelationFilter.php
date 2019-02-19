<?php
namespace Fgsl\Groupware\Groupbase\Model\Filter;
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
 * @package     Tinebase
 * @subpackage  Relation
 * 
 */
class RelationFilter extends FilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Groupbase';
    
    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = Relation::class;
    
    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = array(
        'id'                     => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Id'),
        'own_model'              => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Text'),
        'own_backend'            => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Text'),
        'own_id'                 => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Id'),
        'related_degree'         => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Text'),
        'related_model'          => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Text'),
        'related_backend'        => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Text'),
        'related_id'             => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Id'),
        'type'                   => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Text'),
        'remark'                 => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Text'),
        'created_by'             => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Text'),
        'creation_time'          => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\DateTime'),
        'last_modified_by'       => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Text'),
        'last_modified_time'     => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\DateTime'),
        'is_deleted'             => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Text'),
        'deleted_time'           => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\DateTime'),
        'deleted_by'             => array('filter' => 'Fgsl\Groupware\Groupbase\Model\Filter\Text'),
    );
}
