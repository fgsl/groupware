<?php
/**
 * Syncroton
 *
 * @package     Custom
 * @subpackage  Syncroton
 * @license     http://www.tine20.org/licenses/lgpl.html LGPL Version 3
 * @copyright   Copyright (c) 2009-2012 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@serpro.gov.br>
 *
 */

/**
 * Customized class for Syncroton_Command_Sync
 *
 * @package     Custom
 * @subpackage  Syncroton
 */

class Custom_Syncroton_Command_Sync implements Syncroton_Command_Sync_Plugin_Interface
{
    /**
     *
     * @param Syncroton_Backend_IFolder $folderBackend
     * @param Syncroton_Model_SyncCollection    $collectionData
     */
    public function applyCustomsForCollectionData(Syncroton_Backend_IFolder $folderBackend, Syncroton_Model_SyncCollection $collectionData)
    {
        $mailApplication = Tinebase_EmailUser_Factory::getMailApplicationName();
        if ($mailApplication === 'Expressomail') {
            $collectionData->folder->imapstatus = '';
            $collectionData->folder->lastimapmodseq = -1;
            $folderBackend->update($collectionData->folder);
        }
        return $collectionData;
    }

    /**
     * @param ActiveSync_Frontend_Abstract    $dataController
     * @param Syncroton_Model_IFolder        $folder
    */
    public function applyCustomUpdateForImapStatus(Expressomail_Frontend_ActiveSync $dataController, Syncroton_Model_IFolder $folder)
    {
        $mailApplication = Tinebase_EmailUser_Factory::getMailApplicationName();
        if ($mailApplication === 'Expressomail') {
            return $dataController->setFolderImapStatus($folder);
        } else {
            return false;
        }
    }

    /**
     * @param unknown                         $dataController
     * @param Syncroton_Model_SyncCollection  $collectionData
     * @param array                           $allClientEntries
     * @param Syncroton_Backend_IFolder       $folderBackend
     * @param Datetime                        $syncTimeStamp
     */
    public function fetchEntriesChangedSinceLastSync($dataController, Syncroton_Model_SyncCollection $collectionData, $allClientEntries, Syncroton_Backend_IFolder $folderBackend, DateTime $syncTimeStamp)
    {
        $mailApplication = Tinebase_EmailUser_Factory::getMailApplicationName();
        // fetch entries changed since last sync
        if ($mailApplication === 'Expressomail') {
            $folderDecoded = Tinebase_EmailUser_Factory::callStatic('Backend_Folder','decodeFolderUid',array($collectionData->folder->bigfolderid));
            $allServerModifications = $dataController->getImapChangedEntries(
                    $folderDecoded['accountId'],
                    $folderDecoded['globalName'],
                    $collectionData->folder->lastimapmodseq
            );

            if ($collectionData->folder->lastimapmodseq !== -1) {
                foreach($allServerModifications['messages'] as $message)
                {
                    $bigContentId = Tinebase_EmailUser_Factory::callStatic('Backend_Message','createMessageId',array($folderDecoded['accountId'], $collectionData->folder->bigfolderid, $message['UID']));
                    $serverModificationsChanged[$bigContentId] = md5($bigContentId);
                }
                $serverModificationsChanged = array_intersect($serverModificationsChanged, $allClientEntries);
            } else {
                $serverModificationsChanged = array();
            }
            if ($allServerModifications['HIGHESTMODSEQ'] != FALSE)
            {
                $collectionData->folder->lastimapmodseq = $allServerModifications['HIGHESTMODSEQ'];
                $folderBackend->update($collectionData->folder);
            }
        } else {
            $serverModificationsChanged = $dataController->getChangedEntries(
                $collectionData->collectionId,
                $collectionData->syncState->lastsync,
                $syncTimeStamp,
                $collectionData->options['filterType']
            );
        }

       return $serverModificationsChanged;
    }

    /**
     * Get item from server module
     *
     * @param unknown                              $dataController
     * @param Syncroton_Model_SyncCollection       $collectionData
     * @param string                               $id
     * @param string                               $serverId
     */
    public function getEntry($dataController, $collectionData, $id, $serverId)
    {
        if ($dataController instanceof Expressomail_Frontend_ActiveSync){
            return $dataController->getEntry($collectionData, $id);
        } else { // default treatment
            return $dataController->getEntry($collectionData, $serverId);
        }

        $mailApplication = Tinebase_EmailUser_Factory::getMailApplicationName();

        if ($mailApplication === 'Expressomail') {
            return $dataController->getEntry($collectionData, $id);
        } else { // default treatment
            return $dataController->getEntry($collectionData, $serverId);
        }
    }

    /**
     * Get mail message id
     *
     * @param unknown                              $dataController
     * @param string                               $folderId
     * @param string                               $serverId
     * @return string                              $mailServerId
     */
    public function getMailServerMessageId($dataController, $folderId, $serverId)
    {
        if ($dataController instanceof Expressomail_Frontend_ActiveSync){
            return $dataController->getBigContentId($folderId, $serverId);
        } else { // default treatment
            return $serverId;
        }
    }
}