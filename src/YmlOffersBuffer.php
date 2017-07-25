<?php

namespace Drupal\commerce_yandex_market;

use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\file\Entity\File;

class YmlOffersBuffer extends XmlOffersBuffer {

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
      'url',
      'price',
      'currencyId',
      'categoryId',
      'picture',
      'typePrefix',
      'vendor',
      'vendorCode',
      'model',
      'description',
      'sales_notes',
      'weight',
    ];
    $offer = [
      'id' => intval($data['attrs']['ID'], 10),
      'type' => $data['attrs']['TYPE'],
      'available' => $data['attrs']['AVAILABLE'],
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
      $variation = count($variation_ids) ? ProductVariation::load(reset($variation_ids)) : ProductVariation::create([
        'type' => 'default',
        'sku' => $offer['id'],
      ]);
      $product = $variation->getProduct() ?: Product::create([
        'id' => $offer['id'],
        'type' => 'default',
        'variations' => [],
      ]);

      $uri = 'public://product_' . $offer['id'] . '.jpg';
      $path = \Drupal::service('file_system')->realpath($uri);
      if (file_exists($path) || YandexMarketLoader::saveFile($offer['picture'], $path)) {
        $files = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->loadByProperties(['uri' => $uri]);
        $file = reset($files) ?: File::create([
          'uri' => $uri,
        ]);
        $file->save();
        $product->set("field_picture", [
          'target_id' => $file->id(),
          'alt' => 'Alt text',
          'title' => 'Title',
        ]);
      }

      $variation->setTitle($offer['model']);
      $price = new Price($offer['price'], 'RUB');

      $variation->setPrice($price);
      $variation->setActive($offer['available']);
      $variation->save();
      $product->setStoreIds([1]);
      $product->setTitle($offer['model']);
      $product->set("body", [
        'value' => $offer['description'],
        'format' => 'basic_html',
      ]);
      $product->setPublished(TRUE);
      $product->setVariations([$variation]);
      $product->set("field_url", $offer['url']);
      $product->set("field_sales_notes", [
        'value' => $offer['sales_notes'],
        'format' => 'basic_html',
      ]);
      $product->set("field_weight", $offer['weight']);
      /** @var \Drupal\taxonomy\Entity\Term $term */
      $term = $this->terms[intval($offer['categoryId'])];
      if ($term) {
        $product->set("field_category", [['target_id' => $term->id()]]);
      }
      $product->save();
      drupal_set_message($product->id());
    }
  }
}