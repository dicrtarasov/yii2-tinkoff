<?php
/**
 * @author Igor A Tarasov <develop@dicr.org>, http://dicr.org
 * @version 24.07.20 01:26:42
 */

/** @noinspection PhpMissingDocCommentInspection */
declare(strict_types = 1);

define('YII_ENV', 'dev');
define('YII_DEBUG', true);

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
