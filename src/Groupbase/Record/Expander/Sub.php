<?php
namespace Fgsl\Groupware\Groupbase\Record\Expander;
use Fgsl\Groupware\Groupbase\Exception\NotImplemented;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
abstract class Sub extends AbstractExpander
{
    protected $_recordsToProcess;

    protected function _registerDataToFetch(DataRequest $_dataRequest)
    {
        throw new NotImplemented('do not call this method on ' . self::class);
    }
}