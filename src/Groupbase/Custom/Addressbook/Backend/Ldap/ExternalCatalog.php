<?php
class Custom_Addressbook_Backend_ldap_ExternalCatalog implements Addressbook_Backend_Ldap_Plugin_Interface
{
    /**
     * Allow to add external catalogs from .inc files
     *
     * (non-PHPdoc)
     * @see Addressbook_Backend_Ldap_Plugin_Interface::getOptions()
     */
    public function getOptions(array $ldapBackendOptions, array $tinebaseLdapOptions)
    {
        $options = array();
        $domain = Tinebase_Config::getDomain(TRUE);
        $file = 'serpro/Addressbook/Container/ContainerConf_' . $tinebaseLdapOptions['container'] . '.inc';
        if (!empty($domain)){
            $file = "serpro/Addressbook/Container/$domain/ContainerConf_" . $tinebaseLdapOptions['container'] . '.inc';
        }
        @include($file);
        if (is_array($ldapMap)){
            $options['attributes'] = $ldapMap;
        }
        return $options;
    }
}