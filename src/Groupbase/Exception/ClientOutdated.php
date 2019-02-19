<?php
namespace Fgsl\Groupware\Groupbase\Exception;

use Fgsl\Groupware\Groupbase\Frontend\Http\SinglePageApplication;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * outdated client
 *
 * @package     Groupbase
 * @subpackage  Exception
 */
class ClientOutdated extends ProgramFlow
{
    /**
     * @var string
     */
    protected $_appName = 'Tinebase';

    public function __construct($message = 'Client Outdated', $code = 426)
    {
        parent::__construct($message, $code);
    }

    /**
     * returns existing nodes info as array
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'code'      => $this->getCode(),
            'message'   => $this->getMessage(),
            'version'   => array(
                'buildType'     => GROUPWARE_BUILDTYPE,
                'codeName'      => GROUPWARE_CODENAME,
                'packageString' => GROUPWARE_PACKAGESTRING,
                'releaseTime'   => GROUPWARE_RELEASETIME,
                'assetHash'     => SinglePageApplication::getAssetHash(),
            ),
        );
    }
}
