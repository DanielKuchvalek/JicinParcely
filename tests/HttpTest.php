<?php

declare(strict_types=1);

use JicinParcely\Http;

test('prázdný seznam prvků z ArcGIS se do cache neukládá (výpadek by jinak vydržel týden)', function () {
    assert_true(!Http::isCacheable(['type' => 'FeatureCollection', 'features' => []]), 'prázdné features');
    assert_true(Http::isCacheable(['type' => 'FeatureCollection', 'features' => [['properties' => ['kod' => 1]]]]), 'neprázdné features');
    assert_true(Http::isCacheable(['suggestions' => []]), 'jiná odpověď než seznam prvků');
});
