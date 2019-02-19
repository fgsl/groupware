<?php
namespace Fgsl\Groupware\Groupbase\Controller;

use Fgsl\Groupware\Groupbase\Event\EventInterface;
use Fgsl\Groupware\Groupbase\Model\User as ModelUser;
use Fgsl\Groupware\Groupbase\Event\AbstractEvent;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\User\User;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Translation;
use Fgsl\Groupware\Groupbase\FileSystem\FileSystem;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * controller abstract for applications with event handling
 *
 * @package     Groupbase
 * @subpackage  Controller
 */
abstract class Event extends AbstractController implements EventInterface
{
    /**
     * disable events on demand
     * 
     * @var mixed   false => no events filtered, true => all events filtered, array => disable only specific events
     */
    protected $_disabledEvents = false;
    
    /**
     * @see EventInterface::handleEvent()
     * @param AbstractEvent $_eventObject
     */
    public function handleEvent(AbstractEvent $_eventObject)
    {
        if ($this->_disabledEvents === true) {
            // nothing todo
            if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . 
                ' events are disabled. do nothing'
            );
            return;
        }
        
        $this->_handleEvent($_eventObject);
    }
    
    /**
     * implement logic for each controller in this function
     * 
     * @param AbstractEvent $_eventObject
     */
    protected function _handleEvent(AbstractEvent $_eventObject)
    {
        // do nothing
    }
    
    /**
     * (non-PHPdoc)
     * @see EventInterface::suspendEvents()
     */
    public function suspendEvents()
    {
        $this->_disabledEvents = true;
    }

    /**
     * (non-PHPdoc)
     * @see EventInterface::resumeEvents()
     */
    public function resumeEvents()
    {
        $this->_disabledEvents = false;
    }

    /**
     * creates the initial (file/tree node) folder for new accounts and returns it. skips creation if node already exists.
     *
     * @param mixed[int|ModelUser] $_account   the account object
     * @param string $applicationName
     * @return RecordSet of subtype Tinebase_Model_Tree_Node
     */
    public function createPersonalFileFolder($_account, $applicationName)
    {
        $account = (! $_account instanceof ModelUser)
            ? User::getInstance()->getUserByPropertyFromSqlBackend('accountId', $_account)
            : $_account;
        $translation = Translation::getTranslation('Tinebase');
        $nodeName = sprintf($translation->_("%s's personal files"), $account->accountFullName);
        $path = FileSystem::getInstance()->getApplicationBasePath(
                $applicationName,
                FileSystem::FOLDER_TYPE_PERSONAL
            ) . '/' . $account->getId() . '/' . $nodeName;

        if (true === FileSystem::getInstance()->fileExists($path)) {
            Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Found existing personal folder: "' .
                $path . '"');
            $personalNode = FileSystem::getInstance()->stat($path);
        } else {
            Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Creating new personal folder: "' .
                $path . '"');
            $personalNode = FileSystem::getInstance()->createAclNode($path);
        }

        $container = new RecordSet('Tinebase_Model_Tree_Node', array($personalNode));

        return $container;
    }
}
