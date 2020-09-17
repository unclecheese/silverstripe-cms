<?php


namespace SilverStripe\CMS\GraphQL;


use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\GraphQL\Schema\DataObject\Plugin\QueryFilter\QueryFilter;
use SilverStripe\GraphQL\Schema\Field\ModelQuery;
use SilverStripe\GraphQL\Schema\Interfaces\ModelQueryPlugin;
use SilverStripe\GraphQL\Schema\Schema;

class LinkablePlugin implements ModelQueryPlugin
{
    const IDENTIFIER = 'getByLink';

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function apply(ModelQuery $query, Schema $schema, array $config = []): void
    {
        $class = $query->getModel()->getSourceClass();
        // Only site trees have the get_by_link capability
        if ($class !== SiteTree::class && !is_subclass_of($class, SiteTree::class)) {
            return;
        }
        // if the query is intended to return a list, `link` doesn't apply here
        if ($query->isList()) {
            return;
        }

        $filterPluginID = QueryFilter::singleton()->getIdentifier();
        if ($query->hasPlugin($filterPluginID)) {
            $args = $query->getArgs();
            $filterArg = null;
            foreach ($args as $arg) {
                if ($arg->getName() === QueryFilter::config()->get('field_name')) {
                    $filterArg = $arg;
                    break;
                }
            }
            Schema::invariant(
                $filterArg,
                'Plugin "%s" was applied but the "%s" plugin has not run yet. Make sure it is set to after: %s',
                $this->getIdentifier(),
                $filterPluginID,
                $filterPluginID
            );
            $inputTypeName = $filterArg->getType();
            $inputType = $schema->getType($inputTypeName);
            Schema::invariant(
                $inputType,
                'Input type "%s" is not in the schema but the %s plugin is applied',
                $inputTypeName,
                $filterPluginID
            );
            $inputType->addField('link', 'String');
            $query->addResolverAfterware([static::class, 'applyLinkFilter']);
        } else {
            $query->addArg('link', 'String');
            $query->addResolverAfterware([static::class, 'applyLinkFilter']);
        }
    }

    /**
     * @param $obj
     * @param array $args
     * @param array $context
     * @param ResolveInfo $info
     * @param callable $done
     * @return SiteTree|null
     */
    public static function applyLinkFilter(
        $obj, array $args,
        array $context,
        ResolveInfo $info,
        callable $done
    ): ?SiteTree {
        $filterLink = $args['filter']['link'] ?? null;
        $argLink = $args['link'] ?? null;
        $filterLink = $filterLink ?: $argLink;

        if ($filterLink) {
            $done();
            return SiteTree::get_by_link($filterLink);
        }

        return $obj;
    }
}
