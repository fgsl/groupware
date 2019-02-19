<?php
namespace Fgsl\Groupware\Groupbase\Auth\ModSsl\UsernameCallback;
use Fgsl\Groupware\Groupbase\Auth\ModSsl\Certificate\X509;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/

abstract class AbstractUsernameCallback implements UsernameCallbackInterface
{
    /**
     * @var X509
     */
    protected $certificate;
            
    public function __construct(X509 $certificate)
    {
        $this->certificate = $certificate;
    }
    
    public function getUsername()
    {
        return $this->certificate->getEmail();
    }
}