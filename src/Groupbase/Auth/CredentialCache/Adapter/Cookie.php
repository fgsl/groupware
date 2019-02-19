<?php
namespace Fgsl\Groupware\Groupbase\Auth\CredentialCache\Adapter;
use Fgsl\Groupware\Groupbase\Core;
use Psr\Log\LogLevel;
use Fgsl\Groupware\Groupbase\Model\CredentialCache;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * credential cache adapter (cookie)
 *  
 * @package     Groupbase
 * @subpackage  Auth
 */
class Cookie implements AdapterInterface
{
    /**
     * cookie key const
     * 
     * @var string
     */
    const COOKIE_KEY = 'usercredentialcache';
    
    /**
     * setCache() - persists cache
     *
     * @param  CredentialCache $_cache
     */
    public function setCache(CredentialCache $_cache)
    {
        $cacheId = $_cache->getCacheId();
        setcookie(self::COOKIE_KEY, base64_encode(json_encode($cacheId)));
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
            . ' Set credential cache cookie.');
    }
    
    /**
     * getCache() - get the credential cache
     *
     * @return NULL|CredentialCache 
     */
    public function getCache()
    {
        $result = NULL;
        if (isset($_COOKIE[self::COOKIE_KEY]) && ! empty($_COOKIE[self::COOKIE_KEY])) {
            $cacheId = json_decode(base64_decode($_COOKIE[self::COOKIE_KEY]));
            if (is_array($cacheId)) {
                $result = new CredentialCache($cacheId);
            } else {
                Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                    . ' Could not get CC from cookie (cache is not an array)');
                if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__
                    . ' cache: ' . print_r($cacheId, true));
            }
        } else {
            Core::getLogger()->warn(__METHOD__ . '::' . __LINE__
                . ' Could not get CC from cookie (could not find CC key in $_COOKIE)');
        }
        
        return $result;
    }

    /**
     * resetCache() - resets the cache
     */
    public function resetCache()
    {
        setcookie(self::COOKIE_KEY, '', time() - 3600);
        if (Core::isLogLevel(LogLevel::DEBUG)) Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' Reset credential cache cookie.');
    }
    
    /**
     * getDefaultKey() - get default cache key
     * 
     * @return string
     */
    public function getDefaultKey()
    {
        return AbstractRecord::generateUID();
    }

    /**
     * getDefaultId() - get default cache id
     * 
     * @return string
     */
    public function getDefaultId()
    {
        return AbstractRecord::generateUID();
    }
}
