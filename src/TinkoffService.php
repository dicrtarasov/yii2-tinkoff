<?php
/**
 * @author Igor A Tarasov <develop@dicr.org>, http://dicr.org
 * @version 09.07.20 05:56:58
 */

declare(strict_types = 1);
namespace dicr\tinkoff;

use yii\base\Component;
use function array_merge;

/**
 * Виджет формы заявки на кредит в банк Тинькофф.
 *
 * @property float $sum Сумма всех позиций заказа в рублях. Число с двумя десятичными знаками и разделителем точкой.
 * @property-read bool $isValid параметры товаров подходят для кредита
 *
 * @link https://tinkoff.loans/api/v1/static/documents/templates/Lightweight_Integration_Guide.RU.v1.3.pdf
 * @noinspection PhpUnused
 */
class TinkoffService extends Component
{
    /** @var array конфиг по-умолчанию запроса LightweightRequest */
    public $lightweightConfig = [];

    /**
     * Создает LightWeight-запрос с параметрами по-умолчанию.
     *
     * @param array $config
     * @return LightweightRequest
     */
    public function lightweightRequest(array $config)
    {
        return new LightweightRequest(array_merge($this->lightweightConfig ?: [], $config));
    }
}
