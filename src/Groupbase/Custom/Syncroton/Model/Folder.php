<?php
/**
 * Syncroton
 *
 * @package     Custom
 * @subpackage  Syncroton
 * @license     http://www.tine20.org/licenses/lgpl.html LGPL Version 3
 * @copyright   Copyright (c) 2014 Serpro (http://www.serpro.gov.br)
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@serpro.gov.br>
 */
/**
 * plugin to add properties to Syncroton_Model_Folder
 *
 * @package     Custom
 * @subpackage  Syncroton
 */
class Custom_Syncroton_Model_Folder implements Syncroton_Model_Folder_Plugin_Interface
{
    private $_properties = array();

    /**
     * Add two custom properties
     * @param array $properties
     * @see Syncroton_Model_Folder_Plugin_Interface::__construct()
     */
    public function __construct(array $properties)
    {
        $properties['_properties']['Internal']['imapstatus'] = array('type' => 'string');
        $properties['_properties']['Internal']['lastimapmodseq'] = array('type' => 'number');
        $properties['_properties']['Internal']['bigfolderid'] = array('type' => 'string');

        $this->_properties['_properties'] = $properties['_properties'];
    }

    /**
     * Returns only attribute $_properties
     * @see Syncroton_Model_Folder_Plugin_Interface::getChangedProperties()
     */
    public function getChangedProperties()
    {
        return $this->_properties;
    }
}