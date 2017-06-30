<?php
namespace Olden;

/**
 * Extends abstract parser that do all work
 * Here goes methods for give it right DOM selectors to parse
 *
 * Class ItemParser
 * @package Olden
 */
class BrandParser extends AbstractParser
{
    protected function entityName()
    {
        return 'brand';
    }

    /**
     * @return string
     */
    protected function metaRobotsPath()
    {
        return PageConstants::META_ROBOTS;
    }

    /**
     * @return string
     */
    protected function titlePath()
    {
        return PageConstants::TITLE;
    }

    /**
     * @return string
     */
    protected function metaDescriptionPath()
    {
        return PageConstants::META_DESCRIPTION;
    }

    /**
     * @return string
     */
    protected function h1Path()
    {
        return 'h1';
    }

    /**
     * @return string
     */
    protected function seoDescriptionPath()
    {
        return PageConstants::BRAND_SEO_DESCRIPTION;
    }

    /**
     * @return string
     */
    protected function breadcrumbsTextPath()
    {
        return PageConstants::BREADCRUMBS_TEXT;
    }

    /**
     * @return string
     */
    protected function breadCrumbsLinkPath()
    {
        return PageConstants::BREADCRUMBS_LINK;
    }

    /**
     * @return string
     */
    protected function imagePath()
    {
        return PageConstants::BRAND_IMAGE;
    }
}
