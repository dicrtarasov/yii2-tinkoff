<?php
/*
 * @copyright 2019-2021 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license MIT
 * @version 27.01.21 02:18:04
 */

declare(strict_types = 1);
namespace dicr\tests;

use dicr\tinkoff\TinkoffService;
use dicr\validate\ValidateException;
use PHPUnit\Framework\TestCase;
use yii\base\InvalidConfigException;
use yii\di\Instance;

/**
 * Class LightweightRequestTest
 */
class LightweightRequestTest extends TestCase
{
    /** @var TinkoffService */
    public static $service = 'tinkoff';

    /**
     * @inheritDoc
     * @throws InvalidConfigException
     */
    public static function setUpBeforeClass(): void
    {
        self::$service = Instance::ensure(self::$service, TinkoffService::class);
    }

    /**
     * @throws ValidateException
     */
    public function testGetData(): void
    {
        $request = self::$service->lightweightRequest([
            'sum' => 12345
        ]);

        self::assertTrue($request->validate());

        $data = $request->getData();
        self::assertIsArray($data);
    }
}
