<?php
namespace Fgsl\Groupware\Groupbase\Controller\Record;
use Fgsl\Groupware\Groupbase\Backend\Sql\SqlInterface;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Exception\InvalidArgument;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Core;
use Sabre\DAV\Exception\NotFound;
use Fgsl\Groupware\Groupbase\Timemachine\ModificationLog;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * Trait to create modlogs
 *
 * @package     Tinebase
 * @subpackage  Controller
 */
trait ModlogTrait
{

    /**
     * application backend class
     *
     * @var SqlInterface
     */
    protected $_backend;


    /**
     * omit mod log for this records
     *
     * @var boolean
     */
    protected $_omitModLog = FALSE;

    /**
     * get backend type
     *
     * @return string
     */
    protected function _getBackendType()
    {
        $type = (method_exists( $this->_backend, 'getType')) ? $this->_backend->getType() : 'Sql';
        return $type;
    }

    /**
     * write modlog
     *
     * @param RecordInterface|null $_newRecord
     * @param RecordInterface|null $_oldRecord
     * @return NULL|RecordSet
     * @throws InvalidArgument
     */
    protected function _writeModLog($_newRecord, $_oldRecord)
    {
        if (null !== $_newRecord) {
            $notNullRecord = $_newRecord;
        } else {
            $notNullRecord = $_oldRecord;
        }
        if (! is_object($notNullRecord)) {
            throw new InvalidArgument('record object expected');
        }

        if (! $notNullRecord->has('created_by') || $this->_omitModLog === TRUE) {
            return NULL;
        }

        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Writing modlog for ' . get_class($notNullRecord));

        $currentMods = ModificationLog::getInstance()->writeModLog($_newRecord, $_oldRecord, $this->_modelName,
            $this->_getBackendType(), $notNullRecord->getId());

        return $currentMods;
    }

    /**
     * set/get modlog active
     *
     * @param  boolean $setTo
     * @return bool
     * @throws NotFound
     */
    public function modlogActive($setTo = NULL)
    {
        if (! $this->_backend) {
            $currValue = ! $this->_omitModLog;
        } else {
            $currValue = $this->_backend->getModlogActive();
        }

        if (NULL !== $setTo) {
            $setTo = (bool)$setTo;
            if (Core::isLogLevel(LogLevel::TRACE)) Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' Resetting modlog active to ' . (int) $setTo);
            if ($this->_backend) {
                $this->_backend->setModlogActive($setTo);
            }
            $this->_omitModLog = ! $setTo;
        }

        return $currValue;
    }
}