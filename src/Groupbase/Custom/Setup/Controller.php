<?php
/**
 * Tine 2.0
 *
 * @package     Custom
 * @subpackage  Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @copyright   Copyright (c) 2015 Serpro (http://www.serpro.gov.br)
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@serpro.gov.br>
 */
class Custom_Setup_Controller implements Setup_Initialize_Custom_Interface
{
    /**
     * @see Setup_Initialize_Custom_Interface::applyCustomizations
     */
    public static function applyCustomizations()
    {
        $backend = Setup_Backend_Factory::factory();

        self::_update_6($backend);
        self::_update_7($backend);
        self::_update_8($backend);
        //TODO remove calls below when task #14299 is integrated
        self::_updateTemp($backend);
        $setup = new Tinebase_Setup_Update_Release7($backend);
        $setup->setApplicationVersion('Tinebase', '7.6');
    }
    /**
     * update to 5.11
     * - add ldapSettings (name, host, account, ...) for container
     * @param Setup_Backend_Interface $backend
     * @return void
     */
    protected static function _update_6(Setup_Backend_Interface $backend)
    {
        if (! $backend->columnExists('backend_options', 'container')){
            $declaration = new Setup_Backend_Schema_Field_Xml('
                <field>
                    <name>backend_options</name>
                    <type>text</type>
                    <default>NULL</default>
                </field>
            ');
            $backend->addCol('container', $declaration);
        }
    }

    /**
     * update indexes
     * add index by name and status for applicattions table
     * add index by created_by for container table
     * - added passwordhash to access_log
     * @param Setup_Backend_Interface $backend
     * @return void
     */
    protected static function _update_7(Setup_Backend_Interface $backend)
    {
        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
            <index>
                <name>name-status</name>
                <field>
                    <name>name</name>
                </field>
                <field>
                    <name>status</name>
                </field>
            </index>
            ');
            $backend->addIndex('applications', $declaration);

            $declaration = new Setup_Backend_Schema_Index_Xml('
            <index>
                <name>created_by</name>
                <field>
                    <name>created_by</name>
                </field>
            </index>
            ');
            $backend->addIndex('container', $declaration);

            $declaration = new Setup_Backend_Schema_Index_Xml('
            <index>
                <name>type-created_by-name</name>
                <field>
                    <name>type</name>
                </field>
                <field>
                    <name>created_by</name>
                </field>
                <field>
                    <name>name</name>
                </field>
            </index>
            ');
            $backend->addIndex('container', $declaration);

            $declaration = new Setup_Backend_Schema_Index_Xml('
            <index>
                <name>account_type</name>
                <field>
                    <name>account_type</name>
                </field>
            </index>
            ');
            $backend->addIndex('preferences', $declaration);
        } catch (Exception $e) { // If indexes already exist, there is no anything to do
        }

        if (! $backend->columnExists('passwordhash', 'access_log')){
            $declaration = new Setup_Backend_Schema_Field_Xml('
                 <field>
                    <name>passwordhash</name>
                    <type>text</type>
                    <length>255</length>
                    <notnull>false</notnull>
                 </field>
             ');
            $backend->addCol('access_log', $declaration);
        }
    }

    /**
     * update to 8.0
     * @param Setup_Backend_Interface $backend
     * @return void
     */
    protected static function _update_8(Setup_Backend_Interface $backend)
    {
       try {
           $declaration = new Setup_Backend_Schema_Index_Xml('
            <index>
                <name>email</name>
                <field>
                    <name>email</name>
                </field>
            </index>
            ');
           $backend->addIndex('accounts', $declaration);
       } catch (Exception $e) { // If index already exist, there is no anything to do
       }
    }

    /**
     * Keeps changes of Tinebase Release8 for Release7
     *
     * Aggregates update_6, update_7, update_8, update_9, update_10,
     * update_11 and update_12 from Expresso Tinebase Release7
     *
     * @param Setup_Backend_Interface $backend
     * TODO remove method when task #14299 is integrated
     */
    protected static function _updateTemp(Setup_Backend_Interface $backend)
    {
        self::_release7update6($backend);
        self::_release7update7($backend);
        self::_release7update8($backend);
        self::_release7update9($backend);
        self::_release7update10($backend);
        self::_release7update11($backend);
        self::_release7update12($backend);
    }

    /**
     * TODO remove method when task #14299 is integrated
     * @param Setup_Backend_Interface $backend
     */
    protected static function _release7update6(Setup_Backend_Interface $backend)
    {
        // Tine 2.0
        if ($backend->columnExists('data', 'state')){
            $declaration = new Setup_Backend_Schema_Field_Xml('
            <field>
                <name>data</name>
                <type>clob</type>
            </field>
            ');
            $backend->alterCol('state', $declaration);
        }
    }

    /**
     * TODO remove method when task #14299 is integrated
     * @param Setup_Backend_Interface $backend
     */
    protected static function _release7update7(Setup_Backend_Interface $backend)
    {
        // Tine 2.0
        if (! $backend->columnExists('uuid', 'container')){
            $declaration = new Setup_Backend_Schema_Field_Xml('
                <field>
                    <name>uuid</name>
                    <type>text</type>
                    <length>64</length>
                    <default>NULL</default>
                </field>
            ');

            $backend->addCol('container', $declaration);
        }

        // Expresso
        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
            <index>
                <name>name-status</name>
                <field>
                    <name>name</name>
                </field>
                <field>
                    <name>status</name>
                </field>
            </index>
            ');
            $backend->addIndex('applications', $declaration);

            $declaration = new Setup_Backend_Schema_Index_Xml('
            <index>
                <name>created_by</name>
                <field>
                    <name>created_by</name>
                </field>
            </index>
            ');
            $backend->addIndex('container', $declaration);

            $declaration = new Setup_Backend_Schema_Index_Xml('
            <index>
                <name>type-created_by-name</name>
                <field>
                    <name>type</name>
                </field>
                <field>
                    <name>created_by</name>
                </field>
                <field>
                    <name>name</name>
                </field>
            </index>
            ');
            $backend->addIndex('container', $declaration);

            $declaration = new Setup_Backend_Schema_Index_Xml('
            <index>
                <name>account_type</name>
                <field>
                    <name>account_type</name>
                </field>
            </index>
            ');
            $backend->addIndex('preferences', $declaration);
        } catch (Exception $e) { // If indexes already exist, there is no anything to do
        }
    }

    /**
     * TODO remove method when task #14299 is integrated
     * @param Setup_Backend_Interface $backend
     */
    protected static function _release7update8(Setup_Backend_Interface $backend)
    {
        if ($backend->tableExists('registrations')){
            $backend->dropTable('registrations');
        }
        if ($backend->tableExists('registration_invitation')){
            $backend->dropTable('registration_invitation');
        }
    }

    /**
     * TODO remove method when task #14299 is integrated
     * @param Setup_Backend_Interface $backend
     */
    protected static function _release7update9(Setup_Backend_Interface $backend)
    {
        if (! $backend->columnExists('passwordhash', 'access_log')) {
            $declaration = new Setup_Backend_Schema_Field_Xml('
                <field>
                    <name>passwordhash</name>
                    <type>text</type>
                    <length>255</length>
                    <notnull>false</notnull>
                </field>
            ');
            $backend->addCol('access_log', $declaration);
        }
    }

    /**
     * TODO remove method when task #14299 is integrated
     * @param Setup_Backend_Interface $backend
     */
    protected static function _release7update10(Setup_Backend_Interface $backend)
    {
        if (! $backend->columnExists('uuid', 'container')) {
            $declaration = new Setup_Backend_Schema_Field_Xml('
                <field>
                    <name>uuid</name>
                    <type>text</type>
                    <length>64</length>
                    <default>NULL</default>
                </field>
            ');

            $backend->addCol('container', $declaration);
        }
    }

    /**
     * TODO remove method when task #14299 is integrated
     * @param Setup_Backend_Interface $backend
     */
    protected static function _release7update11(Setup_Backend_Interface $backend)
    {
        try {
            $declaration = new Setup_Backend_Schema_Index_Xml('
            <index>
                <name>email</name>
                <field>
                    <name>email</name>
                </field>
            </index>
            ');
            $backend->addIndex('accounts', $declaration);
        } catch (Exception $e) {// If index already exists, there is no anything to do
        }
    }

    /**
     * TODO remove method when task #14299 is integrated
     * @param Setup_Backend_Interface $backend
     */
    protected static function _release7update12(Setup_Backend_Interface $backend)
    {
        self::_addFilterAclTable($backend);
        self::_addGrantsToExistingFilters($backend);
    }

    /**
     * add filter acl table
     * TODO remove method when task #14299 is integrated
     * @param Setup_Backend_Interface $backend
     */
    protected static function _addFilterAclTable(Setup_Backend_Interface $backend)
    {
        if ($backend->tableExists('filter_acl')){
            return;
        }
        $xml = $declaration = new Setup_Backend_Schema_Table_Xml('<table>
            <name>filter_acl</name>
            <version>1</version>
            <declaration>
                <field>
                    <name>id</name>
                    <type>text</type>
                    <length>40</length>
                    <notnull>true</notnull>
                </field>
                <field>
                    <name>record_id</name>
                    <type>text</type>
                    <length>40</length>
                    <notnull>true</notnull>
                </field>
                <field>
                    <name>account_type</name>
                    <type>text</type>
                    <length>32</length>
                    <default>user</default>
                    <notnull>true</notnull>
                </field>
                <field>
                    <name>account_id</name>
                    <type>text</type>
                    <length>40</length>
                    <notnull>true</notnull>
                </field>
                <field>
                    <name>account_grant</name>
                    <type>text</type>
                    <length>40</length>
                    <notnull>true</notnull>
                </field>
                <index>
                    <name>record_id-account-type-account_id-account_grant</name>
                    <primary>true</primary>
                    <field>
                        <name>id</name>
                    </field>
                    <field>
                        <name>record_id</name>
                    </field>
                    <field>
                        <name>account_type</name>
                    </field>
                    <field>
                        <name>account_id</name>
                    </field>
                    <field>
                        <name>account_grant</name>
                    </field>
                </index>
                <index>
                    <name>id-account_type-account_id</name>
                    <field>
                        <name>record_id</name>
                    </field>
                    <field>
                        <name>account_type</name>
                    </field>
                    <field>
                        <name>account_id</name>
                    </field>
                </index>
                <index>
                    <name>filter_acl::record_id--filter::id</name>
                    <field>
                        <name>record_id</name>
                    </field>
                    <foreign>true</foreign>
                    <reference>
                        <table>filter</table>
                        <field>id</field>
                        <ondelete>cascade</ondelete>
                    </reference>
                </index>
            </declaration>
        </table>');

        $backend->createTable('filter_acl', $declaration);
    }

    /**
     * add default grants to existing filters
     * TODO remove method when task #14299 is integrated
     * @param Setup_Backend_Interface $backend
     */
    protected static function _addGrantsToExistingFilters(Setup_Backend_Interface $backend)
    {
        $pfBackend = new Tinebase_PersistentFilter_Backend_Sql();
        $filters = $pfBackend->getAll();
        $pfGrantsBackend = new Tinebase_Backend_Sql_Grants(array(
            'modelName' => 'Tinebase_Model_PersistentFilterGrant',
            'tableName' => 'filter_acl'
        ));
        $pfGrantsBackend->getGrantsForRecords($filters);

        foreach ($filters as $filter) {
            if (count($filter->grants) > 0) {
                Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Filter ' . $filter->name . ' already has grants.');
                continue;
            }
            $grant = new Tinebase_Model_PersistentFilterGrant(array(
                'account_type' => $filter->isPersonal() ? Tinebase_Acl_Rights::ACCOUNT_TYPE_USER : Tinebase_Acl_Rights::ACCOUNT_TYPE_ANYONE,
                'account_id'   => $filter->account_id,
                'record_id'    => $filter->getId(),
            ));

            $grant->sanitizeAccountIdAndFillWithAllGrants();

            $filter->grants = new Tinebase_Record_RecordSet('Tinebase_Model_PersistentFilterGrant');
            $filter->grants->addRecord($grant);

            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' Updating filter "' . $filter->name . '" with grant: ' . print_r($grant->toArray(), true));

            Tinebase_PersistentFilter::getInstance()->setGrants($filter);
        }
    }
}