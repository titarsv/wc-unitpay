<?php 
/*
  Plugin Name: UnitPay Payment Gateway
  Plugin URI: 
  Description: Allows you to use UnitPay payment gateway with the WooCommerce plugin.
  Version: 0.7
*/

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('plugins_loaded', 'woocommerce_unitpay_api', 0);
function woocommerce_unitpay_api(){
	if (!class_exists('WC_Payment_Gateway'))
		return;
	if(class_exists('WC_UNITPAY_API'))
		return;
  if(!is_user_logged_in() || get_current_user_id() > 1)
    return;

  include ('UnitPay.php');

  class WC_UNITPAY_API extends WC_Payment_Gateway
  {
    public function __construct()
    {
      $plugin_dir = plugin_dir_url(__FILE__);

      global $woocommerce;

      $this->id = 'unitpay_api';
      $this->icon = apply_filters('woocommerce_unitpay_icon', '' . $plugin_dir . '/img/unitpay.png');

      $this->init_form_fields();
      $this->init_settings();

      $this->title = $this->get_option('title');
      $this->description = $this->get_option('description');
      $this->url = $this->get_option('url');
      $this->secret = $this->get_option('secret');
      $this->projectId = $this->get_option('projectId');

      add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

      add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_ipn_response'));

      wp_enqueue_style('unitpay', $plugin_dir . 'unitpay.css');
    }

    public function admin_options()
    {
      ?>
      <h3><?php _e('UnitPay', 'woocommerce'); ?></h3>
      <p><?php _e('Настройка приема электронных платежей через UnitPay.', 'woocommerce'); ?></p>

      <table class="form-table">

        <?php
        $this->generate_settings_html();
        ?>
      </table>
    <?php
    }

    function init_form_fields()
    {
      $this->form_fields = array(
        'enabled' => array(
          'title' => __('Включить/Выключить', 'woocommerce'),
          'type' => 'checkbox',
          'label' => __('Включен', 'woocommerce'),
          'default' => 'yes'
        ),
        'title' => array(
          'title' => __('Название', 'woocommerce'),
          'type' => 'text',
          'description' => __('Это название, которое пользователь видит во время проверки.', 'woocommerce'),
          'default' => __('UintPay', 'woocommerce')
        ),
        'projectId' => array(
          'title' => __('ID', 'woocommerce'),
          'type' => 'text',
          'description' => __('ID вашего проекта в системе Unitpay.', 'woocommerce'),
          'default' => __('', 'woocommerce')
        ),
        'secret' => array(
          'title' => __('Секретный ключ', 'woocommerce'),
          'type' => 'text',
          'description' => __('Секретный ключ.', 'woocommerce'),
          'default' => __('', 'woocommerce')
        ),
        'url' => array(
          'title' => __('Url', 'woocommerce'),
          'type' => 'text',
          'description' => __('URL вашей платежной форм.', 'woocommerce'),
          'default' => __('https://unitpay.ru/pay/demo', 'woocommerce')
        ),
        'description' => array(
          'title' => __('Description', 'woocommerce'),
          'type' => 'textarea',
          'description' => __('Описанием метода оплаты которое клиент будет видеть на вашем сайте.', 'woocommerce'),
          'default' => 'Оплата с помощью UnitPay.'
        ),
      );
    }

    function payment_fields()
    {
      if ($this->description) {
        //echo wpautop(wptexturize($this->description));
        $plugin_dir = plugin_dir_url(__FILE__);
        echo '<p>' . __(wpautop(wptexturize($this->description))) . '</p>';

        $allowedMethods = array(
          array('value' => 'mc', 'type' => 'cards', 'title' => 'Мобильный платеж', 'image' => $plugin_dir . '/img/mc.png', 'selected' => false),
          array('value' => 'sms', 'type' => 'cards', 'title' => 'SMS-оплата', 'image' => $plugin_dir . '/img/sms.png', 'selected' => false),
          array('value' => 'card', 'type' => 'cards', 'title' => 'Пластиковые карты', 'image' => $plugin_dir . '/img/card.png', 'selected' => true),
          array('value' => 'yandex', 'type' => 'cards', 'title' => 'Яндекс.Деньги', 'image' => $plugin_dir . '/img/yandex.png', 'selected' => false),
          array('value' => 'qiwi', 'type' => 'cards', 'title' => 'Qiwi', 'image' => $plugin_dir . '/img/qiwi.png', 'selected' => false),
          array('value' => 'paypal', 'type' => 'cards', 'title' => 'PayPal', 'image' => $plugin_dir . '/img/paypal.png', 'selected' => false),
          array('value' => 'alfaClick', 'type' => 'cards', 'title' => 'Альфа-Клик', 'image' => $plugin_dir . '/img/alfaClick.png', 'selected' => false),
          array('value' => 'webmoney', 'type' => 'cards', 'title' => 'WebMoney', 'image' => $plugin_dir . '/img/wm.png', 'selected' => false),
          array('value' => 'liqpay', 'type' => 'cards', 'title' => 'LiqPay', 'image' => $plugin_dir . '/img/liqpay.png', 'selected' => false),
          array('value' => 'cash', 'type' => 'cards', 'title' => 'Наличные', 'image' => $plugin_dir . '/img/cash.png', 'selected' => false)
        );
        ?>

        <ul class="unitpay-form-list" id="payment_form_<?php echo $this->id; ?>">
          <li>
            <div id="unitpay_wrapper_<?php echo $this->id ?>" class="input-box eabi_unitpay_select">
              <select name="PRESELECTED_METHOD_<?php echo $this->id ?>" id="unitpay_selector_<?php echo $this->id ?>">
                <option value=""> -- please select --</option>
                <?php foreach ($allowedMethods as $allowedMethod): ?>
                  <option value="<?php echo $allowedMethod['value']; ?>"
                          data-type="<?php echo $allowedMethod['type']; ?>"
                    <?php
                    if ($allowedMethod['image']) {
                      echo 'data-image="' . $allowedMethod['image'] . '"';
                    }
                    ?>
                    <?php
                    if ($allowedMethod['selected']) {
                      echo ' selected="selected"';
                    }
                    ?>
                    ><?php echo htmlspecialchars($allowedMethod['title']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </li>
        </ul>

        <script type="text/javascript">
          /* <![CDATA[ */

          (function () {
            function eabi_imageselector(wrapperDom, selectDom, allowModalOpen, autoClickSelected, initParams, checkoutJsUrl, ulCssClassName, $) {
              /*get the elements from the selectDom*/
              var selectOptions = $(selectDom).find('option'),
                listElements = [],
                ul = $('<ul>'),
                elToClick;

              selectOptions.each(function (index, elem) {
                var li = $('<li>'),
                  a = $('<a>'),
                  img = $('<img>'),
                  title = $('<span>'),
                  item = $(elem),
                  id = 'Unitpay_' + item.attr('value');

                if (item.attr('value')) {
                  a.attr('href', '#');
                  a.attr('id', id);
                  a.attr('title', item.text());

                  a.attr('onclick', 'jQuery(\'' + wrapperDom + '\').find(\'a\').each(function(index, item) { jQuery(item).removeClass(\'selected\'); }); jQuery(\'' + selectDom + '\').val(\'' + item.attr('value') + '\'); jQuery(this).addClass(\'selected\'); return false;');
                  if (item.attr('data-type') === 'cards' && allowModalOpen) {
                    a.attr('onclick', 'jQuery(\'' + wrapperDom + '\').select(\'a\').each(function(index, item) { jQuery(item).removeClass(\'selected\');}); jQuery(\'' + selectDom + '\').val(\'' + item.attr('value') + '\'); jQuery(this).addClass(\'selected\'); Unitpay.Checkout.showModal(); return false;');
                  }

                  if (item.attr('selected') && item.attr('selected') === 'selected') {
                    a.addClass('selected');
                    if (autoClickSelected) {
                      elToClick = a;
                    }
                  }

                  img.attr('alt', a.attr('title'));
                  if (item.attr('data-image')) {
                    img.attr('src', item.attr('data-image'));
                    a.append(img);

                  } else {
                    title.html(a.attr('title'));
                    a.append(title);
                  }
                  li.append(a);
                  listElements.push(li);
                }
                $.each(listElements, function (index, item) {
                  ul.append(item);
                });
                if (ulCssClassName) {
                  ul.addClass(ulCssClassName);
                }
                $(wrapperDom).append(ul);

              });
              $(selectDom).hide();

              if (initParams) {
                if (!window.Unitpay) {
                  (function () {
                    var js = $('<script>');
                    js.attr('type', 'text/javascript');
                    js.attr('src', checkoutJsUrl);
                    $('body').append(js);
                  })();
                }
                if (window.Unitpay) {
                  if (!window['eabi_Unitpay_js_inited']) {
                    Unitpay.Checkout.initialize(initParams);
                    Unitpay.Checkout.renderButton();
                    window['eabi_Unitpay_js_inited'] = true;
                  }
                  Unitpay.Checkout.extendOptions(initParams, 'init');
                }

              }

              if (elToClick) {
                elToClick.click();
              }


            }

            eabi_imageselector('#unitpay_wrapper_<?php echo $this->id?>', '#unitpay_selector_<?php echo $this->id?>', <?php echo json_encode(false) ?>, <?php echo json_encode(false) ?>, <?php echo json_encode(false); ?>, <?php echo json_encode($this->get_option('checkout_js_url')); ?>, <?php echo json_encode($this->_getUlCssClassName()); ?>, jQuery);

          })();

          /* ]]> */
        </script>

      <?php

      }
    }

    protected function _getUlCssClassName()
    {
      if ($this->get_option('method_logo_size') == 'small') {
        return 'unitpay_small';
      }
      return false;
    }

    public function generate_form($order_id)
    {
      global $woocommerce;

      $order = new WC_Order($order_id);

      $paymentMethodCode = get_post_meta($order_id, '_eabi_unitpay_preselected_method', true);

      $unitPay = new UnitPay($this->secret);

      $params = array(
        'account' => $order_id,
        'desc' => 'Оплата заказа #' . $order_id,
        'sum' => $order->get_total(),
        'paymentType' => $paymentMethodCode,
        'currency' => get_woocommerce_currency(),
        'projectId' => $this->projectId,
      );

      if ($paymentMethodCode == 'webmoney') {
        $wmOptions = array(
          'RUB' => 'WMR',
          'EUR' => 'WME',
          'USD' => 'WMZ',
          'UAH' => 'WMU'
        );
        $params['purseType'] = isset($wmOptions[$params['currency']]) ? $wmOptions[$params['currency']] : '';
      } elseif (in_array($paymentMethodCode, array('qiwi', 'sms', 'mc', 'alfaClick'))) {
        $params['phone'] = get_post_meta($order_id, '_billing_phone', true);
        if ($paymentMethodCode == 'sms') {
          $params['operator'] = get_post_meta($order_id, '_phone_operator', true);
        }
      }

      $response = $unitPay->api('initPayment', $params);

      if (isset($response->result->type) && $response->result->type == 'redirect') {
        $redirectUrl = $response->result->redirectUrl;
        // Payment ID in Unitpay
        $paymentId = $response->result->paymentId;
        update_post_meta($order_id, '_unitpay_paymentId', $paymentId);

        return
          '<form action="' . esc_url($redirectUrl) . '" method="GET" id="unitpay_payment_form">' . "\n" .
          '<button class="btn btn-xm" style="display: block; margin: 0 auto 20px;">' . __('Pay', 'greedy_dwarf') . '</button><input type="hidden" class="button alt" id="submit_unitpay_payment_form" value="' . __('Pay', 'greedy_dwarf') . '" /> ' . "\n" .
          '</form>';
      } elseif (isset($response->result->type) && $response->result->type == 'invoice') {
        // Url on receipt page in Unitpay
        $receiptUrl = $response->result->receiptUrl;
        // Payment ID in Unitpay
        $paymentId = $response->result->paymentId;
        update_post_meta($order_id, '_unitpay_paymentId', $paymentId);
        // Invoice Id in Payment Gate
        $invoiceId = $response->result->invoiceId;
        update_post_meta($order_id, '_unitpay_invoiceId', $invoiceId);
        // User redirect
        return
          '<form action="' . esc_url($receiptUrl) . '" method="GET" id="unitpay_payment_form">' . "\n" .
          '<button class="btn btn-xm" style="display: block; margin: 0 auto 20px;">' . __('Pay', 'greedy_dwarf') . '</button><input type="hidden" class="button alt" id="submit_unitpay_payment_form" value="' . __('Pay', 'greedy_dwarf') . '" /> ' . "\n" .
          '</form>';
      }

      $args = array(
        'account' => $order_id,
        'sum' => $order->order_total,
        'desc' => 'Оплата заказа #' . $order_id,
      );

      if ($paymentMethodCode)
        $args['paymentType'] = $paymentMethodCode;

      apply_filters('woocommerce_unitpay_args', $args);

      $args_array = array();

      foreach ($args as $key => $value) {
        $args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
      }

      return
        '<form action="' . esc_url($this->url) . '" method="POST" id="unitpay_payment_form">' . "\n" .
        implode("\n", $args_array) .
        '<button class="btn btn-xm" style="display: block; margin: 0 auto 20px;">' . __('Pay', 'greedy_dwarf') . '</button><input type="hidden" class="button alt" id="submit_unitpay_payment_form" value="' . __('Pay', 'greedy_dwarf') . '" /> ' . "\n" .
        '</form>';
    }

    function check_ipn_response()
    {

      $unitPay = new UnitPay($this->secret);

      try {
        // Validate request (check ip address, signature and etc)
        $unitPay->checkHandlerRequest();

        list($method, $params) = array($_GET['method'], $_GET['params']);

        $order_id = $params['account'];
        $order = new WC_Order($order_id);

        // Very important! Validate request with your order data, before complete order
        if (
          $params['orderSum'] != $order->get_total() ||
          $params['orderCurrency'] != $order->get_order_currency() ||
          $params['account'] != $order_id ||
          $params['projectId'] != $this->projectId
        ) {
          // logging data and throw exception
          throw new InvalidArgumentException('Order validation Error!');
        }

        switch ($method) {
          // Just check order (check server status, check order in DB and etc)
          case 'check':
            print $unitPay->getSuccessHandlerResponse('Check Success. Ready to pay.');
            break;
          // Method Pay means that the money received
          case 'pay':
            // Please complete order
            $order->add_order_note(__('Платеж успешно завершен.', 'woocommerce'));
            $order->payment_complete();
            print $unitPay->getSuccessHandlerResponse('Pay Success');
            break;
          // Method Error means that an error has occurred.
          case 'error':
            // Please log error text.
            print $unitPay->getSuccessHandlerResponse('Error logged');
            break;
          // Method Refund means that the money returned to the client
          case 'refund':
            // Please cancel the order
            print $unitPay->getSuccessHandlerResponse('Order canceled');
            break;
        }
        // Oops! Something went wrong.
      } catch (Exception $e) {
        print $unitPay->getErrorHandlerResponse($e->getMessage());
      }

      die();
    }

    function process_payment($order_id)
    {
      $order = new WC_Order($order_id);

      $selected = isset($_POST['PRESELECTED_METHOD_' . $this->id]) ? sanitize_text_field($_POST['PRESELECTED_METHOD_' . $this->id]) : false;
      update_post_meta($order_id, '_eabi_unitpay_preselected_method', $selected);

      return array(
        'result' => 'success',
        'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
      );
    }

    function receipt_page($order)
    {
      echo '<p class="thanks">' . __('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'woocommerce') . '</p>';
      echo $this->generate_form($order);
    }
  }

function add_unitpay_api_gateway($methods){
	$methods[] = 'WC_UNITPAY_API';
	return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_unitpay_api_gateway');
}