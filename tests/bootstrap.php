<?php
/*
 * @copyright 2019-2021 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license MIT
 * @version 12.04.21 03:10:35
 */

/** @noinspection PhpMissingDocCommentInspection */
declare(strict_types = 1);

const YII_ENV = 'dev';
const YII_DEBUG = true;

require_once(dirname(__DIR__) . '/vendor/autoload.php');
require_once(dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php');

/** @noinspection PhpUnhandledExceptionInspection */
new yii\console\Application([
    'id' => 'test',
    'basePath' => __DIR__,
    'components' => [
        'cache' => yii\caching\ArrayCache::class,

        'tinkoff' => [
            'class' => dicr\tinkoff\TinkoffService::class,
            'lightweightConfig' => [
                'url' => dicr\tinkoff\LightweightRequest::TEST_URL,
                'shopId' => dicr\tinkoff\LightweightRequest::TEST_SHOP_ID,
                'showcaseId' => dicr\tinkoff\LightweightRequest::TEST_SHOWCASE_ID
            ]
        ]
    ]
]);
