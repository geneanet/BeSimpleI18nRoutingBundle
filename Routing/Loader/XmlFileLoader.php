<?php

namespace Geneanet\I18nRoutingBundle\Routing\Loader;

use Geneanet\I18nRoutingBundle\Routing\I18nRouteCollection;
use Geneanet\I18nRoutingBundle\Routing\I18nRouteCollectionBuilder;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Loader\XmlFileLoader as BaseXmlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * XmlFileLoader
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Andrej Hudec <pulzarraider@gmail.com>
 */
class XmlFileLoader extends BaseXmlFileLoader
{
    const NAMESPACE_URI = 'http://geneanet.org/schema/i18n_routing';

    /**
     * @var I18nRouteCollectionBuilder
     */
    protected $collectionBuilder;

    public function __construct(FileLocatorInterface $locator, I18nRouteCollectionBuilder $collectionBuilder = null)
    {
        parent::__construct($locator);

        if ($collectionBuilder === null) {
            $collectionBuilder = new I18nRouteCollectionBuilder();
        }
        $this->collectionBuilder = $collectionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function load($file, $type = null)
    {
        $path = $this->locator->locate($file);

        $xml = $this->loadFile($path);

        $collection = new I18nRouteCollection();
        $collection->addResource(new FileResource($path));

        // process routes and imports
        foreach ($xml->documentElement->childNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $this->parseNode($collection, $node, $path, $file);
        }

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    protected function parseNode(RouteCollection $collection, \DOMElement $node, $path, $file)
    {
        if (self::NAMESPACE_URI !== $node->namespaceURI) {
            return;
        }

        switch ($node->localName) {
            case 'route':
                $this->parseRoute($collection, $node, $path);
                break;
            case 'import':
                $this->parseImport($collection, $node, $path, $file);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unknown tag "%s" used in file "%s". Expected "route" or "import".', $node->localName, $path));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && 'xml' === pathinfo($resource, PATHINFO_EXTENSION) && ('geneanet_i18n' === $type);
    }

    /**
     * {@inheritdoc}
     */
    protected function parseRoute(RouteCollection $collection, \DOMElement $node, $path)
    {
        if ('' === ($id = $node->getAttribute('id')) || (!$node->hasAttribute('pattern') && !$node->hasAttribute('path'))) {
            throw new \InvalidArgumentException(sprintf('The <route> element in file "%s" must have an "id" and a "path" attribute.', $path));
        }

        if ($node->hasAttribute('pattern')) {
            if ($node->hasAttribute('path')) {
                throw new \InvalidArgumentException(sprintf('The <route> element in file "%s" cannot define both a "path" and a "pattern" attribute. Use only "path".', $path));
            }

            @trigger_error(sprintf('The "pattern" option in file "%s" is deprecated since version 2.2 and will be removed in 3.0. Use the "path" option in the route definition instead.', $path), E_USER_DEPRECATED);

            $node->setAttribute('path', $node->getAttribute('pattern'));
            $node->removeAttribute('pattern');
        }

        $schemes = preg_split('/[\s,\|]++/', $node->getAttribute('schemes'), -1, PREG_SPLIT_NO_EMPTY);
        $methods = preg_split('/[\s,\|]++/', $node->getAttribute('methods'), -1, PREG_SPLIT_NO_EMPTY);

        list($defaults, $requirements, $options, $condition, $localesWithPaths) = $this->parseConfigs($node, $path);

        if (isset($requirements['_method'])) {
            if (0 === count($methods)) {
                $methods = explode('|', $requirements['_method']);
            }

            unset($requirements['_method']);
            @trigger_error(sprintf('The "_method" requirement of route "%s" in file "%s" is deprecated since version 2.2 and will be removed in 3.0. Use the "methods" attribute instead.', $id, $path), E_USER_DEPRECATED);
        }

        if (isset($requirements['_scheme'])) {
            if (0 === count($schemes)) {
                $schemes = explode('|', $requirements['_scheme']);
            }

            unset($requirements['_scheme']);
            @trigger_error(sprintf('The "_scheme" requirement of route "%s" in file "%s" is deprecated since version 2.2 and will be removed in 3.0. Use the "schemes" attribute instead.', $id, $path), E_USER_DEPRECATED);
        }

        if ($localesWithPaths) {
            $collection->addCollection(
                $this->collectionBuilder->buildCollection($id, $localesWithPaths, $defaults, $requirements, $options, $node->getAttribute('host'), $schemes, $methods, $condition)
            );
        } else {
            $route = new Route($node->getAttribute('path'), $defaults, $requirements, $options, $node->getAttribute('host'), $schemes, $methods, $condition);
            $collection->add($id, $route);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function parseImport(RouteCollection $collection, \DOMElement $node, $path, $file)
    {
        if ('' === $resource = $node->getAttribute('resource')) {
            throw new \InvalidArgumentException(sprintf('The <import> element in file "%s" must have a "resource" attribute.', $path));
        }

        $type = $node->getAttribute('type');
        $prefix = $node->getAttribute('prefix');
        $host = $node->hasAttribute('host') ? $node->getAttribute('host') : null;
        $schemes = $node->hasAttribute('schemes') ? preg_split('/[\s,\|]++/', $node->getAttribute('schemes'), -1, PREG_SPLIT_NO_EMPTY) : null;
        $methods = $node->hasAttribute('methods') ? preg_split('/[\s,\|]++/', $node->getAttribute('methods'), -1, PREG_SPLIT_NO_EMPTY) : null;

        list($defaults, $requirements, $options, $condition, $prefixes) = $this->parseConfigs($node, $path);

        $this->setCurrentDir(dirname($path));

        $subCollection = $this->import($resource, ('' !== $type ? $type : null), false, $file);
        /* @var $subCollection RouteCollection */
        $subCollection->addPrefix(empty($prefixes) ? $prefix : $prefixes);
        if (null !== $host) {
            $subCollection->setHost($host);
        }
        if (null !== $condition) {
            $subCollection->setCondition($condition);
        }
        if (null !== $schemes) {
            $subCollection->setSchemes($schemes);
        }
        if (null !== $methods) {
            $subCollection->setMethods($methods);
        }
        $subCollection->addDefaults($defaults);
        $subCollection->addRequirements($requirements);
        $subCollection->addOptions($options);

        $collection->addCollection($subCollection);
    }

    /**
     * Parses the config elements (default, requirement, option).
     *
     * @param \DOMElement $node Element to parse that contains the configs
     * @param string      $path Full path of the XML file being processed
     *
     * @return array An array with the defaults as first item, requirements as second and options as third.
     *
     * @throws \InvalidArgumentException When the XML is invalid
     */
    private function parseConfigs(\DOMElement $node, $path)
    {
        $defaults = array();
        $requirements = array();
        $options = array();
        $condition = null;
        $locales = array();

        foreach ($node->getElementsByTagNameNS(self::NAMESPACE_URI, '*') as $n) {
            /** @var \DOMElement $n */
            switch ($n->localName) {
                case 'default':
                    if ($this->isElementValueNull($n)) {
                        $defaults[$n->getAttribute('key')] = null;
                    } else {
                        $defaults[$n->getAttribute('key')] = trim($n->textContent);
                    }

                    break;
                case 'requirement':
                    $requirements[$n->getAttribute('key')] = trim($n->textContent);
                    break;
                case 'option':
                    $options[$n->getAttribute('key')] = trim($n->textContent);
                    break;
                case 'condition':
                    $condition = trim($n->textContent);
                    break;
                case 'locale':
                    $locales[$n->getAttribute('key')] = trim((string) $n->nodeValue);
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unknown tag "%s" used in file "%s". Expected "default", "requirement", "option", "condition" or "locale".', $n->localName, $path));
            }
        }

        return array($defaults, $requirements, $options, $condition, $locales);
    }

    private function isElementValueNull(\DOMElement $element)
    {
        $namespaceUri = 'http://www.w3.org/2001/XMLSchema-instance';

        if (!$element->hasAttributeNS($namespaceUri, 'nil')) {
            return false;
        }

        return 'true' === $element->getAttributeNS($namespaceUri, 'nil') || '1' === $element->getAttributeNS($namespaceUri, 'nil');
    }
}
