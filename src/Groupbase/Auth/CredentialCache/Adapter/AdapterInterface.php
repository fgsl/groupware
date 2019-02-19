<?php
namespace Fgsl\Groupware\Groupbase\Auth\CredentialCache\Adapter;
use Fgsl\Groupware\Groupbase\Model\CredentialCache;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * credential cache adapter interface
 *  
 * @package     Groupbase
 * @subpackage  Auth
 */
interface AdapterInterface
{
    /**
     * setCache() - persists cache
     *
     * @param CredentialCache $_cache
     */
    public function setCache(CredentialCache $_cache);
    
    /**
     * getCache() - get the credential cache
     *
     * @return NULL|CredentialCache
     */
    public function getCache();

    /**
     * resetCache() - resets the cache
     */
    public function resetCache();

    /**
     * getDefaultKey() - get default cache key
     * 
     * @return string
     */
    public function getDefaultKey();
    
    /**
     * getDefaultId() - get default cache id
     * 
     * @return string
     */
    public function getDefaultId();
}
