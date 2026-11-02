<?php

namespace App\Support\Constants;

class SystemConstants
{
    /**
     * Value Added Tax (VAT) rate in the Philippines (12%).
     */
    public const VAT_RATE = 0.12;

    /**
     * Senior Citizen and Person with Disability (PWD) statutory discount rate (20%).
     */
    public const SENIOR_PWD_DISCOUNT_RATE = 0.20;

    /**
     * Default currency code and symbol.
     */
    public const CURRENCY_CODE = 'PHP';
    public const CURRENCY_SYMBOL = '₱';

    /**
     * Session keys.
     */
    public const SESSION_AUTH_METHOD = 'auth_method';
    public const SESSION_CURRENT_BRANCH = 'current_branch_id';
}
