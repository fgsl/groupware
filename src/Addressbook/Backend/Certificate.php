<?php
namespace Fgsl\Groupware\Addressbook\Backend;
use Fgsl\Groupware\Groupbase\Backend\Sql\AbstractSql;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Auth\ModSsl\Certificate\CertificateFactory;
use Fgsl\Groupware\Groupbase\Auth\CredentialCache\Adapter\AdapterInterface;
use Fgsl\Groupware\Groupbase\Exception\Backend\Database;

/**
 *
 * @package     Addressbook
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * sql backend class for the addressbook certificate
 *
 * @package Addressbook
 */
class Certificate extends AbstractSql
{

    /**
     * Table name without prefix
     *
     * @var string
     */
    protected $_tableName = 'addressbook_certificates';

    /**
     * Model name
     *
     * @var string
     */
    protected $_modelName = 'Addressbook_Model_Certificate';

    /**
     * if modlog is active, we add 'is_deleted = 0' to select object in _getSelect()
     *
     * @var boolean
     */
    protected $_modlogActive = FALSE;

    /**
     * Identifier
     *
     * @var string
     */
    protected $_identifier = 'hash';

    /**
     * returns true if id is a hash value and false if integer
     *
     * @return boolean
     * @todo remove that when all tables use hash ids
     */
    protected function _hasHashId()
    {
        return true;
    }

    /**
     * converts raw data from adapter into a set of records
     *
     * @param array $_rawDatas
     *            of arrays
     * @return RecordSet
     */
    protected function _rawDataToRecordSet(array $_rawDatas)
    {
        $certs = array();

        foreach ($_rawDatas as $cert) {
            $objCertificate = CertificateFactory::buildCertificate($cert['certificate'], TRUE);
            if ($objCertificate && $objCertificate->isValid()) {
                $certs[] = $cert;
            }
        }
        ;
        return parent::_rawDataToRecordSet($certs);
    }

    /**
     * the constructor
     *
     * allowed options:
     * - modelName
     * - tableName
     * - tablePrefix
     * - modlogActive
     * - useSubselectForCount
     *
     * @param AdapterInterface $_db
     *            (optional)
     * @param array $_options
     *            (optional)
     * @throws Database
     */
    public function __construct($_dbAdapter = NULL, $_options = array())
    {
        parent::__construct($_dbAdapter, $_options);

        // $this->_additionalColumns['emails'] = new Zend_Db_Expr('(' .
        // $this->_db->select()
        // ->from($this->_tablePrefix . 'addressbook', array($this->_dbCommand->getAggregate('email')))
        // ->where($this->_db->quoteIdentifier('id') . ' IN ?', $this->_db->select()
        // ->from(array('addressbook_list_members' => $this->_tablePrefix . 'addressbook_list_members'), array('contact_id'))
        // ->where($this->_db->quoteIdentifier('addressbook_list_members.list_id') . ' = ' . $this->_db->quoteIdentifier('addressbook_lists.id'))
        // ) .
        // ')');
    }
}

?>
