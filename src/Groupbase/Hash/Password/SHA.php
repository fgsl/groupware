<?php
namespace Fgsl\Groupware\Groupbase\Hash\Password;
/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * SHA hash class
 * 
 * @package    Hash
 */
class SHA
{
    /**
     * generate SHA hash
     *
     * @param  string  $algo
     * @param  string  $password
     * @return string  the SHA hash
     */
    public function generate($hashType, $password, $addPrefix = true)
    {   
        $hashType = strtoupper($hashType);
        
        if (substr($hashType, 0, 3) !== 'SHA' && substr($hashType, 0, 4) !== 'SSHA') {
            throw new \InvalidArgumentException('unsupported hash type: ' . $hashType);
        }
        
        # sha1 ssha1 sha256 ssha256
        $algo = substr($hashType, 0, 4) === 'SSHA' ? strtolower(substr($hashType, 1)) : strtolower($hashType);
        $algo = ($algo == 'sha') ? 'sha1' : $algo;
        
        if (array_search($algo, hash_algos()) === false) {
            throw new \UnexpectedValueException('unsupported algo: ' . $algo);
        }

        if (substr($hashType, 0, 4) === 'SSHA') {
            // generate 4 byte salt
            $salt = chr(mt_rand(0,255)) . chr(mt_rand(0,255)) . chr(mt_rand(0,255)) . chr(mt_rand(0,255));
            
            // generate salted hash
            $hash = ($addPrefix === true ? '{' . $hashType . '}' : null) . base64_encode(pack("H*", hash($algo, $password . $salt)) . $salt);
        } else {
            // generate hash
            $hash = ($addPrefix === true ? '{' . $hashType . '}' : null) . base64_encode(pack("H*", hash($algo, $password)));
        }
        
        return $hash;
    }

    /**
     * validate SHA hash
     * 
     * @param  string  $hash
     * @param  string  $password
     * @return boolean
     */
    public function validate($hash, $password)
    {
        $hash = (($pos = strpos($hash, '}')) !== false) ? substr($hash, $pos + 1) : $hash;
        
        switch (strlen($hash)) {
            case 28: // SHA
            case 44: // SHA256
                $originalHash  = base64_decode($hash);
                
                $algo = $this->_getHashAlgoByLength(strlen($originalHash));
                
                // recalculate hash of provided cleartext password
                $validatedHash = pack("H*", hash($algo, $password));
                break;
                
            case 32: // SSHA
            case 48: // SSHA256
                // base64 decode hash
                $decodedHash = base64_decode($hash);
                
                // get salted hash of password
                $originalHash = substr($decodedHash, 0, -4);
                
                // get salt
                $salt = substr($decodedHash, -4);
                
                $algo = $this->_getHashAlgoByLength(strlen($originalHash));
                                
                // recalculate salted hash of provided cleartext password
                $validatedHash = pack("H*", hash($algo, $password . $salt));
                break;
                
            default:
                throw new \InvalidArgumentException('unsupported hash: ' . $hash);
                break;
        }
        
        if ($originalHash === $validatedHash) {
            return true;
        }
        
        return false;
    }

    protected function _getHashAlgoByLength($length)
    {
        switch ((int)$length) {
            case 20:
                $algo = 'sha1';
                break;
                
            case 32:
                $algo = 'sha256';
                break;
                
            case 64:
                $algo = 'sha512';
                break;
                
            default:
                throw new \InvalidArgumentException('invalid hash length');
                break;
        }
        
        return $algo;
    }
}