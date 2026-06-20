<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Models\WooCommerceSetting' => 'App\Policies\WooCommerceSettingPolicy',
        'App\Models\OnlineOrder' => 'App\Policies\OnlineOrderPolicy',
        'App\Models\StoreBanner' => 'App\Policies\StoreBannerPolicy',
        'App\Models\StoreSetting' => 'App\Policies\StoreSettingPolicy',
        'App\Models\Collection' => 'App\Policies\CollectionPolicy',
        'App\Models\Subscriber' => 'App\Policies\SubscriberPolicy',
        'App\Models\Message' => 'App\Policies\MessagePolicy',

        'App\Models\PaymentMethod' => 'App\Policies\PaymentMethodPolicy',
        'App\Models\ErrorLog' => 'App\Policies\ErrorLogPolicy',
        'App\Models\Subscription' => 'App\Policies\SubscriptionPolicy',
        'App\Models\ExpenseCategory' => 'App\Policies\ExpenseCategoryPolicy',
        'App\Models\Purchase' => 'App\Policies\PurchasePolicy',
        'App\Models\Adjustment' => 'App\Policies\AdjustmentPolicy',
        'App\Models\Product' => 'App\Policies\ProductPolicy',
        'App\Models\Brand' => 'App\Policies\BrandPolicy',
        'App\Models\Category' => 'App\Policies\CategoryPolicy',
        'App\Models\SubCategory' => 'App\Policies\SubCategoryPolicy',
        'App\Models\Client' => 'App\Policies\ClientPolicy',
        'App\Models\Currency' => 'App\Policies\CurrencyPolicy',
        'App\Models\Expense' => 'App\Policies\ExpensePolicy',
        'App\Models\PaymentPurchase' => 'App\Policies\PaymentPurchasePolicy',
        'App\Models\PaymentSaleReturns' => 'App\Policies\PaymentSaleReturnsPolicy',
        'App\Models\PaymentPurchaseReturns' => 'App\Policies\PaymentPurchaseReturnsPolicy',
        'App\Models\PaymentSale' => 'App\Policies\PaymentSalePolicy',
        'App\Models\Warehouse' => 'App\Policies\WarehousePolicy',
        'App\Models\WarehouseLocation' => 'App\Policies\WarehouseLocationPolicy',
        'App\Models\Provider' => 'App\Policies\ProviderPolicy',
        'App\Models\Quotation' => 'App\Policies\QuotationPolicy',
        'App\Models\SaleReturn' => 'App\Policies\SaleReturnPolicy',
        'App\Models\PurchaseReturn' => 'App\Policies\PurchaseReturnPolicy',
        'App\Models\Role' => 'App\Policies\RolePolicy',
        'App\Models\Server' => 'App\Policies\ServerPolicy',
        'App\Models\Setting' => 'App\Policies\SettingPolicy',
        'App\Models\Transfer' => 'App\Policies\TransferPolicy',
        'App\Models\Unit' => 'App\Policies\UnitPolicy',
        'App\Models\Sale' => 'App\Policies\SalePolicy',
        'App\Models\User' => 'App\Policies\UserPolicy',
        'App\Models\Shipment' => 'App\Policies\ShipmentPolicy',
        'App\Models\Account' => 'App\Policies\AccountPolicy',
        'App\Models\TransferMoney' => 'App\Policies\TransferMoneyPolicy',
        'App\Models\Deposit' => 'App\Policies\DepositPolicy',
        'App\Models\DepositCategory' => 'App\Policies\DepositCategoryPolicy',
        'App\Models\Asset' => 'App\Policies\AssetPolicy',
        'App\Models\Damage' => 'App\Policies\DamagePolicy',

        // hrm
        'App\Models\Employee' => 'App\Policies\EmployeePolicy',
        'App\Models\Company' => 'App\Policies\CompanyPolicy',
        'App\Models\Department' => 'App\Policies\DepartmentPolicy',
        'App\Models\Designation' => 'App\Policies\DesignationPolicy',
        'App\Models\OfficeShift' => 'App\Policies\Office_ShiftPolicy',
        'App\Models\Attendance' => 'App\Policies\AttendancePolicy',
        'App\Models\Leave' => 'App\Policies\LeavePolicy',
        'App\Models\Holiday' => 'App\Policies\HolidayPolicy',
        'App\Models\Payroll' => 'App\Policies\PayrollPolicy',
        'App\Models\Project' => 'App\Policies\ProjectPolicy',
        'App\Models\Contract' => 'App\Policies\ContractPolicy',
        'App\Models\KnowledgeBaseArticleGroup' => 'App\Policies\KnowledgeBaseArticleGroupPolicy',
        'App\Models\KnowledgeBaseArticle' => 'App\Policies\KnowledgeBaseArticlePolicy',
        'App\Models\Task' => 'App\Policies\TaskPolicy',
        'App\Models\ServiceJob' => 'App\Policies\ServiceJobPolicy',
        'App\Models\Booking' => 'App\Policies\BookingPolicy',

        // Commission module
        'App\Models\CommissionProgram' => 'App\Policies\CommissionProgramPolicy',

        // Webhooks module
        'App\Models\Webhook' => 'App\Policies\WebhookPolicy',

    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Passport::routes();

    }
}
