<?php
namespace Fgsl\Groupware\Groupbase\Model;
use Fgsl\Groupware\Groupbase\Record\AbstractRecord;
use Fgsl\Groupware\Groupbase\ModelConfiguration\ModelConfiguration;
use Zend\InputFilter\Input;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * class Path
 * 
 * @package     Tinebase
 * @subpackage  Record
 *
 * @property string     $id
 * @property string     $path
 * @property string     $shadow_path
 */
class Path extends AbstractRecord
{
    /**
     * holds the configuration object (must be declared in the concrete class)
     *
     * @var ModelConfiguration
     */
    protected static $_configurationObject = NULL;

    /**
     * Holds the model configuration (must be assigned in the concrete class)
     *
     * @var array
     */
    protected static $_modelConfiguration = [
        'recordName'        => 'Path',
        'recordsName'       => 'Paths', // ngettext('Path', 'Paths', n)
        'hasRelations'      => false,
        'copyRelations'     => false,
        'hasCustomFields'   => false,
        'hasNotes'          => false,
        'hasTags'           => false,
        'modlogActive'      => false,
        'hasAttachments'    => false,
        'createModule'      => false,
        'exposeHttpApi'     => false,
        'exposeJsonApi'     => false,

        'titleProperty'     => 'path',
        'appName'           => 'Tinebase',
        'modelName'         => 'Path',
        'table'             => array(
            'name'              => 'path',
        ),

        'fields'            => [
            'path'              => [
                'validators'        => [Input::ALLOW_EMPTY => true],
            ],
            'shadow_path'       => [
                'validators'        => [Input::ALLOW_EMPTY => true],
            ],
            'creation_time'     => [
                'type'              => 'datetime',
                'validators'        => [Input::ALLOW_EMPTY => true],
            ],
        ],
    ];

    /**
     * expects a shadow path part of format [] = optional, {} are part of the string!
     * [/]{MODELNAME}RECORDID
     *
     * returns array('parent' => {MODELNAME}RECORDID[{TYPE}], 'child' => [{TYPE}]/{MODELNAME}RECORDID)
     *
     * @param string $_shadowPathPart
     * @return array
     * @throws UnexpectedValue
     */
    public function getNeighbours($_shadowPathPart)
    {
        $shadowPathPart = trim($_shadowPathPart, '/');
        $pathParts = explode('/', ltrim($this->shadow_path, '/'));
        $parentPart = null;
        $childPart = null;
        $childPrefix = '';
        $match = false;

        foreach($pathParts as $pathPart) {
            if (false !== ($pos = strpos($pathPart, '{', 1))) {
                $type = substr($pathPart, $pos);
                $pathPart = substr($pathPart, 0, $pos);
            } else {
                $type = '';
            }
            if (true === $match) {
                $childPart .= $childPrefix . $pathPart;
                break;
            }
            if ($pathPart === $shadowPathPart) {
                $childPrefix = $type . '/';
                $match = true;
            } else {
                $parentPart = $pathPart . $type;
            }
        }

        if (false === $match) {
            throw new UnexpectedValue('trying to get path neighbours for a record that is not part of this path');
        }

        return array('parent' => $parentPart, 'child' => $childPart);
    }

    /**
     * @return array
     * @throws UnexpectedValue
     */
    public function getRecordIds()
    {
        $pathParts = explode('/', ltrim($this->shadow_path, '/'));
        $result = array();
        foreach($pathParts as $pathPart) {
            if (false !== ($pos = strpos($pathPart, '{', 1))) {
                $pathPart = substr($pathPart, 0, $pos);
            }

            if (false === ($pos = strpos($pathPart, '}'))) {
                throw new UnexpectedValue('malformed shadow path: ' . $this->shadow_path . ': working on path part: ' . $pathPart);
            }
            $model = substr($pathPart, 1, $pos - 1);
            $id = substr($pathPart, $pos + 1);
            $result[$pathPart] = array('id' => $id, 'model' => $model);
        }

        return $result;
    }

    public function getRecordIdsOfModel($_model)
    {
        $pathParts = explode('/', ltrim($this->shadow_path, '/'));
        $result = array();
        foreach($pathParts as $pathPart) {
            if (false !== ($pos = strpos($pathPart, '{', 1))) {
                $pathPart = substr($pathPart, 0, $pos);
            }

            if (false === ($pos = strpos($pathPart, '}'))) {
                throw new UnexpectedValue('malformed shadow path: ' . $this->shadow_path . ': working on path part: ' . $pathPart);
            }
            $model = substr($pathPart, 1, $pos - 1);
            if ($model !== $_model) {
                continue;
            }
            $result[] = substr($pathPart, $pos + 1);
        }

        return $result;
    }
}