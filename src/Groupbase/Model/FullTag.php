<?php
namespace Fgsl\Groupware\Groupbase\Model;
use Zend\InputFilter\Input;
use Zend\Filter\StringTrim;
use Fgsl\Groupware\Groupbase\ModelConfiguration\ModelConfiguration;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * defines the datatype for one full (including all rights and contexts) tag
 * 
 * @package     Groupbase
 * @subpackage  Tags
 */
class FullTag extends Tag
{
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
            'contexts'                      => [
                //'type'                          => '!?',
                'validators'                    => [Input::ALLOW_EMPTY => true],
            ],
        ],
    ];
}
