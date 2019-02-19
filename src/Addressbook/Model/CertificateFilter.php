<?php
    /**
    * Tine 2.0
    * 
    * @package     Addressbook
    * @subpackage  Backend
    * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
    * @author      Mário César Kolling <mario.kolling@serpro.gov.br>
    * @copyright   Copyright (c) 2007-2012 Metaways Infosystems GmbH (http://www.metaways.de)
    * @copyright   Copyright (c) 2009-2014 Serpro (http://serpro.gov.br)
    */

    class Addressbook_Model_CertificateFilter extends Tinebase_Model_Filter_FilterGroup {
        /**
        * @var string class name of this filter group
        *      this is needed to overcome the static late binding
        *      limitation in php < 5.3
        */
        protected $_className = 'Addressbook_Model_CertificateFilter';

        /**
        * @var string application of this filter group
        */
        protected $_applicationName = 'Addressbook';

        /**
        * @var string name of model this filter group is designed for
        */
        protected $_modelName = 'Addressbook_Model_Certificate';
        
        /**
        * @var array filter model fieldName => definition
        */
        protected $_filterModel = array(
            'hash'                  => array('filter' => 'Tinebase_Model_Filter_Id'),
            'auth_key_identifier'   => array('filter' => 'Tinebase_Model_Filter_Id'),
            'email'                 => array('filter' => 'Tinebase_Model_Filter_Text'),
        );
    }
?>
