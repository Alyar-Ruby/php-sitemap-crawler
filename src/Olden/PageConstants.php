<?php
namespace Olden;

/**
 * xPath & css selectors definitions
 *
 * Class PageConstants
 * @package Olden
 */
class PageConstants
{
    const BRANDS_SITEMAP_URL = 'https://www.famous-smoke.com/sitemap/type/brands';
    const ITEMS_SITEMAP_URL = 'https://www.famous-smoke.com/sitemap/type/products';

    const META_ROBOTS = '//html/head/meta[@name="robots"]/@content';
    const TITLE = '//html/head/title';
    const META_DESCRIPTION = '//html/head/meta[@name="description"]/@content';
    const BREADCRUMBS_TEXT = 'div.breadcrumb a span[itemprop="title"]';
    const BREADCRUMBS_LINK = 'div.breadcrumb a meta[itemprop="url"]';

    const BRAND_SEO_DESCRIPTION = '.more-text > p';
    const BRAND_IMAGE = 'div.brandtop div.brandband img';

    const ITEM_H1 = 'input[name=\'product_id-div\']:checked + div.ic .title.oswald';
    const ITEM_SEO_DESCRIPTION = '.threequarters > p';
    const ITEM_IMAGE = '.mainimg.imgzoom';

    const REPORT_HEADERS = [
        "URL",
        "CANONICAL URL",
        "INDEX",
        "TITLE",
        "TITLE COMPARISON",
        "META DESCRIPTION",
        "META DESCRIPTION SPELL CHECK",
        "HEADER H1",
        "H1 COMPARISON",
        "SEO PARAGRAPH",
        "SEO PARAGRAPH SPELL CHECK",
        "BREADCRUMBS TEXT",
        "BREADCRUMBS COMPARISON",
        "BREADCRUMBS LINK",
        "IDENTIFIED",
        "NAGIF",
        "ALT TAG"
    ];

    const REPORT_CHANGES_HEADERS = [
        "RUN",      // This field is added only, contains name of report file
        "URL",
        "CANONICAL URL",
        "INDEX",
        "TITLE",
        "TITLE COMPARISON",
        "META DESCRIPTION",
        "META DESCRIPTION SPELL CHECK",
        "HEADER H1",
        "H1 COMPARISON",
        "SEO PARAGRAPH",
        "SEO PARAGRAPH SPELL CHECK",
        "BREADCRUMBS TEXT",
        "BREADCRUMBS COMPARISON",
        "BREADCRUMBS LINK",
        "IDENTIFIED",
        "NAGIF",
        "ALT TAG"
    ];

    const REPORT_DETAILS_HEADERS = [
        "URL",
        "CANONICAL URL",
        "INDEX",
        "TITLE",
        "TITLE COMPARISON",
        "META DESCRIPTION",
        "META DESCRIPTION SPELL CHECK",
        "HEADER H1",
        "H1 COMPARISON",
        "SEO PARAGRAPH",
        "SEO PARAGRAPH SPELL CHECK",
        "BREADCRUMBS TEXT",
        "BREADCRUMBS COMPARISON",
        "BREADCRUMBS LINK",
        "IDENTIFIED",
        "NAGIF",
        "ALT TAG",
        "CHECKED DATE",     // Modification check Date/Time
        "CHANGE STATUS"     // Modified Status  ("Add" or "Remove")
    ];
}
