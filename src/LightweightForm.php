<?php
/**
 * @author Igor A Tarasov <develop@dicr.org>, http://dicr.org
 * @version 09.07.20 05:55:11
 */

declare(strict_types = 1);
namespace dicr\tinkoff;

use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\Html;
use function is_string;
use function ob_get_clean;

/**
 * Виджет Lightweight-форма заявки на кредит в банк Тинькофф.
 *
 * @noinspection PhpUnused
 */
class LightweightForm extends Widget
{
    /** @var LightweightRequest */
    public $request;

    /** @var bool|string отображать кнопку */
    public $button;

    /** @var array опции тега формы */
    public $options = [];

    /** @var bool авто-отправка формы при загрузке страницы */
    public $autoSubmit = false;

    /** @var string|null событие Метрики при отправке формы */
    public $ym;

    /**
     * @inheritDoc
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        if (! ($this->request instanceof LightweightRequest)) {
            throw new InvalidConfigException('request');
        }

        $this->options = array_merge([
            'method' => 'post',
            'action' => $this->request->url,
            'id' => $this->id,
            'data-promo' => $this->request->promoCode
        ], $this->options);

        Html::addCssClass($this->options, 'widget-dicr-tinkoff-lightweight');
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        ob_start();
        echo Html::beginTag('form', $this->options);

        foreach ($this->request->data as $name => $val) {
            echo Html::hiddenInput($name, $val);
        }

        if (! empty($this->button)) {
            echo is_string($this->button) ? $this->button : Html::submitButton('Отправить заявку', [
                'disabled' => ! $this->request->isValid
            ]);
        }

        echo Html::endTag('form');

        // отправка метрики при отправке формы. Так как форма может быть отображена на странице переадресации,
        // то никаких библиотек может быть недоступно.
        if (! empty($this->ym)) {
            ?>
            <!--suppress JSUnresolvedVariable, JSUnresolvedFunction -->
            <script>
                "use strict";

                window.document.getElementById("<?= $this->id ?>").onsubmit = function () {
                    if (window.Ya && window.Ya._metrika && window.Ya._metrika.counters) {
                        Object.values(window.Ya._metrika.counters).forEach(function (counter) {
                            counter.reachGoal("<?= $this->ym ?>");
                        });
                    }
                };
            </script>
            <?php
        }

        // авто-отправка формы на странице переадресации
        if (! empty($this->autoSubmit)) {
            ?>
            <script>
                "use strict";
                window.document.getElementById("<?= $this->id ?>").submit();
            </script>
            <?php
        }

        return ob_get_clean();
    }
}
