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

namespace app\common\service\about;

use app\common\model\about\About;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * 关于我们服务。
 */
class AboutService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 富文本允许的标签。
     */
    private const ALLOWED_TAGS = [
        'a',
        'b',
        'blockquote',
        'br',
        'code',
        'del',
        'div',
        'em',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'hr',
        'i',
        'img',
        'li',
        'ol',
        'p',
        'pre',
        's',
        'span',
        'strong',
        'sub',
        'sup',
        'table',
        'tbody',
        'td',
        'tfoot',
        'th',
        'thead',
        'tr',
        'u',
        'ul',
    ];

    /**
     * 保存时直接删除的危险标签。
     */
    private const BLOCKED_TAGS = [
        'applet',
        'base',
        'embed',
        'form',
        'iframe',
        'input',
        'link',
        'meta',
        'object',
        'script',
        'select',
        'style',
        'svg',
        'textarea',
        'video',
    ];

    /**
     * 关于我们内容模型。
     */
    protected function setModel(): string
    {
        return About::class;
    }

    /**
     * 获取后台配置页数据。
     */
    public function schema(): array
    {
        return $this->detail();
    }

    /**
     * 获取公开的关于我们内容。
     */
    public function detail(): array
    {
        $about = $this->getLatest();

        return [
            'id' => $about ? (int)$about->id : 0,
            'content' => $about ? (string)($about->content ?? '') : '',
            'update_time' => $about ? (string)($about->update_time ?? '') : '',
        ];
    }

    /**
     * 保存单例富文本内容。
     */
    public function saveContent(string $content): array
    {
        $cleanContent = $this->sanitizeContent($content);

        $this->transaction(function () use ($cleanContent): void {
            $about = $this->getLatest();
            if ($about) {
                $this->update((int)$about->id, [
                    'content' => $cleanContent,
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
                return;
            }

            $this->create([
                'content' => $cleanContent,
            ]);
        });

        return $this->detail();
    }

    /**
     * 获取最新的一条关于我们内容。
     */
    protected function getLatest(): ?About
    {
        $about = $this->getModel()
            ->order('id', 'desc')
            ->find();

        return $about instanceof About ? $about : null;
    }

    /**
     * 清洗富文本内容，保留常用结构并移除脚本注入风险。
     */
    protected function sanitizeContent(string $content): string
    {
        $content = trim(str_replace("\0", '', $content));
        if ($content === '') {
            return '';
        }

        if (!class_exists(DOMDocument::class)) {
            return $this->sanitizeWithoutDom($content);
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapperId = '__about_us_content_root';
        $document = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'
            . '<div id="' . $wrapperId . '">' . $content . '</div>'
            . '</body></html>';
        $loaded = $dom->loadHTML($document, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$loaded) {
            return $this->sanitizeWithoutDom($content);
        }

        $root = $dom->getElementById($wrapperId);
        if (!$root instanceof DOMElement) {
            return $this->sanitizeWithoutDom($content);
        }

        $this->sanitizeNode($root);

        $result = '';
        for ($index = 0; $index < $root->childNodes->length; $index++) {
            $child = $root->childNodes->item($index);
            if ($child instanceof DOMNode) {
                $result .= $dom->saveHTML($child);
            }
        }

        return trim($result);
    }

    /**
     * 递归清洗 DOM 节点。
     */
    protected function sanitizeNode(DOMNode $parent): void
    {
        for ($index = $parent->childNodes->length - 1; $index >= 0; $index--) {
            $child = $parent->childNodes->item($index);
            if (!$child instanceof DOMNode) {
                continue;
            }

            if ($child->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($child);
                continue;
            }

            if (!$child instanceof DOMElement) {
                if ($child->nodeType !== XML_TEXT_NODE) {
                    $parent->removeChild($child);
                }
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::BLOCKED_TAGS, true)) {
                $parent->removeChild($child);
                continue;
            }

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);
                continue;
            }

            $this->sanitizeAttributes($child, $tag);
            $this->sanitizeNode($child);
        }
    }

    /**
     * 清洗单个元素属性。
     */
    protected function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowedAttributes = [
            'a' => ['class', 'href', 'rel', 'style', 'target', 'title'],
            'img' => ['alt', 'class', 'height', 'src', 'style', 'title', 'width'],
            'table' => ['align', 'border', 'cellpadding', 'cellspacing', 'class', 'style', 'width'],
            'td' => ['align', 'class', 'colspan', 'height', 'rowspan', 'style', 'valign', 'width'],
            'th' => ['align', 'class', 'colspan', 'height', 'rowspan', 'style', 'valign', 'width'],
            '*' => ['align', 'class', 'style', 'title'],
        ];
        $allowed = $allowedAttributes[$tag] ?? $allowedAttributes['*'];

        for ($index = $element->attributes->length - 1; $index >= 0; $index--) {
            $attribute = $element->attributes->item($index);
            if (!$attribute) {
                continue;
            }

            $name = strtolower($attribute->name);
            $value = trim((string)$attribute->value);
            if (str_starts_with($name, 'on') || !in_array($name, $allowed, true)) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            if ($name === 'style') {
                $style = $this->sanitizeStyle($value);
                if ($style === '') {
                    $element->removeAttributeNode($attribute);
                } else {
                    $element->setAttribute($attribute->name, $style);
                }
                continue;
            }

            if (in_array($name, ['href', 'src'], true)) {
                $isImage = $tag === 'img' && $name === 'src';
                if (!$this->isSafeUrl($value, $isImage)) {
                    $element->removeAttributeNode($attribute);
                }
                continue;
            }

            if ($name === 'target' && !in_array($value, ['_blank', '_self', '_parent', '_top'], true)) {
                $element->removeAttributeNode($attribute);
            }
        }
    }

    /**
     * 清洗样式属性，保留颜色、字号、对齐等常用编辑格式。
     */
    protected function sanitizeStyle(string $style): string
    {
        $style = trim($style);
        if ($style === '' || preg_match('/(?:expression\s*\(|javascript\s*:|vbscript\s*:|data\s*:|@import|behavior\s*:|-moz-binding\s*:)/i', $style)) {
            return '';
        }

        return $style;
    }

    /**
     * 判断链接或图片地址是否安全。
     */
    protected function isSafeUrl(string $url, bool $isImage = false): bool
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return false;
        }

        $normalized = preg_replace('/[\x00-\x20]+/u', '', $url) ?: $url;
        if (preg_match('/^(?:javascript|vbscript):/i', $normalized)) {
            return false;
        }

        if (str_starts_with(strtolower($normalized), 'data:')) {
            return $isImage && (bool)preg_match(
                '/^data:image\/(?:gif|png|jpeg|jpg|webp);base64,[a-z0-9+\/=\s]+$/i',
                $normalized
            );
        }

        return (bool)preg_match('/^(?:https?:|mailto:|tel:|\/|\.\/|\.\.\/|#)/i', $url);
    }

    /**
     * DOM 扩展不可用时的降级清洗。
     */
    protected function sanitizeWithoutDom(string $content): string
    {
        $content = preg_replace(
            '/<\s*(?:applet|base|embed|form|iframe|input|link|meta|object|script|select|style|svg|textarea|video)\b[^>]*>.*?<\s*\/\s*(?:applet|base|embed|form|iframe|input|link|meta|object|script|select|style|svg|textarea|video)\s*>/is',
            '',
            $content
        ) ?? '';
        $content = preg_replace('/\s+on[a-z0-9_-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $content) ?? $content;
        $content = preg_replace(
            '/\s+(?:href|src)\s*=\s*(["\'])\s*(?:javascript|vbscript):.*?\1/is',
            '',
            $content
        ) ?? $content;
        $content = preg_replace(
            '/\s+style\s*=\s*(["\'])[^"\']*(?:expression|javascript|vbscript|data:)[^"\']*\1/is',
            '',
            $content
        ) ?? $content;

        return trim($content);
    }
}
