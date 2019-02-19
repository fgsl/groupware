<?php
namespace Fgsl\Groupware\Groupbase\Hash;
use Fgsl\Groupware\Groupbase\Hash\Password\Crypt;
use Fgsl\Groupware\Groupbase\Hash\Password\SHA;
use Fgsl\Groupware\Groupbase\Hash\Password\MD5;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

class Password
{
    /**
     * validate password hash
     * 
     * @param  string   $hash
     * @param  string   $password
     * @return boolean
     */
    public static function validate($hash, $password)
    {
        $matches = [];
        if (!preg_match('/\{(.*)\}.*/', $hash, $matches)) {
            throw new \Exception('unsupported hash: ' . $hash);
        }

        $algo = strtoupper($matches[1]);

        switch ($algo) {
            case (strpos($algo, 'CRYPT') !== false):
                $hashClass = new Crypt();
                break;
                
            case (strpos($algo, 'SHA') !== false):
                $hashClass = new SHA();
                break;
                
            case (strpos($algo, 'MD5') !== false):
                $hashClass = new MD5();
                break;
                
            default:
                throw new \InvalidArgumentException('Unsupported algo provided: ' . $algo);
                break;
        }
        
        return $hashClass->validate($hash, $password);
    }
    
    /**
     * generate password hash
     * 
     * @param  string   $hashType
     * @param  string   $password
     * @param  boolean  $addPrefix  add {HASHTYPE} prefix
     * @return string
     */
    public static function generate($hashType, $password, $addPrefix = true)
    {
        $hashType = strtoupper($hashType);

        switch ($hashType) {
            case (strpos($hashType, 'CRYPT') !== false):
                $hashClass = new Crypt();
                break;
                
            case (strpos($hashType, 'SHA') !== false):
                $hashClass = new SHA();
                break;
                
            case (strpos($hashType, 'MD5') !== false):
                $hashClass = new MD5();
                break;
                
            default:
                throw new \InvalidArgumentException('Unsupported algo provided: ' . $hashType);
                break;
        }
        
        return $hashClass->generate($hashType, $password, $addPrefix);
    }
}