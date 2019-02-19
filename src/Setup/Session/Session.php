<?php
namespace Fgsl\Groupware\Setup\Session;

use Fgsl\Groupware\Groupbase\Session\AbstractSession;

/**
 * @package     Setup
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * class for Session and Session Namespaces for Groupbase\User
 *
 * @package     Setup
 * @subpackage  Session
 */
class Session extends AbstractSession
{
    /**
     * Session namespace for Setup
     */
    const NAMESPACE_NAME = 'Setup_Session_Namespace';
}