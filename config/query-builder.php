<?php

return [

    /*
     * By default the package will use the `include`, `filter`, `sort`
     * and `fields` query parameters as described in the readme.
     *
     * You can customize these query string parameters here.
     */
    'parameters' => [
        'include' => 'include',
        'filter' => 'filter',
        'sort' => 'sort',
        'fields' => 'fields',
        'append' => 'append',
    ],

    /*
     * Related model counts are included using the `count_type` key.
     */
    'count_type' => 'exact',

    /*
     * By default the package will throw an `InvalidFilterQuery` exception
     * when a filter in the URL is not allowed in the `allowedFilters()` method.
     *
     * Disabled because our UsesQueryBuilder trait transforms flat filter_* params
     * into the filter[] format, and some residual params may not be in allowedFilters.
     */
    'disable_invalid_filter_query_exception' => true,

    /*
     * By default the package will throw an `InvalidSortQuery` exception when
     * a sort in the URL is not allowed in the `allowedSorts()` method.
     *
     * Disabled for the same backward-compatibility reason as filters.
     */
    'disable_invalid_sort_query_exception' => true,

    'disable_invalid_includes_query_exception' => false,

    /*
     * By default the package inspects request data from both the request body
     * and the query string. You can choose to limit this to one or the other.
     */
    'request_data_source' => 'source',

];
