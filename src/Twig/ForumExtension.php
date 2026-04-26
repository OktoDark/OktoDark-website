<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Twig;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ForumExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('forum_markdown', [$this, 'renderMarkdown'], ['is_safe' => ['html']]),
        ];
    }

    public function renderMarkdown(?string $content): string
    {
        if (!$content) {
            return '';
        }

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $converter->convert($content)->getContent();
    }
}
