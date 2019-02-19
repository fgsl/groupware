<?php
namespace Fgsl\Groupware\Groupbase\Auth\ModSsl\Model;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\Auth\ModSsl\Certificate\CertificateFactory;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/
/**
 * class Custom_Model_DigitalCertificateValidation
 * 
 * @package     Groupbase
 * @subpackage  Auth
 */
class DigitalCertificateValidation extends AbstractRecord
{
    /**
     * identifier
     * 
     * @var string
     */ 
    protected $_identifier = 'certificate';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Tinebase';
    
    /**
     * record validators
     *
     * @var array
     */
    protected $_validators = array(
        'certificate'       => array('allowEmpty' => false),
        'hash'              => array('allowEmpty' => true),
        'success'           => array('allowEmpty' => true),
        'messages'          => array('allowEmpty' => true),
    );

    /**
     *
     * @param String $_certificate
     * @param boolean $_dontSkip If true, don't skip validation
     * @param boolean $_addPem If true, adds certificate in PEM format to response
     * @return DigitalCertificateValidation
     * @todo Change success attribute to isValid
     */
    public static function createFromCertificate($_certificate, $_dontSkip = FALSE, $_addPem = FALSE)
    {
        $objCertificate = CertificateFactory::buildCertificate($_certificate, $_dontSkip);
        $objReturn = array(
            'certificate' => array(
                'serialNumber'  => $objCertificate->getSerialNumber(),
                'issuerCn'      => $objCertificate->getIssuerCn(),
                'cn'            => $objCertificate->getCn(),
                'email'         => $objCertificate->getEmail(),
                'validFrom'     => $objCertificate->getValidFrom(),
                'validTo'       => $objCertificate->getValidTo(),
            ),
            'hash'        => $objCertificate->getHash(),
            'success'     => $objCertificate->isValid(),
            'messages'    => $objCertificate->getStatusErrors(),
        );
        if ($_addPem === TRUE) {
            $objReturn['certificate']['pemCertificateData'] = $objCertificate->getPemCertificateData();
        }
        return new DigitalCertificateValidation($objReturn);
    }
}
