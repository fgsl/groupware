<?php
namespace  Fgsl\Groupware\Groupbase\Model;

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
 * AreaLockConfig Model
 * @see Config::AREA_LOCKS
 *
 * @package     Groupbase
 * @subpackage  Adapter
 *
 * @property provider
 * @property provider_config
 * @property area
 * @property validity
 */
class AreaLockConfig extends AbstractRecord
{
    /**
     * supported validity
     */
    const VALIDITY_ONCE = 'once';
    const VALIDITY_SESSION = 'session';
    const VALIDITY_LIFETIME = 'lifetime';
    const VALIDITY_PRESENCE = 'presence';
    const VALIDITY_DEFINEDBYPROVIDER = 'definedbyprovider';

    /**
     * some predefined areas
     */
    const AREA_LOGIN = 'Tinebase.login';
    const AREA_DATASAFE = 'Tinebase.datasafe';

    /**
     * supported providers
     */
    const PROVIDER_PIN = 'pin';
    const PROVIDER_USERPASSWORD = 'userpassword';
    const PROVIDER_TOKEN = 'token';

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
        'recordName'        => 'Area Lock Configuration',
        'recordsName'       => 'Area Lock Configurations', // ngettext('Area Lock Configuration', 'Area Lock Configurations', n)
        'titleProperty'     => 'area',

        'modlogActive'      => false, // @todo activate?

        'appName'           => 'Tinebase',
        'modelName'         => 'AreaLockConfig',

        'fields'            => [
            'area' => [
                'type'          => 'string',
                'length'        => 255,
                'validators'    => [Input::ALLOW_EMPTY => false, 'presence' => 'required'],
                'label'         => 'Area', // _('Area')
                'queryFilter'   => true
            ],
            'provider' => [
                'type'          => 'string',
                'length'        => 255,
                'validators'    => [
                    Input::ALLOW_EMPTY => false,
                    'presence' => 'required',
                    ['InArray', [
                        self::PROVIDER_PIN,
                        self::PROVIDER_USERPASSWORD,
                        self::PROVIDER_TOKEN,
                    ]],
                ],
                'label'         => 'Provider', // _('Provider')
            ],
             /** example config:
              *
              * array(
              *      // some auth adapter below Tinebase_Auth (i.e. Tinebase_Auth_PrivacyIdea)
              *      // NOTE: must implement Tinebase_Auth_Interface
              *      'adapter'               => 'PrivacyIdea',
              *      'url'                   => 'https://localhost/validate/check',
              *      'allow_self_signed'     => true,
              *      'ignorePeerName'        => true,
              * )
              *
              * NOTE: as this might contain confidential data it is removed (in toArray) before sent to any client via
              *       getRegistryData()
              */
            'provider_config' => [
                'type'          => 'array',
                'validators'    => [Input::ALLOW_EMPTY => true],
                'label'         => 'Provider Configuration', // _('Provider Configuration')
            ],
            'validity' => [
                'type'          => 'string',
                'length'        => 255,
                'validators'    => [
                    Input::ALLOW_EMPTY => false,
                    'presence' => 'required',
                    ['InArray', [
                        self::VALIDITY_ONCE, // default
                        self::VALIDITY_SESSION, // valid until session ends
                        self::VALIDITY_LIFETIME, // @see lifetime
                        self::VALIDITY_PRESENCE, // lifetime is relative to last presence recording (requires presence api)
                        self::VALIDITY_DEFINEDBYPROVIDER, // provider can define own rules (not implemented yet)
                    ]],
                ],
                'label'         => 'Validity', // _('Validity')
                'queryFilter'   => true,
                'default'       => self::VALIDITY_ONCE
            ],
            // absolute lifetime from unlock
            'lifetime' => [
                'type'          => 'integer',
                'validators'    => [Input::ALLOW_EMPTY => true],
                'label'         => 'Lifetime in Minutes', // _('Lifetime in Minutes')
            ],
            // @todo add more fields:
            // individual: true,      // each area must be unlocked individually (when applied hierarchically / with same provider) -> NOT YET
            // public_options: // provider specific _public_ options
            // allowEmpty?
        ]
    ];

    /**
     * returns array with record related properties
     *
     * @param boolean $_recursive
     * @return array
     */
    public function toArray($_recursive = TRUE)
    {
        $result = parent::toArray($_recursive);

        // unset provider_config as this is confidential
        unset($result['provider_config']);

        return $result;
    }
}
