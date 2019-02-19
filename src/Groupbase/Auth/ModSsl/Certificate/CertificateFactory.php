<?php
namespace Fgsl\Groupware\Groupbase\Auth\ModSsl\Certificate;
use Fgsl\Groupware\Groupbase\Auth\ModSsl\Exception\OpensslNotLoaded;
use Fgsl\Groupware\Groupbase\Auth\ModlSsl\Certificate\ICPBrasil;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/

class CertificateFactory
{
    
    /**
     *
     * @param string $certificate
     * @return ICPBrasil|X509
     * @throws OpensslNotLoaded 
     */
    public static function buildCertificate($certificate, $dontSkip = FALSE)
    {   
        if(!extension_loaded('openssl'))
        {
            // No suport to openssl.....
            throw new OpensslNotLoaded('Openssl not supported!');
        }
        
        if (!preg_match('/^-----BEGIN CERTIFICATE-----/', $certificate)){
            // TODO: convert to pem
        }
        
        // get Oids from ICPBRASIL
        $icpBrasilData = ICPBrasil::parseICPBrasilData($certificate);
        if ($icpBrasilData)
        {
            return new ICPBrasil($certificate, $icpBrasilData, $dontSkip);
        }
        return new X509($certificate, $dontSkip);
        
    }
    
}
