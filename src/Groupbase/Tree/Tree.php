<?php
namespace Fgsl\Groupware\Groupbase\Tree;
use Fgsl\Groupware\Groupbase\Controller\ControllerInterface;
use Fgsl\Groupware\Groupbase\FileSystem\FileSystem;
use Fgsl\Groupware\Groupbase\Timemachine\ModificationLog;
use Fgsl\Groupware\Groupbase\Record\Diff;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;
use Groupware\Groupbase\Tree\Node;
use Groupware\Groupbase\Tree\NodeGrants;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * tree fake controller, so that Tinebase_Core::getApplicationInstance('Tinebase_Model_Tree_Node') will return this
 * @see Tree_Node::getInstance()
 *
 * @package     Tinebase
 */
class Tree implements ControllerInterface
{
    /**
     * holds the _instance of the singleton
     *
     * @var Tree
     */
    private static $_instance = NULL;

    /**
     * the clone function
     *
     * disabled. use the singleton
     */
    private function __clone()
    {}

    /**
     * the constructor
     *
     * disabled. use the singleton
     */
    private function __construct()
    {}

    /**
     * the singleton pattern
     *
     * @return Tree
     */
    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Tree;
        }

        return self::$_instance;
    }

    public function get($_id)
    {
        return FileSystem::getInstance()->get($_id);
    }

    public function applyReplicationModificationLog(ModificationLog $_modification)
    {
        $treeBackend = FileSystem::getInstance()->_getTreeNodeBackend();
        switch($_modification->change_type) {
            case ModificationLog::CREATED:
                $diff = new Diff(json_decode($_modification->new_value, true));
                $node = new Node($diff->diff);
                $this->_prepareReplicationRecord($node);
                /**
                 * things that can go wrong:
                 * * name not unique...
                 * * parent_id was deleted
                 * * revisionProps, notificationProps, acl_node
                 */
                $treeBackend->create($node);
                break;

            case ModificationLog::UPDATED:
                $diff = new Diff(json_decode($_modification->new_value, true));
                /** @var Node $record */
                $record = $treeBackend->get($_modification->record_id, true);
                if (isset($diff->diff['grants']) && $record->acl_node === $record->getId()) {
                    NodeGrants::getInstance()->getGrantsForRecord($record);
                }
                $record->applyDiff($diff);
                $this->_prepareReplicationRecord($record);
                $treeBackend->update($record);
                if (isset($diff->diff['grants']) && $record->acl_node === $record->getId()) {
                    FileSystem::getInstance()->setGrantsForNode($record, $record->grants);
                    //Tree_NodeGrants::getInstance()->setGrants($record);
                }
                break;

            case ModificationLog::DELETED:
                $treeBackend->softDelete(array($_modification->record_id));
                break;

            default:
                throw new UnexpectedValue('change_type ' . $_modification->change_type . ' unknown');
        }
    }

    /**
     * @param Node $_record
     */
    protected function _prepareReplicationRecord(Node $_record)
    {
        // unset properties that are maintained only locally
        $_record->preview_count = null;
    }

    /**
     * @param ModificationLog $_modification
     * @param bool $_dryRun
     */
    public function undoReplicationModificationLog(ModificationLog $_modification, $_dryRun)
    {
        $treeBackend = FileSystem::getInstance()->_getTreeNodeBackend();
        switch($_modification->change_type) {
            case ModificationLog::CREATED:
                if (true === $_dryRun) {
                    return;
                }
                $treeBackend->softDelete($_modification->record_id);
                break;

            case ModificationLog::UPDATED:
                $node = $treeBackend->get($_modification->record_id);
                $diff = new Diff(json_decode($_modification->new_value, true));
                $node->undo($diff);

                if (true !== $_dryRun) {
                    $treeBackend->update($node);
                }
                break;

            case ModificationLog::DELETED:
                if (true === $_dryRun) {
                    return;
                }
                $node = $treeBackend->get($_modification->record_id, true);
                $node->is_deleted = false;
                $treeBackend->update($node);
                break;

            default:
                throw new UnexpectedValue('change_type ' . $_modification->change_type . ' unknown');
        }
    }
}