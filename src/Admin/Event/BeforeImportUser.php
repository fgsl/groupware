<?php
namespace Fgsl\Groupware\Admin\Event;
use Fgsl\Groupware\Groupbase\Event\AbstractEvent;
use Fgsl\Groupware\Groupbase\Model\FullUser;

/**
 * @package     Admin
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * thrown before a user account gets imported
 *
 */
class BeforeImportUser extends AbstractEvent
{
    /**
     * @var FullUser account of the teacher
     */
    public $account;
    
    /**
     * @var array options of the import plugin
     */
    public $options;
    
    public function __construct($_account, $_options)
    {
        $this->account = $_account;
        $this->options = $_options;
    }
}