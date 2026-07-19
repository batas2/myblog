<?php
/**
 * lib/schema.php — JSON-LD structured data builders.
 * Each builder returns an array; ld_script() serializes one into a script tag.
 */

declare(strict_types=1);

// HEX_TAG: a title containing "</script>" must not break out of the JSON-LD tag
const LD_JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;

/** One <script type="application/ld+json"> tag. */
function ld_script(array $schema): string
{
    return '<script type="application/ld+json">' . json_encode($schema, LD_JSON_FLAGS) . '</script>';
}

/** The site author as a schema.org Person reference. */
function schema_person(): array
{
    return ['@type' => 'Person', '@id' => cfg('site_url') . '/#person', 'name' => cfg('author'), 'url' => cfg('site_url')];
}

/** BreadcrumbList from [name => url] pairs, in order. */
function schema_breadcrumbs(array $trail): array
{
    $items = [];
    $pos = 1;
    foreach ($trail as $name => $url) {
        $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $name, 'item' => $url];
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

/** ItemList entries for a list of posts (used standalone and inside CollectionPage). */
function schema_item_list_elements(array $posts): array
{
    return array_map(
        fn($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $p['title'], 'url' => post_url($p['slug'])],
        $posts, array_keys($posts)
    );
}

/** BlogPosting for an article page. */
function schema_blog_posting(array $p, ?array $hero, string $url, string $published): array
{
    $person = schema_person();
    $posting = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $p['title'],
        'description' => $p['subtitle'],
        'datePublished' => $published,
        'dateModified' => $published,
        'author' => $person,
        'publisher' => $person,
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        'url' => $url,
        'inLanguage' => 'en',
        'wordCount' => $p['words'],
        'timeRequired' => 'PT' . $p['minutes'] . 'M',
        'keywords' => implode(', ', $p['tags']),
        'articleSection' => $p['tags'][0] ?? 'Software Architecture',
        'isPartOf' => ['@type' => 'Blog', '@id' => cfg('site_url') . '/blog/#blog', 'name' => 'Field Notes'],
    ];
    if ($hero) {
        $posting['image'] = ['@type' => 'ImageObject', 'url' => cfg('site_url') . $hero['src'], 'width' => $hero['w'], 'height' => $hero['h']];
    }
    if ($p['medium']) {
        $posting['sameAs'] = $p['medium'];
    }
    return $posting;
}

/** The Blog entity for the index page. */
function schema_blog(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Blog',
        '@id' => cfg('site_url') . '/blog/#blog',
        'name' => 'Field Notes',
        'url' => cfg('site_url') . '/blog/',
        'description' => 'Weekly essays on software architecture, the people around it, and the corporate machine they form together.',
        'inLanguage' => 'en',
        'author' => schema_person(),
    ];
}

/** ItemList of all posts for the index page. */
function schema_item_list(array $posts): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'itemListElement' => schema_item_list_elements($posts),
    ];
}

/** CollectionPage for a tag archive. */
function schema_collection(string $tag, array $posts): array
{
    $slug = tag_slug($tag);
    return [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $tag . ' · Field Notes',
        'url' => cfg('site_url') . "/blog/tag/{$slug}/",
        'inLanguage' => 'en',
        'isPartOf' => ['@type' => 'Blog', '@id' => cfg('site_url') . '/blog/#blog'],
        'about' => $tag,
        'mainEntity' => [
            '@type' => 'ItemList',
            'itemListElement' => schema_item_list_elements($posts),
        ],
    ];
}
