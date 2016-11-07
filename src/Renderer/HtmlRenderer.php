<?php
/**
 * pdepend-process
 *
 * Copyright (c) 2014, Sebastian Bergmann <sebastian@phpunit.de>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Sebastian Bergmann nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package   pdepend-process
 * @author    Sebastian Bergmann <sebastian@phpunit.de>
 * @copyright 2014 Sebastian Bergmann <sebastian@phpunit.de>
 * @license   http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @since     File available since Release 1.0.0
 */

namespace SebastianBergmann\PDEPEND\Process\Renderer;

use SebastianBergmann\PDEPEND\Process\RuntimeException;
use Text_Template;

/**
 * @author    Sebastian Bergmann <sebastian@phpunit.de>
 * @copyright 2014 Sebastian Bergmann <sebastian@phpunit.de>
 * @license   http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link      http://github.com/sebastianbergmann/pdepend-process/tree
 * @since     Interface available since Release 1.0.0
 */
class HtmlRenderer implements RendererInterface
{
    /**
     * @var string
     */
    private $directory;

    /**
     * @var string
     */
    private $templatePath;

    /**
     * @param  string $directory
     * @throws RuntimeException
     */
    public function __construct($directory)
    {
        $this->templatePath = sprintf(
            '%s%sTemplate%s',

            __DIR__,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR
        );

        $this->directory = $this->getDirectory($directory);
    }

    /**
     * @param  array $data
     * @throws RuntimeException
     */
    public function render(array $data)
    {
        $this->copyFiles($this->directory);

        $classLengthChart = $this->renderBarChart(
            $this->partitionData($data['classLength'])
        );

        $methodLengthChart = $this->renderBarChart(
            $this->partitionData($data['methodLength'])
        );

        $ccnChart = $this->renderBarChart(
            $this->partitionData($data['ccn'])
        );

        $npathChart = $this->renderBarChart(
            $this->partitionData($data['npath'])
        );

        $template = new Text_Template(
            $this->templatePath . 'dashboard.html',
            '{{',
            '}}'
        );

        $template->setVar(
            array(
                'classes_table'        => $this->renderClassesTable($data),
                'methods_table'        => $this->rendermethodsTable($data),
                'class_length_labels'  => $classLengthChart['labels'],
                'class_length_values'  => $classLengthChart['values'],
                'method_length_labels' => $methodLengthChart['labels'],
                'method_length_values' => $methodLengthChart['values'],
                'ccn_labels'           => $ccnChart['labels'],
                'ccn_values'           => $ccnChart['values'],
                'npath_labels'         => $npathChart['labels'],
                'npath_values'         => $npathChart['values']
            )
        );

        $template->renderTo($this->directory . 'index.html');
    }

    /**
     * @param  array $data
     * @return array
     */
    private function renderBarChart(array $data)
    {
        $result = array(
            'labels' => json_encode(array_keys($data)),
            'values' => json_encode(array_values($data))
        );

        return $result;
    }

    /**
     * @param  array $data
     * @return string
     */
    private function renderClassesTable(array $data)
    {
        $buffer = '';

        foreach ($data['classes'] as $class => $classData) {
            $buffer .= sprintf(
                '<tr><td>%s</td><td class="text-right">%d</td><td class="text-right">%.2f</td><td class="text-right">%.2f</td></tr>',
                $class,
                $classData['lloc'],
                $classData['cr'],
                $classData['rcr']
            );
        }

        return $buffer;
    }

    /**
     * @param  array $data
     * @return string
     */
    private function renderMethodsTable(array $data)
    {
        $buffer = '';

        foreach ($data['methods'] as $method => $methodData) {
            $npath = sprintf('%g', $methodData['npath']);

            if (strpos($npath, 'e+')) {
                $npath = sprintf(
                    '<abbr title="%s">%s</abbr>',
                    $methodData['npath'],
                    $npath
                );
            }

            $buffer .= sprintf(
                '<tr><td>%s</td><td class="text-right">%d</td><td class="text-right">%d</td><td class="text-right">%s</td></tr>',
                $method,
                $methodData['lloc'],
                $methodData['ccn'],
                $npath
            );
        }

        return $buffer;
    }

    /**
     * @param  array $data
     * @return array
     */
    private function partitionData(array $data)
    {
        $partitions    = array();
        $numPartitions = ceil(2 * pow(count($data), (1/3)));
        $width         = floor(max($data) / $numPartitions);

        foreach ($data as $value) {
            $div = 0;
            if(floatval($value) != 0 && floatval($width) != 0) {
              $div = floor($value / $width);
            }
            $key = $div * $width;
            $key = $key . '-' . ($key + $width);

            if (!isset($partitions[$key])) {
                $partitions[$key] = 1;
            } else {
                $partitions[$key]++;
            }
        }

        ksort($partitions, SORT_NATURAL);

        return $partitions;
    }

    /**
     * @param  string $target
     * @throws RuntimeException
     */
    private function copyFiles($target)
    {
        $dir = $this->getDirectory($target . 'css');
        copy($this->templatePath . 'css/bootstrap.min.css', $dir . 'bootstrap.min.css');
        copy($this->templatePath . 'css/nv.d3.css', $dir . 'nv.d3.css');
        copy($this->templatePath . 'css/style.css', $dir . 'style.css');

        $dir = $this->getDirectory($target . 'fonts');
        copy($this->templatePath . 'fonts/glyphicons-halflings-regular.eot', $dir . 'glyphicons-halflings-regular.eot');
        copy($this->templatePath . 'fonts/glyphicons-halflings-regular.svg', $dir . 'glyphicons-halflings-regular.svg');
        copy($this->templatePath . 'fonts/glyphicons-halflings-regular.ttf', $dir . 'glyphicons-halflings-regular.ttf');
        copy($this->templatePath . 'fonts/glyphicons-halflings-regular.woff', $dir . 'glyphicons-halflings-regular.woff');

        $dir = $this->getDirectory($target . 'js');
        copy($this->templatePath . 'js/bootstrap.min.js', $dir . 'bootstrap.min.js');
        copy($this->templatePath . 'js/d3.min.js', $dir . 'd3.min.js');
        copy($this->templatePath . 'js/holder.js', $dir . 'holder.js');
        copy($this->templatePath . 'js/html5shiv.js', $dir . 'html5shiv.js');
        copy($this->templatePath . 'js/jquery.js', $dir . 'jquery.js');
        copy($this->templatePath . 'js/jquery.tablesorter.min.js', $dir . 'jquery.tablesorter.min.js');
        copy($this->templatePath . 'js/nv.d3.min.js', $dir . 'nv.d3.min.js');
        copy($this->templatePath . 'js/respond.min.js', $dir . 'respond.min.js');
    }

    /**
     * @param  string $directory
     * @return string
     * @throws RuntimeException
     */
    private function getDirectory($directory)
    {
        if (substr($directory, -1, 1) != DIRECTORY_SEPARATOR) {
            $directory .= DIRECTORY_SEPARATOR;
        }

        if (is_dir($directory)) {
            return $directory;
        }

        if (@mkdir($directory, 0777, true)) {
            return $directory;
        }

        throw new RuntimeException(
            sprintf(
                'Directory "%s" does not exist.',
                $directory
            )
        );
    }
}
