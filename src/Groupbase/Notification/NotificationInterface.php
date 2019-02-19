<?php
namespace Fgsl\Groupware\Groupbase\Notification;
use Fgsl\Groupware\Groupbase\Model\FullUser;
use Fgsl\Groupware\Addressbook\Model\Contact;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * notifications interface
 *
 * @package     Groupbase
 * @subpackage  Notification
 */
interface NotificationInterface
{
   /**
     * send a notification
     *
     * @param FullUser                  $_updater
     * @param Contact                   $_recipient
     * @param string                    $_subject the subject
     * @param string                    $_messagePlain the message as plain text
     * @param string                    $_messageHtml the message as html
     * @param string|array              $_attachements
     */
    public function send($_updater, Contact $_recipient, $_subject, $_messagePlain, $_messageHtml = NULL, $_attachements = NULL);
}
