<?php
namespace Fgsl\Groupware\Groupbase\Model\Tree;

use Fgsl\Groupware\Groupbase\Model\Filter\FilterGroup;
use Fgsl\Groupware\Groupbase\Model\Filter\FilterBool; 
use Fgsl\Groupware\Groupbase\Model\Filter\Text;


/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 *  file object filter class
 *
 * @package     Groupbase
 * @subpackage  Filter
 */
class FileObjectFilter extends FilterGroup
{
    /**
     * @var string application of this filter group
     */
    protected $_applicationName = 'Tinebase';

    /**
     * @var string name of model this filter group is designed for
     */
    protected $_modelName = FileObject::class;

    /**
     * @var array filter model fieldName => definition
     */
    protected $_filterModel = [
        'is_deleted'            => ['filter' => FilterBool::class],
        'type'                  => ['filter' => Text::class]
    ];
}
