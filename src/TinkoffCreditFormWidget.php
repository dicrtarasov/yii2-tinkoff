<?php
/**
 * @author Igor A Tarasov <develop@dicr.org>, http://dicr.org
 * @version 01.07.20 05:53:12
 */

declare(strict_types = 1);
namespace dicr\tinkoff;

use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\Html;
use function array_map;
use function array_reduce;
use function array_values;
use function is_array;
use function is_string;
use function ob_get_clean;
use function trim;

/**
 * Виджет формы заявки на кредит в банк Тинькофф.
 *
 * @property float $sum Сумма всех позиций заказа в рублях. Число с двумя десятичными знаками и разделителем точкой.
 * @property-read bool $isValid параметры товаров подходят для кредита
 *
 * @link https://tinkoff.loans/api/v1/static/documents/templates/Lightweight_Integration_Guide.RU.v1.3.pdf
 * @noinspection PhpUnused
 */
class TinkoffCreditFormWidget extends Widget
{
    /** @var string адрес отправки данных формы */
    public const ACTION_URL = 'https://loans.tinkoff.ru/api/partners/v1/lightweight/create';

    /** @var string адрес для тестов */
    public const ACTION_TEST_URL = 'https://loans-qa.tcsbank.ru/api/partners/v1/lightweight/create';

    /** @var string shipId для тестов */
    public const SHOP_TEST_ID = 'test_online';

    /** @var string showcaseId для тестов */
    public const SHOWCASE_TEST_ID = self::SHOP_TEST_ID;

    /** @var string код услуги "Купить в кредит" */
    public const PROMO_DEFAULT = 'credit';

    /** @var string код услуги "Купить в рассрочку 0-0-3" */
    public const PROMO_INSTALLMENT = 'installment_0_0_3';

    /** @var string promoCode для тестов */
    public const PROMO_TEST = self::PROMO_DEFAULT;

    /** @var int минимальная сумма товаров для кредита */
    public const SUM_MIN = 3000;

    /** @var string адрес для отправки запроса */
    public $url = self::ACTION_URL;

    /** @var string [50] уникальный идентификатор магазина, выдается банком при подключении. */
    public $shopId;

    /**
     * @var string|null [20] идентификатор витрины магазина.
     * Витрины —это различные сайты, зарегистрированные на одно юридическое лицо.
     * В случае единственной витрины можно не указывать.
     */
    public $showcaseId;

    /** @var string|null [20] указывается в случае, если на товар распространяется акции (например, рассрочки). */
    public $promoCode = self::PROMO_DEFAULT;

    /**
     * @var string|null [64] Номер заказа в системе магазина.
     * Если его не передать, будет присвоен автоматически сгенерированный на стороне банка номер заказа.
     */
    public $orderNumber;

    /** @var string|null [64] Идентификатор клиента в системе магазина. */
    public $customerNumber;

    /** @var string|null [100] адрес электронной почты клиента */
    public $customerEmail;

    /**
     * @var string|null [20] Номер мобильного телефона клиента.
     * Формат: 10 или 11 цифр номера с любым форматированием: со скобками, пробелами, дефисами и т.п.
     * Например, +7ХХХХХХХХХХ; 7(ХХХ)ХХХХХХХ; 8-ХХХ-ХХХ-ХХ-ХХ; (ХХХ)ХХХ-ХХ-ХХ.
     */
    public $customerPhone;

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

    /** @var array опции тега виджета */
    public $options = [];

    /** @var bool авто-отправка формы при загрузке страницы */
    public $autoSubmit = false;

    /** @var string|null событие Метрики при отправке формы */
    public $ym;

    /** @var bool|string отображать кнопку */
    public $button;

    /**
     * @inheritDoc
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();

        $this->shopId = trim((string)$this->shopId);
        if (empty($this->shopId)) {
            throw new InvalidArgumentException('shopId');
        }

        if (isset($this->items)) {
            if (! is_array($this->items)) {
                throw new InvalidConfigException('items');
            }

            $this->items = array_map(static function($item) {
                if (! is_array($item)) {
                    throw new InvalidConfigException('items[i]');
                }

                $item['name'] = trim((string)($item['name'] ?? ''));
                if (empty($item['name'])) {
                    throw new InvalidConfigException('item.name');
                }

                $item['price'] = (float)($item['price'] ?? 0);
                if ($item['price'] <= 0) {
                    throw new InvalidConfigException('item.price');
                }

                $item['quantity'] = (int)($item['quantity'] ?? 0);
                if ($item['quantity'] < 1) {
                    throw new InvalidConfigException('item.quantity');
                }

                return $item;
            }, $this->items);
        }

        if ($this->sum <= 0) {
            throw new InvalidConfigException('sum или items должны быть установлены');
        }

        $this->options = array_merge([
            'id' => $this->id,
            'method' => 'post',
            'action' => $this->url,
            'data-promo' => $this->promoCode
        ], $this->options);

        Html::addCssClass($this->options, 'dicr-tinkoff-credit-form');
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
                return $sum + ($item['price'] * $item['quantity']);
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

        $this->sum = $sum;
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
     */
    protected function data()
    {
        $data = [
            'shopId' => (string)$this->shopId,
            'sum' => sprintf("%.2f", $this->sum)
        ];

        foreach ([
            'showcaseId', 'promoCode', 'orderNumber', 'customerNumber', 'customerEmail', 'customerPhone'
        ] as $field) {
            if (! empty($this->{$field})) {
                $data[$field] = (string)$this->{$field};
            }
        }

        if (! empty($this->items)) {
            foreach (array_values($this->items) as $pos => $item) {
                $data['itemName_' . $pos] = (string)$item['name'];
                $data['itemPrice_' . $pos] = sprintf("%.2f", $item['price']);
                $data['itemQuantity_' . $pos] = sprintf("%d", $item['quantity']);

                if (! empty($item['vendorCode'])) {
                    $data['itemVendorCode_' . $pos] = (string)$item['vendorCode'];
                }

                if (! empty($item['category'])) {
                    $data['itemCategory_' . $pos] = (string)$item['category'];
                }
            }
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        if (! empty($this->ym)) {
            ob_start();
            ?>
            $("#<?= $this->id ?>").on("submit", function() {
            if (window.Ya && window.Ya._metrika && window.Ya._metrika.counters) {
            Object.values(window.Ya._metrika.counters).forEach(function(counter) {
            counter.reachGoal("<?= $this->ym ?>");
            });
            }
            });
            <?php
            $this->view->registerJs(ob_get_clean());
        }

        if (! empty($this->autoSubmit)) {
            $this->view->registerJs('$("#' . $this->id . '").trigger("submit");');
        }

        ob_start();
        echo Html::beginTag('form', $this->options);

        foreach ($this->data() as $name => $val) {
            echo Html::input('hidden', $name, $val);
        }

        if (! empty($this->button)) {
            echo is_string($this->button) ? $this->button : Html::submitButton('Отправить', [
                'disabled' => ! $this->isValid
            ]);
        }

        echo Html::endTag('form');
        return ob_get_clean();
    }
}
