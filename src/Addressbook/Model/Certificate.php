<?php
namespace Fgsl\Groupware\Addressbook\Model;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Zend\InputFilter\Input;
use Fgsl\Groupware\Groupbase\Auth\ModSsl\Certificate\X509;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * class to hold contact data
 * 
 * @package     Addressbook
 * @subpackage  Model
 * @property    email       e-mail value inside certificate
 * @property    subject     certificate owner subject
 * @property    issuer_cn   CN of certificate's Issuer
 * @property    hash        certificate's digital fingerprint
 * @property    certificate certificate's data in PEM format
 * @property    not_after   certificate's expiration date
 * @property    expired     true if certificate is expired
 * @property    revoked     true if certificate was revoked
 */
class Certificate extends AbstractRecord
{
    
    /**
     * Default certificate class type
     */
    const CERTIFICATE_CLASS = 'Custom_Auth_ModSsl_Certificate_X509';
    
    /**
     * key in $_validators/$_properties array for the filed which 
     * represents the identifier
     * 
     * @var string
     */
    protected $_identifier = 'hash';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Addressbook';
    
    /**
     * list of zend validator
     * this validators get used when validating user generated content with Zend_Input_Filter
     * @var array
     * @todo should we use validators? Data comes from valid digital certificates.
     */
    protected $_validators = array(
        'hash'                      => array(Input::PRESENCE_REQUIRED => true), // primary key
        'auth_key_identifier'       => array(Input::PRESENCE_REQUIRED => true), // primary key
        'email'                     => array(Input::PRESENCE_REQUIRED => true),
        'certificate'               => array(Input::PRESENCE_REQUIRED => true),
        'invalid'                   => array(Input::ALLOW_EMPTY => true, Input::DEFAULT_VALUE => 'false'),
    );
        
    
    /**
     *
     * @param mixed $_data array | Custom_Auth_ModSsl_Certificate_X509 $_certificate
     * @param boolean $_bypassFilters
     * @param boolean $_convertDates 
     */
    public function __construct($_data = NULL, $_bypassFilters = false, $_convertDates = true) {
        
        $_data = $_data instanceof X509 ? array(
            'hash'                  => $_data->getHash(),
            'auth_key_identifier'   => $_data->getAuthorityKeyIdentifier(),
            'email'                 => $_data->getEmail(),
            'certificate'           => $_data->getPemCertificateData(),
            'invalid'               => !$_data->isValid(),
        ) : $_data;
        
        parent::__construct($_data, $_bypassFilters, $_convertDates);
    }
    
    
}

?>
