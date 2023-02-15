<?php

namespace App\Classes\Alarms;

use App\Classes\LoggerQuery;
use App\Classes\LoggerQueryFactory;

class MixedInputQueryBuilder {

  private $query;
  private $function;
  private $parameters;

  public function __construct($query , $function , $parameters) {
    $this->query = $query;
    $this->function = $function;
    $this->parameters = $parameters;
  }

  public function get() {
    return $this->{$this->function}();
  }

  private function setMust() {
    $match = collect($this->parameters)->flatMap(function ($value , $field) {
      return [
        'field' => $field,
        'value' => $value
      ];
    })->toArray();

    $this->query->whereMust([
      LoggerQueryFactory::get()->whereMatch($match)
    ]);
  }

  private function setMustNot() {
    $match = collect($this->parameters)->flatMap(function ($value , $field) {
      return [
        'field' => $field,
        'value' => $value
      ];
    })->toArray();

    $this->query->whereMustNot([
      LoggerQueryFactory::get()->whereMatch($match)
    ]);
  }

  private function setMustMustMust() {
    foreach ($this->parameters as $field => $value) {
      $this->query->whereMust([
        LoggerQueryFactory::get()->whereMatch([
          'field' => $field,
          'value' => $value
        ])
      ]);
    }
  }

  private function setMustNotMustMust() {
    $match = collect($this->parameters)->map(function ($value , $field) {
      return [
        'field' => $field,
        'value' => $value
      ];
    });

    $this->query->whereMustNot([
      LoggerQueryFactory::get()->whereMatch($match->first())
    ]);

    $this->query->whereMust([
      LoggerQueryFactory::get()->whereMatch($match->last())
    ]);
  }

  private function setMustNotMustMustNot() {
    foreach ($this->parameters as $field => $value) {
      $this->query->whereMustNot([
        LoggerQueryFactory::get()->whereMatch([
          'field' => $field,
          'value' => $value
        ])
      ]);
    }
  }

  private function setMustMustMustNot() {
    $this->parameters = collect($this->parameters)->reverse()->toArray();
    $this->setMustNotMustMust();
  }

  private function setMustShouldMust() {
    foreach ($this->parameters as $field => $value) {
      $this->query->whereShould([
        LoggerQueryFactory::get()->whereMatch([
          'field' => $field,
          'value' => $value
        ])
      ]);
    }
  }

  private function setMustShouldMustNot() {
    $match = collect($this->parameters)->map(function ($value , $field) {
      return [
        'field' => $field,
        'value' => $value
      ];
    });

    $queries = [
      LoggerQueryFactory::get()->whereMust([
         LoggerQueryFactory::get()->whereMatch($match->first())
      ])->getBoolQuery(),

      LoggerQueryFactory::get()->whereMustNot([
         LoggerQueryFactory::get()->whereMatch($match->last())
      ])->getBoolQuery()
    ];

    $this->query->whereShould($queries);
  }

  private function setMustNotShouldMust() {
    $this->parameters = collect($this->parameters)->reverse()->toArray();
    $this->setMustShouldMustNot();
  }

  private function setMustNotShouldMustNot() {
    $queries = [];
    foreach ($this->parameters as $field => $value) {
      $queries[] = LoggerQueryFactory::get()->whereMatch([
                    'field' => $field,
                    'value' => $value
                  ]);
    }

    $this->query->whereShould([
      LoggerQueryFactory::get()->whereMustNot($queries)->getBoolQuery()
   ]);
  }
}
