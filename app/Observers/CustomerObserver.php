<?php

namespace App\Observers;

use App\Models\Customer;
use App\Services\CustomerHierarchyService;

class CustomerObserver
{
    public function __construct(private readonly CustomerHierarchyService $hierarchy)
    {
    }

    public function saving(Customer $customer): void
    {
        $this->hierarchy->validateForSave($customer);
    }

    public function saved(Customer $customer): void
    {
        $this->hierarchy->afterSaved($customer);
    }

    public function deleting(Customer $customer): void
    {
        $this->hierarchy->ensureCanDelete($customer);
    }
}
