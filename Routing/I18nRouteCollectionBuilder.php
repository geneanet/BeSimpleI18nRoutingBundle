<?php

namespace Geneanet\I18nRoutingBundle\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class I18nRouteCollectionBuilder
{
    const LOCALE_PARAM = '_locale';
    const LOCALES_PARAM = '_locales';

    /**
     * buildCollection.
     *
     * Available options:
     *
     *  * See Routing class
     *
     * @param  string          $name             The route name
     * @param  array           $localesWithPaths An array with keys locales and values path patterns
     * @param  array           $defaults         An array of default parameter values
     * @param  array           $requirements     An array of requirements for parameters (regexes)
     * @param  array           $options          An array of options
     * @param  string          $host             The host pattern to match
     * @param  string|array    $schemes          A required URI scheme or an array of restricted schemes
     * @param  string|array    $methods          A required HTTP method or an array of restricted methods
     * @param  string          $condition        A condition that should evaluate to true for the route to match
     * @return RouteCollection
     */
    public function buildCollection($name, array $localesWithPaths, array $defaults = array(), array $requirements = array(), array $options = array(), $host = '', $schemes = array(), $methods = array(), $condition = '')
    {
        $collection = new RouteCollection();

        $pathsWithLocales = $this->buildPathsWithLocales($localesWithPaths);

        foreach ($pathsWithLocales as $path => $locales) {
             foreach ($locales as $locale) {
                 $localeRoute = new Route($path, $defaults, $requirements, $options, $host, $schemes, $methods, $condition);
                 $localeRoute->setDefault(self::LOCALE_PARAM, $locale);
                 if (count($locales) > 1) {
                     $localeRoute->setDefault(self::LOCALES_PARAM, $locales);
                 }

                 $collection->add($name.'.'.$locale, $localeRoute);
             }
         }

        return $collection;
    }

    /**
     * @param array $localesWithPaths
     *
     * @return array
     */
    protected function buildPathsWithLocales(array $localesWithPaths)
    {
        $pathsWithLocales = array();

        foreach ($localesWithPaths as $locale => $path) {
            $pathsWithLocales[$path][] = $locale;
        }

        return $pathsWithLocales;
    }
}
