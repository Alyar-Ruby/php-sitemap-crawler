<?php
namespace Olden;

/**
 * Extends abstract parser that do all work
 * Here goes methods for give it right DOM selectors to parse
 *
 * Class ItemParser
 * @package Olden
 */
class ItemParser extends AbstractParser
{
    protected function entityName()
    {
        return 'item';
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
        return PageConstants::ITEM_H1;
    }

    /**
     * @return string
     */
    protected function seoDescriptionPath()
    {
        return PageConstants::ITEM_SEO_DESCRIPTION;
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
        return PageConstants::ITEM_IMAGE;
    }
}
