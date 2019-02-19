<?php
/**
 * Tine 2.0
 *
 * @package     Custom
 * @subpackage  Plugins
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2014 Metaways Infosystems GmbH (http://www.metaways.de)
 * @copyright   Copyright (c) 2014 Serpro (http://www.serpro.gov.br)
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@serpro.gov.br>
 * @author      Mário César Kolling <mario.kolling@serpro.gov.br>
 *
 */

/**
 * This class is a plugin for frontend
 *
 * @package    Custom
 * @subpackage Plugins
 */

class Custom_Plugins_DigitalCertificate
{
    /*********************** Digital Certification Services ***************************/

    /**
     * Anonymous function
     * Returns multiple records the enter param is an array with the certificates to validate
     *
     * @param array $_records
     * @return array data
     *
     * @author Mário César Kolling <mario.kolling@serpro.gov.br>
     */
    public function verifyCertificate($_data)
    {
        Tinebase_Session::getSessionNamespace()->lock();
        $results = array();
        foreach ($_data as $data){
            $results[] = Custom_Model_DigitalCertificateValidation::createFromCertificate($data, true)->toArray();
        }
        return array(
            'results'       => $results,
            'totalCount'    => count($results),
        );
    }

    /**
     * Get Key Escrow certificates for message encryption
     *
     * @return array data
     * @author Mário César Kolling <mario.kolling@serpro.gov.br>
     * @todo Add auditing code when implementation becomes available
     */
    public function getKeyEscrowCertificates() {
        Tinebase_Session::getSessionNamespace()->lock();
        $config = Tinebase_Core::getConfig();
        $results = array();
        if ($config->certificate->active && $config->certificate->useKeyEscrow) {
            foreach (Custom_Auth_ModSsl_Certificate_X509::readCertificatesFile($config->certificate->masterCertificate) as $cert)
            {
                if ($cert['success']) {
                    $results[] = $cert;
                } else {
                    unset($cert['pemCertificateData']);
                    Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                        . "Master Certificate not Valid:\n"
                        . "Certificate Details:\n" . print_r($cert, TRUE));
                }
            }
            if (empty($results)) {
                Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                        . 'UseKeyEscrow Policy is active but no valid Master Certificate was found');
            }
        }
        return array(
            'results' => $results,
            'totalCount' => count($results),
        );
    }
}