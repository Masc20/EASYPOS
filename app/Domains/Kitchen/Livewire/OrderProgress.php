<?php

namespace App\Domains\Kitchen\Livewire;

use App\Domains\PointOfSale\Livewire\OrderProgress as PointOfSaleOrderProgress;

/**
 * Kitchen Domain OrderProgress alias for PointOfSale OrderProgress.
 *
 * Implements F25 & F26: Grouped SQL aggregate badge counts and indexable timestamp ranges.
 */
class OrderProgress extends PointOfSaleOrderProgress{}
