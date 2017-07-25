<?php

namespace Drupal\commerce_yandex_market;

class XmlParserHandler {

  protected $buffer;

  protected $data;

  public $date;
  protected $last_date;

  function __construct(XmlOffersBuffer $buffer, $last_date) {
    $this->data = [];
    $this->buffer = $buffer;
    $this->last_date = $last_date;
  }

  function newParserData(): XmlParserData {
    return new XmlParserData();
  }

  function getData(string $p): XmlParserData {
    if (!array_key_exists($p, $this->data)) {
      $this->data[$p] = $this->newParserData();
    }
    return $this->data[$p];
  }

  function resetData() {
    return reset($this->data);
  }

  function character_data_handler($p, string $data) {
    $d = $this->getData((string)$p);
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

  function end_element_handler($p, string $name) {
    $d = $this->getData((string)$p);
    array_splice($d->tags, -1, 1);
    switch ($name) {
      case 'OFFERS':
        break;
      case 'OFFER':
        $this->buffer->add($d->offer);
        $d->offer = NULL;
        break;
      default:
        if ($d->offer) {
          array_pop($d->path);
        }
        break;
    }
  }

  function start_element_handler($p, string $name, array $attrs) {
    $d = $this->getData((string)$p);
    $d->tags[] = $name;
    switch ($name) {
      case 'ДАТАФОРМИРОВАНИЯ':
        $this->date = date_create_from_format('Y-m-dTH:i', $attrs['DATE']);
        $d->alreadyUpdated = $this->last_date == $this->date;
        echo "Already updated: " . var_export($d->alreadyUpdated, TRUE);
        break;
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
          $d->path[] = key($i['child']);
        }
        break;
    }
  }
}