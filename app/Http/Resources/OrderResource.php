<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return array_filter([
            'id' => $this->id,
            'type' => $this->type,
            'waiter' => $this->when($this->waiter, fn() => [
                'id' => $this->waiter->id,
                'name' => $this->waiter->name
            ]),
            'table' => $this->when($this->table, fn() => [
                'id' => $this->table->id,
                'name' => $this->table->name
            ]),
            'floor' => $this->when($this->floor, fn() => [
                'id' => $this->floor->id,
                'name' => $this->floor->name
            ]),
            'grand_total' => $this->grand_total,
            'discount' => $this->discount,
            'net_total' => $this->net_total,
            'cash_received' => $this->cash_received,
            'change_due' => $this->change_due,
            'is_cancelled' => $this->is_cancelled,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'menu_item' => [
                            'id' => $item->menuItem->id,
                            'name' => $item->menuItem->name,
                        ],
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'total' => $item->total,
                    ];
                });
            }),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ], fn($value) => !is_null($value));
    }
}
