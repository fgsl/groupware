<?php

class Custom_Tinebase_Frontend_Http extends Tinebase_Frontend_Http
{
    /**
     * handle openAM http request
     */
    public function openAM()
    {
        Tinebase_Core::startCoreSession();

        $user = Tinebase_Core::getUser();
        if (!$user instanceof Tinebase_Model_FullUser) {
            // try to login user
            $success = (Tinebase_Controller::getInstance()->login(
                            null, null, Tinebase_Core::get(Tinebase_Core::REQUEST), self::REQUEST_TYPE
                    ) === TRUE);

            if ($success === TRUE) {

                $ccAdapter = Tinebase_Auth_CredentialCache::getInstance()->getCacheAdapter();
                if (Tinebase_Core::isRegistered(Tinebase_Core::USERCREDENTIALCACHE)) {
                    $ccAdapter->setCache(Tinebase_Core::getUserCredentialCache());
                } else {
                    Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' Something went wrong with the CredentialCache / no CC registered.');
                    $success = FALSE;
                    $ccAdapter->resetCache();
                }
            }

            // authentication failed
            if ($success !== TRUE) {
                $_SESSION = array();
                Tinebase_Session::destroyAndRemoveCookie();

                // redirect back to loginurl if needed
                $redirectUrl = Tinebase_Config::getInstance()->get(Tinebase_Config::REDIRECTURL, $redirectUrl);
                if (! empty($redirectUrl)) {
                    header('Location: ' . $redirectUrl);
                }
                return;
            }

            $this->_renderMainScreen();
        }
    }
}
