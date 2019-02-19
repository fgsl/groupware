<?php
namespace Fgsl\Groupware\Model;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\ModelConfiguration\ModelConfiguration;
use Zend\InputFilter\Input;
use Zend\Filter\StringTrim;
use Fgsl\Groupware\Groupbase\User\User;
use Fgsl\Groupware\Groupbase\Exception\NotFound;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * defines the datatype for one note
 * 
 * @package     Groupbase
 * @subpackage  Notes
 *
 * @property    string      $id
 * @property    string      $note_type_id
 * @property    string      $note
 * @property    string      $record_id
 * @property    string      $record_model
 * @property    string      $record_backend
 */
class Note extends AbstractRecord
{
    /**
     * system note type: changed
     * 
     * @staticvar string
     */
    const SYSTEM_NOTE_NAME_CREATED = 'created';
    
    /**
     * system note type: changed
     * 
     * @staticvar string
     */
    const SYSTEM_NOTE_NAME_CHANGED = 'changed';
    
    /**
     * key in $_validators/$_properties array for the filed which 
     * represents the identifier
     * 
     * @var string
     */    
    protected $_identifier = 'id';
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Tinebase';

    /**
     * holds the configuration object (must be declared in the concrete class)
     *
     * @var ModelConfiguration
     */
    protected static $_configurationObject = NULL;

    /**
     * Holds the model configuration (must be assigned in the concrete class)
     *
     * @var array
     */
    protected static $_modelConfiguration = [
        'recordName'        => 'Note',
        'recordsName'       => 'Notes', // ngettext('Note', 'Notes', n)
        'hasRelations'      => false,
        'hasCustomFields'   => false,
        'hasNotes'          => false,
        'hasTags'           => false,
        'hasXProps'         => false,
        // this will add a notes property which we shouldn't have...
        'modlogActive'      => true,
        'hasAttachments'    => false,
        'createModule'      => false,
        'exposeHttpApi'     => false,
        'exposeJsonApi'     => false,

        'appName'           => 'Tinebase',
        'modelName'         => 'Note',
        'idProperty'        => 'id',

        'filterModel'       => [],

        'fields'            => [
            'note_type_id'                  => [
                //'type'                          => 'record',
                'validators'                    => [
                    'presence' => 'required',
                    Input::ALLOW_EMPTY => false
                ],
            ],
            'note'                          => [
                'type'                          => 'string',
                'validators'                    => [
                    'presence' => 'required',
                    Input::ALLOW_EMPTY => false
                ],
                'inputFilters'                  => [StringTrim::class => null],
            ],
            'record_id'                     => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'record_model'                  => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'record_backend'                => [
                'type'                          => 'string',
                'validators'                    => [
                    Input::ALLOW_EMPTY => true,
                    Input::DEFAULT_VALUE => 'Sql'
                ],
            ],
        ],
    ];
    
    /**
     * returns array with record related properties
     * resolves the creator display name and calls Tinebase_Record_Abstract::toArray() 
     *
     * @param boolean $_recursive
     * @param boolean $_resolveCreator
     * @return array
     */    
    public function toArray($_recursive = TRUE, $_resolveCreator = TRUE)
    {
        $result = parent::toArray($_recursive);
        
        // get creator
        if ($this->created_by && $_resolveCreator) {
            //resolve creator; return default NonExistentUser-Object if creator cannot be resolved =>
            //@todo perhaps we should add a "getNonExistentUserIfNotExists" parameter to User::getUserById 
            try {
                $creator = User::getInstance()->getUserById($this->created_by);
            }
            catch (NotFound $e) {
                $creator = User::getInstance()->getNonExistentUser();
            }
             
            $result['created_by'] = $creator->accountDisplayName;
        }
        
        return $result;
    }
}
