<?php
/**
 * Copyright © 2019 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 16.03.2019 17:30
 */

namespace App\Utils;

use HtmlSanitizer\SanitizerInterface;

/**
 * This class is a light interface between an external Markdown parser library
 * and the application. It's generally recommended to create these light interfaces
 * to decouple your application from the implementation details of the third-party library.
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class Markdown
{
    private $parser;
    private $sanitizer;

    public function __construct(SanitizerInterface $sanitizer)
    {
        $this->parser = new \Parsedown();
        $this->sanitizer = $sanitizer;
    }

    public function toHtml(string $text): string
    {
        $html = $this->parser->text($text);
        $safeHtml = $this->sanitizer->sanitize($html);

        return $safeHtml;
    }
}
