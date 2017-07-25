<?php

namespace Drupal\commerce_yandex_market;

use Drupal\taxonomy\Entity\Term;

class YmlParserHandler extends XmlParserHandler {
  function newParserData(): XmlParserData {
    return new YmlParserData();
  }

  function character_data_handler($p, string $data) {
    $d = $this->getData((string)$p);
    if (!$d->alreadyUpdated) {
      if ($d->offer) {
        $v = trim($data);
        if ($v !== '') {
          $i = &$d->offer;
          foreach ($d->path as $item) {
            $i = &$i['child'][$item];
          }
          $i['value'] .= $v;
        }
      }
      if ($d->category) {
        $v = trim($data);
        if ($v !== '') {
          $i = &$d->category;
          foreach ($d->path as $item) {
            $i = &$i['child'][$item];
          }
          $i['value'] .= $v;
        }
      }
    }
  }

  function end_element_handler($p, string $name) {
    $d = $this->getData((string)$p);
    if (!$d->alreadyUpdated) {
      array_splice($d->tags, -1, 1);
      switch ($name) {
        case 'CATEGORIES':
          break;
        case 'OFFERS':
          break;
        case 'OFFER':
          $this->buffer->add($d->offer);
          $d->offer = NULL;
          break;
        case 'CATEGORY':
          $parent = NULL;
          if (array_key_exists('PARENTID', $d->category['attrs'])) {
            $parent_ids = \Drupal::entityQuery('taxonomy_term')
              ->condition('field_id', intval($d->category['attrs']['PARENTID']))
              ->execute();
            // guess that term always exists
            $parent = Term::load(reset($parent_ids));
          }
          $category_ids = \Drupal::entityQuery('taxonomy_term')
            ->condition('field_id', intval($d->category['attrs']['ID']))
            ->execute();
          $term = count($category_ids) ? Term::load(reset($category_ids)) : Term::create([
            'name' => $d->category['value'],
            'vid' => 'categories',
          ]);
          if ($parent) {
            /** @noinspection PhpUndefinedFieldInspection */
            $term->parent = [$parent->id()];
          }
          $term->set("field_id", intval($d->category['attrs']['ID']));
          $term->setName($d->category['value']);
          drupal_set_message($term->save());
          $d->category = NULL;
          break;
        default:
          if ($d->offer) {
            array_pop($d->path);
          }
          break;
      }
    }
  }

  function start_element_handler($p, string $name, array $attrs) {
    $d = $this->getData((string)$p);
    if (!$d->alreadyUpdated) {
      $d->tags[] = $name;
      switch ($name) {
        case 'YML_CATALOG':
          $this->date = date_create_from_format('Y-m-d H:i', $attrs['DATE']);
          $d->alreadyUpdated = $this->last_date == $this->date;
          echo "Already updated: " . var_export($d->alreadyUpdated, TRUE) . PHP_EOL;
          $store->set("field_yml_updated_at", [$this->date->format("Y-m-d H:i:s")]);
          $store->save();

          break;
        case 'CATEGORIES':
        case 'OFFERS':
          break;
        case 'OFFER':
          $d->offer = [
            'tag' => $name,
            'value' => '',
            'attrs' => $attrs,
            'child' => [],
          ];
          break;
        case 'CATEGORY':
          $d->category = [
            'tag' => $name,
            'value' => '',
            'attrs' => $attrs,
            'child' => [],
          ];
          break;
        default:
          if ($d->offer) {
            $i = &$d->offer;
            foreach ($d->path as $item) {
              $i = &$i['child'][$item];
            }
            $i['child'][] = [
              'tag' => $name,
              'value' => '',
              'attrs' => $attrs,
              'child' => [],
            ];
            end($i['child']);
            $path[] = key($i['child']);
          }
          if ($d->category) {
            $i = &$d->category;
            foreach ($d->path as $item) {
              $i = &$i['child'][$item];
            }
            $i['child'][] = [
              'tag' => $name,
              'value' => '',
              'attrs' => $attrs,
              'child' => [],
            ];
            end($i['child']);
            $d->path[] = key($i['child']);
          }
          break;
      }
    }
  }
}