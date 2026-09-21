<?php
// +----------------------------------------------------------------------
// | Rabbit多商户商城系统
// +----------------------------------------------------------------------
// | Copyright (c) 2022~2026 https://www.rabshop.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed Rabbit多商户商城系统不是自由软件，未经许可不能去掉Rabbit相关版权
// +----------------------------------------------------------------------
// | Author: Rabbit Team <admin@rabshop.cn>
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace app\common\service\content;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * 文章 HTML 清洗器。
 *
 * 文章内容会在多个管理端预览和移动端详情中以 HTML 渲染，
 * 因此这里统一采用标签、属性和 URL 协议 allowlist。
 */
class ArticleHtmlSanitizer
{
    /**
     * 允许保留的 HTML 标签。
     */
    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'code', 'div', 'em', 'h1', 'h2', 'h3',
        'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 'span',
        'strong', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    /**
     * 直接删除的危险容器及内容。
     */
    private const DROP_TAGS = [
        'audio', 'base', 'button', 'embed', 'form', 'iframe', 'input', 'link', 'meta',
        'object', 'script', 'source', 'style', 'svg', 'textarea', 'video',
    ];

    /**
     * 清洗文章 HTML。
     *
     * @param string $html 原始 HTML。
     * @return string 清洗后的安全 HTML。
     */
    public function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // DOM 扩展不可用时退化为纯文本，宁可丢失排版也不能透传不可信 HTML。
        if (!class_exists(DOMDocument::class)) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorMode = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"?><!DOCTYPE html><html><body><div id="article-sanitizer-root">'
                . $html
                . '</div></body></html>',
                LIBXML_NONET
            );
            if (!$loaded) {
                return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $root = $this->findRoot($document);
            if (!$root) {
                return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $this->sanitizeChildren($root);
            $result = '';
            foreach ($root->childNodes as $child) {
                $result .= $document->saveHTML($child);
            }

            return trim($result);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    /**
     * 获取用于承载 HTML 片段的根节点。
     */
    private function findRoot(DOMDocument $document): ?DOMElement
    {
        foreach ($document->getElementsByTagName('div') as $element) {
            if ($element->getAttribute('id') === 'article-sanitizer-root') {
                return $element;
            }
        }

        return null;
    }

    /**
     * 递归清洗节点树。
     */
    private function sanitizeChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;

            if ($node instanceof DOMElement) {
                $tagName = strtolower($node->tagName);
                $isForeignNode = $node->namespaceURI !== null
                    && strtolower($node->namespaceURI) !== 'http://www.w3.org/1999/xhtml';

                if ($isForeignNode || in_array($tagName, self::DROP_TAGS, true)) {
                    $parent->removeChild($node);
                } elseif (!in_array($tagName, self::ALLOWED_TAGS, true)) {
                    // 未知的排版标签仅解包，子节点仍需先递归清洗。
                    $this->sanitizeChildren($node);
                    while ($node->firstChild !== null) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                } elseif (!$this->sanitizeAttributes($node, $tagName)) {
                    $parent->removeChild($node);
                } else {
                    $this->sanitizeChildren($node);
                }
            } elseif ($node->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($node);
            }

            $node = $next;
        }
    }

    /**
     * 清洗标签属性，返回 false 表示应删除整个标签。
     */
    private function sanitizeAttributes(DOMElement $element, string $tagName): bool
    {
        $allowed = ['class', 'title'];
        if ($tagName === 'a') {
            $allowed = array_merge($allowed, ['href', 'rel', 'target']);
        } elseif ($tagName === 'img') {
            $allowed = array_merge($allowed, ['alt', 'height', 'loading', 'src', 'width']);
        } elseif (in_array($tagName, ['td', 'th'], true)) {
            $allowed = array_merge($allowed, ['colspan', 'rowspan']);
        }

        for ($index = $element->attributes->length - 1; $index >= 0; $index--) {
            $attribute = $element->attributes->item($index);
            if (!$attribute) {
                continue;
            }

            $name = strtolower($attribute->name);
            if (!in_array($name, $allowed, true)) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            if (in_array($name, ['href', 'src'], true) && !$this->isSafeUrl($attribute->value, $name === 'src')) {
                if ($name === 'src') {
                    return false;
                }
                $element->removeAttributeNode($attribute);
            }
        }

        if ($tagName === 'img' && !$element->hasAttribute('src')) {
            return false;
        }

        if ($tagName === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }

        return true;
    }

    /**
     * 判断链接是否使用允许的协议或安全相对路径。
     */
    private function isSafeUrl(string $url, bool $image = false): bool
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $url = preg_replace('/[\x00-\x20\x7f]+/u', '', $url) ?? '';
        if ($url === '' || str_starts_with($url, '//')) {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $matches) === 1) {
            $allowedSchemes = $image ? ['http', 'https'] : ['http', 'https', 'mailto'];
            return in_array(strtolower($matches[1]), $allowedSchemes, true);
        }

        return $url[0] === '#' || $url[0] === '/' || str_starts_with($url, './')
            || str_starts_with($url, '../') || !str_contains($url, ':');
    }
}
