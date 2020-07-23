<?php
/**
 * @author Igor A Tarasov <develop@dicr.org>, http://dicr.org
 * @version 24.07.20 01:25:31
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
    public function testGetData()
    {
        $request = self::$service->lightweightRequest([
            'sum' => 12345
        ]);

        self::assertTrue($request->validate());

        $data = $request->getData();
        self::assertIsArray($data);
    }
}
