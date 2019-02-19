<?php
namespace Fgsl\Groupware\Groupbase\Model;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\ModelConfiguration\ModelConfiguration;
use Zend\InputFilter\Input;
use Fgsl\Groupware\Groupbase\Record\RecordSet;
use Fgsl\Groupware\Groupbase\Tags;
use Zend\Filter\StringTrim;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * defines the datatype for one tag
 * 
 * @package     Groupbase
 * @subpackage  Tags
 *
 * @property string     $id
 * @property string     $name
 */
class Tag extends AbstractRecord
{
    /**
     * Type of a shared tag
     */
    const TYPE_SHARED = 'shared';
    
    /**
     * Type of a personal tag
     */
    const TYPE_PERSONAL = 'personal';
    
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
        'recordName'        => 'Tag',
        'recordsName'       => 'Tags', // ngettext('Tag', 'Tags', n)
        'hasRelations'      => false,
        'hasCustomFields'   => false,
        'hasNotes'          => false,
        'hasTags'           => false,
        'hasXProps'         => false,
        'modlogActive'      => true,
        'hasAttachments'    => false,
        'createModule'      => false,
        'exposeHttpApi'     => false,
        'exposeJsonApi'     => false,

        'appName'           => 'Tinebase',
        'modelName'         => 'Tag',

        'filterModel'       => [],

        'fields'            => [
            'type'                          => [
                'type'                          => 'string',
                'validators'                    => [
                    'inArray' => [
                        self::TYPE_PERSONAL,
                        self::TYPE_SHARED,
                    ]
                ],
            ],
            'owner'                         => [
                //'type'                          => 'record',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'name'                          => [
                'type'                          => 'string',
                'validators'                    => ['presence' => 'required'],
                'inputFilters'                  => [StringTrim::class => null],
            ],
            'description'                   => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'color'                         => [
                'type'                          => 'string',
                'validators'                    => [
                    Input::ALLOW_EMPTY => true,
                    ['regex', '/^#[0-9a-fA-F]{6}$/'],
                ],
            ],
            'occurrence'                    => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'selection_occurrence'          => [
                'type'                          => 'string',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'account_grants'                => [
                //'type'                          => '!?',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
            'rights'                        => [
                //'type'                          => '!?',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
        ],
    ];
    
    /**
     * returns containername
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
    
    /**
     * converts an array of tags names to a recordSet of Tag
     * 
     * @param  array    $tagNames
     * @param  string   $applicationName
     * @param  bool     $implicitAddMissingTags
     * @return RecordSet
     */
    public static function resolveTagNameToTag($tagNames, $applicationName, $implicitAddMissingTags = true)
    {
        if (empty($tagNames)) {
            return new RecordSet('Tag');
        }
        
        $resolvedTags = array();
        
        foreach ((array)$tagNames as $tagName) {
            if (Core::isLogLevel(LogLevel::DEBUG)) 
                Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Trying to allocate tag ' . $tagName);
            
            $tagName = trim($tagName);
            if (empty($tagName)) {
                continue;
            }
            
            $existingTags = Tags::getInstance()->searchTags(
                new TagFilter(array(
                    'name'        => $tagName,
                    'application' => $applicationName
                )), 
                new Pagination(array(
                    'sort'    => 'type', // prefer shared over personal
                    'dir'     => 'DESC',
                    'limit'   => 1
                ))
            );
            
            if (count($existingTags) === 1) {
                //var_dump($existingTags->toArray());
                $resolvedTags[] = $existingTags->getFirstRecord();
        
            } elseif ($implicitAddMissingTags === true) {
                // No tag found, lets create a personal tag
                $resolvedTag = Tags::GetInstance()->createTag(new Tag(array(
                    'type'        => Tag::TYPE_PERSONAL,
                    'name'        => $tagName
                )));
                
                $resolvedTags[] = $resolvedTag;
            }
        }
        
        return new RecordSet('Fgsl\Groupware\Groupbase\Model\Tag', $resolvedTags);
    }
}
