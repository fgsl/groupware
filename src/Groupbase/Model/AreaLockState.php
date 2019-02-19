<?php
namespace Fgsl\Groupware\Groupbase\Model;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\ModelConfiguration\ModelConfiguration;
use Zend\InputFilter\Input;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * AreaLockState Model
 *
 * @package     Groupbase
 * @subpackage  Model
 */

class AreaLockState extends AbstractRecord
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
        'recordName'        => 'Area Lock State',
        'recordsName'       => 'Area Lock States', // ngettext('Area Lock State', 'Area Lock States', n)
        'titleProperty'     => 'area',

        'appName'           => 'Tinebase',
        'modelName'         => 'AreaLockState',

        'fields'            => [
            'area' => [
                'type'          => 'string',
                'length'        => 255,
                'validators'    => [Input::ALLOW_EMPTY => false, 'presence' => 'required'],
                'label'         => 'Area', // _('Area')
                'queryFilter'   => true
            ],
            'expires' => [
                // 2150-01-01 -> never
                // 1970-01-01 -> already expired
                'type'          => 'datetime',
                'validators'    => [Input::ALLOW_EMPTY => true],
                'label'         => 'Expires', // _('Expires')
            ],
        ]
    ];
}
