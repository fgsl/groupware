<?php
namespace Fgsl\Groupware\Groupbase\FileSystem\Preview;
use Fgsl\Groupware\Groupbase\Exception\UnexpectedValue;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */
/**
 * filesystem preview service interface
 *
 * @package     Tinebase
 * @subpackage  Filesystem
 */
interface ServiceInterface
{
    /**
     * Uses the DocumentPreviewService to generate previews (images or pdf files) for a file.
     * The preview style (filetype, size, etc.) can be determined by the configuration.
     *
     * A config can consist of multiple conversion configs with a uniqid key.
     *
     * Types:
       Pdf (pdf, gs), Image (png, jpg, ...), Document (odt, docx, xls, ...)
     *
     * Config:
       [
         "synchronRequest" => boolen,   true: realtime priority, false: retry on failure
         "KEY1" => [
            "fileType" => string,      target filetype can be an image extention or 'pdf'
                                       image options:
            "firstPage" => boolen,     only generate preview for first page of each multi page document
            "x" => int,                image max width
            "y" => int,                image max height
            "color" => string/false    color: Rescale image to fit into x,y fills background and margins with color.
                                              (documents have a transparent background, it will be filled)
                                       false: Rescale image to fit into x,y. Aspect ratio is preserved.
         ],
         "KEY2" => [                 second config
            ...
         ],
         ...
       ]
     *
     * Result:
       [
         "KEY1" => [
           binary data,
           ...
         ],
         "KEY1" => [
           binary data,
           ...
         ],
         ...
       ]
     *
     * @param $filePath
     * @param array $config
     * @return array|bool
     */
    public function getPreviewsForFile($filePath, array $config);

    /**
     * Uses the DocumentPreviewService to generate previews (images or pdf files) for multiple files of same type.
     * The preview style (filetype, size, etc.) can be determined by the configuration.
     *
     * A config can consist of multiple conversion configs with a uniqid key.
     *
     * Types:
       Pdf (pdf, gs), Image (png, jpg, ...), Document (odt, docx, xls, ...)
     *
     * Config:
       [
         "synchronRequest" => boolen,   true: realtime priority, false: retry on failure
         "KEY1" => [
             "fileType" => string,      target filetype can be an image extention or 'pdf'
                                        pdf option:
             "merge" => boolen          merge files into a single pdf
                                        image options:
             "firstPage" => boolen,     only generate preview for first page of each multi page document
             "x" => int,                image max width
             "y" => int,                image max height
             "color" => string/false    color: Rescale image to fit into x,y fills background and margins with color.
                                            (documents have a transparent background, it will be filled)
                                        false: Rescale image to fit into x,y. Aspect ratio is preserved.
            ],
            "KEY2" => [                 second config
                ...
            ],
             ...
       ]
     *
     * Result:
       [
         "KEY1" => [
             binary data,
             ...
         ],
         "KEY1" => [
             binary data,
             ...
         ],
         ...
       ]
     *
     * @param $filePaths array of file Paths to convert
     * @param array $config
     * @return array|bool
     */
    public function getPreviewsForFiles(array $filePaths, array $config);

    /**
     * Uses the DocumentPreviewService to generate pdfs for a documentfile.
     *
     * @param $filePath
     * @param $synchronRequest bool should the request be prioritized
     * @return string file blob
     * @throws UnexpectedValue preview service did not succeed
    */
    public function getPdfForFile($filePath, $synchronRequest = false);
}