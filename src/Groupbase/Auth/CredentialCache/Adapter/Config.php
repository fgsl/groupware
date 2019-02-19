<?php
namespace Fgsl\Groupware\Groupbase\Auth\CredentialCache\Adapter;
use Fgsl\Groupware\Groupbase\Model\CredentialCache;
use Fgsl\Groupware\Groupbase\Core;
use Fgsl\Groupware\Groupbase\Exception\NotFound;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * credential cache adapter (config.inc.php)
 *  
 * @package     Groupbase
 * @subpackage  Auth
 */
class Config implements AdapterInterface
{
    /**
     * config key const
     * 
     */
    const CONFIG_KEY = 'usercredentialcache';
    
    /**
     * setCache() - persists cache
     *
     * @param  CredentialCache $_cache
     */
    public function setCache(CredentialCache $_cache)
    {
    }
    
    /**
     * getCache() - get the credential cache
     *
     * @return NULL|CredentialCache 
     */
    public function getCache()
    {
        $result = NULL;
        
        $config = Core::getConfig();
        if ($config->{self::CONFIG_KEY}) {
            $id = $this->getDefaultId();
            if ($id !== NULL) {
                $cacheId = array(
                    'key'   => $config->{self::CONFIG_KEY},
                    'id'    => $id,
                );
                $result = new CredentialCache($cacheId);
            }
        } else {
            Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' No credential cache key found in config.');
        }
        
        return $result;
    }

    /**
     * resetCache() - resets the cache
     */
    public function resetCache()
    {
    }

    /**
     * getDefaultKey() - get default cache key
     * @return string
     * @throws NotFound
     */
    public function getDefaultKey()
    {
        $result = NULL;
        
        $config = Core::getConfig();
        if ($config->{self::CONFIG_KEY}) {
            $result = $config->{self::CONFIG_KEY};
        } else {
            throw new NotFound('No credential cache key found in config!');
        }
        
        return $result;
    }
    
    /**
     * getDefaultId() - get default cache id
     * - use user id as default cache id
     * 
     * @return string
     */
    public function getDefaultId()
    {
        $result = NULL;
        
        if (Core::isRegistered(Core::USER)) {
            $result = Core::getUser()->getId();
        }
        
        return $result;
    }
}
