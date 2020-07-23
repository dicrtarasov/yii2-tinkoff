<?php
/**
 * @author Igor A Tarasov <develop@dicr.org>, http://dicr.org
 * @version 24.07.20 01:26:24
 */

declare(strict_types = 1);
namespace dicr\tinkoff;

use dicr\validate\PhoneValidator;
use dicr\validate\ValidateException;
use yii\base\DynamicModel;
use yii\base\InvalidArgumentException;
use yii\base\Model;
use function array_filter;
use function array_keys;
use function array_map;
use function array_reduce;
use function array_values;
use function sprintf;
use function trim;

/**
 * Lightweight-запрос на рассрочку.
 *
 * Обязательными являются только shopId и sum.
 *
 * @property float $sum Сумма всех позиций заказа в рублях. Число с двумя десятичными знаками и разделителем точкой.
 * @property-read bool $isValid параметры товаров подходят для кредита
 * @property-read string[] $data данные для формы запроса
 *
 * @link https://tinkoff.loans/api/v1/static/documents/templates/Lightweight_Integration_Guide.RU.v1.3.pdf
 */
class LightweightRequest extends Model
{
    /** @var string адрес отправки данных формы */
    public const ACTION_URL = 'https://loans.tinkoff.ru/api/partners/v1/lightweight/create';

    /** @var string адрес для тестов */
    public const TEST_URL = 'https://loans-qa.tcsbank.ru/api/partners/v1/lightweight/create';

    /** @var string shipId для тестов */
    public const TEST_SHOP_ID = 'test_online';

    /** @var string showcaseId для тестов */
    public const TEST_SHOWCASE_ID = self::TEST_SHOP_ID;

    /** @var string код услуги "Купить в кредит" */
    public const PROMO_DEFAULT = 'default';

    /** @var string код услуги "Купить в рассрочку 0-0-3" */
    public const PROMO_INSTALLMENT = 'installment_0_0_3';

    /** @var string promoCode для тестов */
    public const TEST_PROMO = self::PROMO_DEFAULT;

    /** @var int минимальная сумма товаров для кредита */
    public const SUM_MIN = 3000;

    /** @var string URL запроса */
    public $url = self::ACTION_URL;

    /** @var string [50] уникальный идентификатор магазина, выдается банком при подключении. */
    public $shopId;

    /**
     * @var string|null [20] идентификатор витрины магазина.
     * Витрины — это различные сайты, зарегистрированные на одно юридическое лицо.
     * В случае единственной витрины можно не указывать.
     */
    public $showcaseId;

    /** @var string|null [20] указывается в случае, если на товар распространяется акции (например, рассрочки). */
    public $promoCode;

    /**
     * @var string|null [64] Номер заказа в системе магазина.
     * Если его не передать, будет присвоен автоматически сгенерированный на стороне банка номер заказа.
     */
    public $orderNumber;

    /**
     * @var array[]|null товары в заказе.
     * - string $name [255] название товара
     * - int $quantity Количество единиц товара.
     * - float $price Стоимость единицы товара в рублях. Число с двумя десятичными знаками и разделителем точкой.
     * - string|null $vendorCode [64] Артикул товара (необязательно).
     * - string|null $category [255] Категория товара: мебель, электроника, бытовая техника (необязательно).
     *
     * Для заказов на сумму свыше 50 000 рублей обязательно передавайте состав заказа.
     */
    public $items;

    /** @var string|null [64] Идентификатор клиента в системе магазина. */
    public $customerNumber;

    /**
     * @var string|null [20] Номер мобильного телефона клиента.
     * Формат: 10 или 11 цифр номера с любым форматированием: со скобками, пробелами, дефисами и т.п.
     * Например, +7ХХХХХХХХХХ; 7(ХХХ)ХХХХХХХ; 8-ХХХ-ХХХ-ХХ-ХХ; (ХХХ)ХХХ-ХХ-ХХ.
     */
    public $customerPhone;

    /** @var string|null [100] адрес электронной почты клиента */
    public $customerEmail;

