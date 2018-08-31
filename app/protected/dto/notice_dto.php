<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2018/2/10
 * Time: 上午12:17
 */

namespace app\dto;

class notice_dto extends \Dto\Dto
{
    protected $schema = [
        'type' => 'object',
        'properties' => [
            'label' => ['type' => 'string'],
            'value' => ['type' => 'string']
        ],
        'additionalProperties' => true
    ];
}