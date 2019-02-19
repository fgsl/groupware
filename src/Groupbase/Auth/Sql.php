<?php
namespace Fgsl\Groupware\Groupbase\Auth;
use Zend\Authentication\Adapter\DbTable;
use Zend\Authentication\Result;
use Fgsl\Groupware\Groupbase\Hash\Password;

/**
*
* @package     Groupbase
* @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
* @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
* @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
*
*/

/**
 * SQL authentication backend
 * 
 * @package     Groupbase
 * @subpackage  Auth 
 */
class Sql extends DbTable implements AuthInterface
{
    const ACCTNAME_FORM_USERNAME  = 2;
    const ACCTNAME_FORM_BACKSLASH = 3;
    const ACCTNAME_FORM_PRINCIPAL = 4;
    
    /**
     * setIdentity() - set the value to be used as the identity
     *
     * @param  string $value
     * @return DbTable Provides a fluent interface
     */
    public function setIdentity($value)
    {
        $canonicalName = $this->getCanonicalAccountName($value);
        
        $this->_identity = $canonicalName;
        return $this;
    }
    
    /**
     * @param string $acctname The name to canonicalize
     * @param int $form The desired form of canonicalization
     * @return string The canonicalized name in the desired form
     * @throws \Exception
     */
    public function getCanonicalAccountName($acctname, $form = 0)
    {
        $uname = null;
        $dname = null;
        $this->_splitName($acctname, $dname, $uname);

        if (! $this->_isPossibleAuthority($dname)) {
            /**
             * @see \Exception
             */
            throw new \Exception("Domain is not an authority for user: $acctname");
        }

        if (!$uname) {
            /**
             * @see \Exception
             */
            throw new \Exception("Invalid account name syntax: $acctname");
        }

        if (function_exists('mb_strtolower')) {
            $uname = mb_strtolower($uname, 'UTF-8');
        } else {
            $uname = strtolower($uname);
        }

        if ($form === 0) {
            $form = $this->_getAccountCanonicalForm();
        }

        switch ($form) {
            case self::ACCTNAME_FORM_USERNAME:
                return $uname;
            case self::ACCTNAME_FORM_BACKSLASH:
                $accountDomainNameShort = $this->_getAccountDomainNameShort();
                if (!$accountDomainNameShort) {
                    /**
                     * @see \Exception
                     */
                    throw new \Exception('Option required: accountDomainNameShort');
                }
                return "$accountDomainNameShort\\$uname";
            case self::ACCTNAME_FORM_PRINCIPAL:
                $accountDomainName = $this->_getAccountDomainName();
                if (!$accountDomainName) {
                    /**
                     * @see \Exception
                     */
                    throw new \Exception('Option required: accountDomainName');
                }
                return "$uname@$accountDomainName";
            default:
                /**
                 * @see \Exception
                 */
                throw new \Exception("Unknown canonical name form: $form");
        }
    }
    
    /**
     * split username in domain and account name
     * 
     * @param string $name The name to split
     * @param string $dname The resulting domain name (this is an out parameter)
     * @param string $aname The resulting account name (this is an out parameter)
     */
    protected function _splitName($name, &$dname, &$aname)
    {
        $dname = null;
        $aname = $name;

        if (! Auth::getBackendConfiguration('tryUsernameSplit', TRUE)) {
            return;
        }

        $pos = strpos($name, '@');
        if ($pos) {
            $dname = substr($name, $pos + 1);
            $aname = substr($name, 0, $pos);
        } else {
            $pos = strpos($name, '\\');
            if ($pos) {
                $dname = substr($name, 0, $pos);
                $aname = substr($name, $pos + 1);
            }
        }
    }
    
    
    
    /**
     * @param string $dname The domain name to check
     * @return boolean
     */
    protected function _isPossibleAuthority($dname)
    {
        if ($dname === null) {
            return true;
        }
        
        $accountDomainName      = $this->_getAccountDomainName();
        $accountDomainNameShort = $this->_getAccountDomainNameShort();
        
        if (empty($accountDomainName) && empty($accountDomainNameShort)) {
            return true;
        }
        if (strcasecmp($dname, $accountDomainName) == 0) {
            return true;
        }
        if (strcasecmp($dname, $accountDomainNameShort) == 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * _authenticateValidateResult() - This method attempts to validate that the record in the
     * result set is indeed a record that matched the identity provided to this adapter.
     *
     * @param array $resultIdentity
     * @return Result
     */
    protected function _authenticateValidateResult($resultIdentity)
    {
        if (empty($resultIdentity[$this->_credentialColumn])) {
            $validatedPw = ($this->_credential === '');
        } else {
            $passwordHash = substr($resultIdentity[$this->_credentialColumn], 0, 1) === '{' 
                ? $resultIdentity[$this->_credentialColumn] 
                : '{PLAIN-MD5}' . $resultIdentity[$this->_credentialColumn];
            $validatedPw = Password::validate($passwordHash, $this->_credential);
        }
        
        if ($validatedPw !== TRUE) {
            $this->_authenticateResultInfo['code'] = Result::FAILURE_CREDENTIAL_INVALID;
            $this->_authenticateResultInfo['messages'][] = 'Supplied credential is invalid.';
            return $this->_authenticateCreateAuthResult();
        }

        unset($resultIdentity['zend_auth_credential_match']);
        $this->_resultRow = $resultIdentity;

        $this->_authenticateResultInfo['code'] = Result::SUCCESS;
        $this->_authenticateResultInfo['messages'][] = 'Authentication successful.';
        return $this->_authenticateCreateAuthResult();
    }

    /**
     * @return string Either ACCTNAME_FORM_BACKSLASH, ACCTNAME_FORM_PRINCIPAL or
     * ACCTNAME_FORM_USERNAME indicating the form usernames should be canonicalized to.
     */
    protected function _getAccountCanonicalForm()
    {
        /* Account names should always be qualified with a domain. In some scenarios
         * using non-qualified account names can lead to security vulnerabilities. If
         * no account canonical form is specified, we guess based in what domain
         * names have been supplied.
         */

        $accountCanonicalForm = Auth::getBackendConfiguration('accountCanonicalForm', FALSE);
        if (!$accountCanonicalForm) {
            $accountDomainName = $this->_getAccountDomainName();
            $accountDomainNameShort = $this->_getAccountDomainNameShort();
            if ($accountDomainNameShort) {
                $accountCanonicalForm = self::ACCTNAME_FORM_BACKSLASH;
            } else if ($accountDomainName) {
                $accountCanonicalForm = self::ACCTNAME_FORM_PRINCIPAL;
            } else {
                $accountCanonicalForm = self::ACCTNAME_FORM_USERNAME;
            }
        }

        return $accountCanonicalForm;
    }
    
    /**
     * @return string The account domain name
     */
    protected function _getAccountDomainName()
    {
        return Auth::getBackendConfiguration('accountDomainName', NULL);
    }
    
    /**
     * @return string The short account domain name
     */
    protected function _getAccountDomainNameShort()
    {
        return Auth::getBackendConfiguration('accountDomainNameShort', NULL);
    }
}
