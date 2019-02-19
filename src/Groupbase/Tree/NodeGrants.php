<?php
namespace Groupware\Groupbase\Tree;
use Fgsl\Groupware\Groupbase\Controller\Record\Grants;
use Fgsl\Groupware\Groupbase\Backend\Sql\Grants as SqlGrants;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * node grants controller
 * 
 * @package     Groupbase
 * @subpackage  FileSystem
 * 
 */
class NodeGrants extends Grants
{
    /**
     * application name
     *
     * @var string
     */
    protected $_applicationName = 'Groupbase';
    
    /**
     * check for container ACLs?
     *
     * @var boolean
     */
    protected $_doContainerACLChecks = FALSE;

    /**
     * do right checks - can be enabled/disabled by doRightChecks
     * 
     * @var boolean
     */
    protected $_doRightChecks = FALSE;
    
    /**
     * delete or just set is_delete=1 if record is going to be deleted
     *
     * @var boolean
     */
    protected $_purgeRecords = FALSE;
    
    /**
     * omit mod log for this records
     * 
     * @var boolean
     */
    protected $_omitModLog = TRUE;
    
    /**
     * Model name
     *
     * @var string
     */
    protected $_modelName = 'Tinebase_Model_Tree_Node';
    
    /**
     * Model name
     *
     * @var string
     */
    protected $_grantsModel = 'Tinebase_Model_Grants';

    /**
     * @var string acl record property for join with acl table
     */
    protected $_aclIdProperty = 'acl_node';

    /**
     * @var NodeGrants
     */
    private static $_instance = NULL;
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */
    private function __construct()
    {
        $this->_backend = new Node();
        $this->_grantsBackend = new SqlGrants(array(
            'modelName' => $this->_grantsModel,
            'tableName' => 'tree_node_acl',
            'recordTable' => 'tree_node'
        ));
    }

    /**
     * don't clone. Use the singleton.
     */
    private function __clone() 
    {
    }
    
    /**
     * singleton
     *
     * @return NodeGrants
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new NodeGrants();
        }
        
        return self::$_instance;
    }
}