    /**
     * @inheritDoc
     */
    public function attributeLabels()
    {
        return [
            'shopId' => 'Магазин',
            'showcaseId' => 'Витрина (сайт)',
            'promoCode' => 'Тип услуги',
            'orderNumber' => '№ заказа',
            'items' => 'Товары',
            'sum' => 'Сумма',
            'customerNumber' => 'ID покупателя',
            'customerPhone' => 'Телефон покупателя',
            'customerEmail' => 'E-mail покупателя',
            'test' => 'Режим тестирования'
        ];
    }

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return [
            ['shopId', 'trim'],
            ['shopId', 'required'],
            ['shopId', 'string', 'max' => 50],

            ['showcaseId', 'trim'],
            ['showcaseId', 'default'],
            ['showcaseId', 'string', 'max' => 50],

            ['promoCode', 'trim'],
            ['promoCode', 'default'],
            ['promoCode', 'string', 'max' => 20],

            ['orderNumber', 'trim'],
            ['orderNumber', 'default'],
            ['orderNumber', 'string', 'max' => 64],

            ['items', 'default'],
            ['items', function($attribute) {
                foreach ($this->items ?: [] as &$item) {
                    $model = (new DynamicModel([
                        'name', 'price', 'quantity', 'vendorCode', 'category'
                    ]))
                        ->addRule('name', 'trim')
                        ->addRule('name', 'required')
                        ->addRule('name', 'string', ['max' => 255])
                        ->addRule('price', 'required')
                        ->addRule('price', 'number', ['min' => 0.01])
                        ->addRule('price', 'filter', ['filter' => 'floatval'])
                        ->addRule('quantity', 'required')
                        ->addRule('quantity', 'integer', ['min' => 1])
                        ->addRule('vendorCode', 'trim')
                        ->addRule('vendorCode', 'default')
                        ->addRule('vendorCode', 'string', ['max' => 64])
                        ->addRule('category', 'default')
                        ->addRule('category', 'string', ['max' => 255]);

                    $model->attributes = $item;
                    $model->validate();

                    if ($model->hasErrors()) {
                        $errorAttr = array_keys($model->firstErrors)[0];
                        $this->addError($attribute, $errorAttr . ': ' . $model->getFirstError($errorAttr));
                    } else {
                        $item = array_filter($model->attributes, static function($val) {
                            return $val !== null;
                        });
                    }
                }
            }, 'skipOnEmpty' => true],

            ['sum', 'required'],
            ['sum', 'number', 'min' => self::SUM_MIN],
            ['sum', 'filter', 'filter' => 'floatval'],

            ['customerNumber', 'default'],
            ['customerNumber', 'string', 'max' => 64],

            ['customerPhone', 'default'],
            ['customerPhone', PhoneValidator::class],
            ['customerPhone', 'filter', 'filter' => static function($phone) {
                return $phone ? PhoneValidator::format($phone) : null;
            }, 'skipOnEmpty' => true],

            ['customerEmail', 'default'],
            ['customerEmail', 'email'],
            ['customerEmail', 'string', 'max' => 100],
        ];
    }

    /** @var float сумма товаров */
    private $_sum;

    /**
     * Сумма товаров.
     *
     * @return float
     */
    public function getSum()
    {
        if (! isset($this->_sum)) {
            $this->_sum = array_reduce($this->items ?: [], static function(float $sum, array $item) {
                $price = (float)($item['price'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 0);
                return $sum + $price * $quantity;
            }, 0);
        }

        return $this->_sum;
    }

    /**
     * Установить сумму
     *
     * @param float $sum
     */
    public function setSum(float $sum)
    {
        if ($sum <= 0) {
            throw new InvalidArgumentException('sum');
        }

        $this->_sum = $sum;
    }

    /**
     * Проверяет удовлетворяют ли параметры товаров для кредита.
     *
     * @return bool
     */
    public function getIsValid()
    {
        return $this->sum >= self::SUM_MIN;
    }

    /**
     * Возвращает данные формы.
     *
     * @return string[]
     * @throws ValidateException
     */
    public function getData()
    {
        if (! $this->validate()) {
            throw new ValidateException($this);
        }

        $data = $this->toArray([
            'shopId',
            'showcaseId',
            'promoCode',
            'orderNumber',
            'customerNumber',
            'customerPhone',
            'customerEmail'
        ]);

        $data['sum'] = sprintf('%.2f', $this->sum);

        if (! empty($this->items)) {
            foreach (array_values($this->items) as $pos => $item) {
                $data['itemName_' . $pos] = $item['name'];
                $data['itemPrice_' . $pos] = sprintf('%.2f', $item['price']);
                $data['itemQuantity_' . $pos] = $item['quantity'];

                if (! empty($item['vendorCode'])) {
                    $data['itemVendorCode_' . $pos] = $item['vendorCode'];
                }

                if (! empty($item['category'])) {
                    $data['itemCategory_' . $pos] = $item['category'];
                }
            }
        }

        $data = array_map(static function($val) {
            return trim((string)$val);
        }, $data);

        return array_filter($data, static function($val) {
            return $val !== null && $val !== '';
        });
    }
}
