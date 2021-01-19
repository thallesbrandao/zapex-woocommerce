<?php

/**
 * Zapex
 *
 * @package Zapex/Classes
 * @since   1.0.0
 * @version 1.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugins main class.
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    function zapex_delivery_init() {
        if ( ! class_exists( 'WC_Zapex_Delivery_Method' ) ) {
            class WC_Zapex_Delivery_Method extends WC_Shipping_Method {

                private $args = array(
                    'timeout' => 60,
                    'redirection' => 5,
                    'blocking' => true,
                    'sslverify' => false,
                    'headers' => array(
                      'Content-Type' => 'application/json'
                    )
                );


                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct($instance_id = 0) {
                    $this->id                 = 'zapex_delivery'; // Id for your shipping method. Should be uunique.
                    $this->instance_id  = absint( $instance_id );
                    $this->method_title = __('Zapex Transportadora', 'zapex');
                    $this->method_description = __('Entregas feitas por Zapex', 'zapex');

                    $this->init();

                    $this->token = !empty($this->settings['token']) ? $this->settings['token'] : ''; // This can be added as an setting but for this example its forced.
                    $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
                    $this->cep = isset($this->settings['cep']) ? $this->settings['cep'] : '';

                }

                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init() {
                    // Load the settings API
                    $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
                    $this->init_settings(); // This is part of the settings API. Loads settings you previously init.

                    // Save settings in admin if you have any defined
                    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                }

                function init_form_fields() {

                    $this->form_fields = array(

                        'enabled' => array(
                            'title'       => __( 'Habilitar', 'zapex' ),
                            'type'        => 'checkbox',
                            'description' => __( 'Habilite este método de envio.', 'zapex' ),
                            'default'     => 'yes'
                        ),

                        'token' => array(
                            'title'       => __( 'Token', 'zapex' ),
                            'type'        => 'text',
                            'description' => __( 'Token fornecido pela ZAPEX', 'zapex' ),
                            'default'     => __( '', 'zapex' )
                        ),

                        'cep' => array(
                            'title'       => __( 'CEP', 'zapex' ),
                            'type'        => 'number',
                            'description' => __( 'Informe o CEP de onde os produtos serão enviados', 'zapex' ),
                            'default'     => ''
                        ),

                    );

                }

                /**
                 * This function is used to calculate the shipping cost.
                 * Within this function we can check for weights, dimensions and other parameters in package.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping( $package = array() )
                {
                    $total = $this->get_total_volumes($package);
                    $weight = $this->get_order_total_weight($package);
                    $postcode = $package['destination']['postcode'];
                    $contents_cost = $package['contents_cost'];

                    if($this->token AND $this->cep AND $this->enabled === 'yes' AND $postcode && $contents_cost && $total && $weight){
    
                        $uri        = "http://api.zapex.com.br/cotacaofrete/{$this->token}/{$this->cep}/{$postcode}/{$total}/{$weight}/{$contents_cost}";
                        $response   = $this->request_to_api( $uri, $this->args );

                        if ($response !== NULL) {  
                            foreach ($response->oObj as $value){
                                $this->add_rate(array('id' => $value->_id, 'label' => $value->Descricao . ' (Entrega em ' . $value->PrazoEntrega . ' dias)', 'cost' => $value->Valor));
                            }
                        }  
                    }else{
                        return;
                    }
                }


                /**
                 * @param $uri
                 * @param $args
                 * @return mixed|void
                 */
                private function request_to_api( $uri, $args )
                {
                    $response = wp_remote_post( $uri, $args );
                    if ( is_wp_error( $response ) || '200' != wp_remote_retrieve_response_code( $response )) {
                        return;
                    }
                    $body = json_decode( wp_remote_retrieve_body( $response ) );
                    if ( empty( $body ) )
                        return;
                    return $body;
                }


                /**
                 * @param $package
                 * @return int
                 */
                private function get_total_volumes( $package ) {
                    $total = 0;

                    foreach ( $package['contents'] as $item_id => $values ) {
                        $total += (int) $values['quantity'];
                    }

                    return $total;
                }


                /**
                 * Calculate the total wheight of all products of the order
                 * @param $package
                 * @return float|int
                 */
                private function get_order_total_weight( $package ) {

                    $total = 0;

                    foreach ( $package['contents'] as $item_id => $values )
                    {
                        $_product = $values['data'];
                        $_product_weight = (float) $_product->get_weight();
                        $total += $_product_weight * $values['quantity'];
                    }

                    $total = wc_get_weight( $total, 'kg' );

                    return $total;
                }


            }
        }
    }

    add_action( 'woocommerce_shipping_init', 'zapex_delivery_init' );

    function zapex_delivery_shipping_method( $methods ) {
        $methods['zapex_delivery'] = 'WC_Zapex_Delivery_Method';

        return $methods;
    }

    add_filter( 'woocommerce_shipping_methods', 'zapex_delivery_shipping_method' );
}