<?php

namespace Drupal\commerce_yandex_market;

use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\file\Entity\File;

class XmlOffersBuffer {

  public $buffer = [];

  public $models = [];

  protected $terms;

  function __construct() {
    $controller = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $terms = $controller->loadMultiple(\Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'categories')
      ->execute());
    /** @var \Drupal\taxonomy\Entity\Term $term */
    foreach ($terms as $term) {
      $termId = $term->get("field_id")->getValue();
      $this->terms[$termId] = $term;
    }
  }

  public function add($data) {
    $values = [];
    if ($data && array_key_exists('child', $data) && is_array($data['child'])) {
      foreach ($data['child'] as $i => $item) {
        $values[$item['tag']] = [
          'index' => $i,
          'item' => $item,
        ];
      }
    }
    $v = function ($key) use ($values) {
      return (array_key_exists($key, $values)) ? $values[$key]['item']['value'] : NULL;
    };
    $valuesNames = [
      'Код',
      'Ид',
      'vendorCode',
      'Бренд',
      'Name',
      'КоличествоНаРЦ',
      'КоличествоВФилиале',
      'Цена',
      'ЦенаРозница',
    ];
    $offer = [
      'id' => intval($data['attrs']['ID'], 10),
    ];
    foreach ($valuesNames as $name) {
      $offer[$name] = $v(strtoupper($name));
    }
    $this->buffer[$offer['id']] = $offer;
  }

  public function flush() {
    foreach ($this->buffer as $offer) {
      $variation_ids = \Drupal::entityQuery('commerce_product_variation')
        ->condition('sku', $offer['id'])
        ->execute();
      if (count($variation_ids)) {
        $variation = ProductVariation::load(reset($variation_ids));
        //        $product = $variation->getProduct();
        $variation->setTitle(print_r($offer, TRUE));
        $variation->setPrice(new Price($offer['ЦенаРозница'], 'RUB'));
        $variation->set('field_partner_price', new Price($offer['Цена'], 'RUB'));
        $variation->save();
        //$product->setStoreIds([1]);
        //      $product->setTitle($offer['Name']);
        //      $product->set("body", [
        //        'value' => print_r($offer, true),
        //        'format' => 'basic_html',
        //      ]);
        //$product->setPublished(TRUE);
        //$product->setVariations([$variation]);
        //$product->save();
        //drupal_set_message($product->id());
      }
    }
  }
}