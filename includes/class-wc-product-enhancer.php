<?php
namespace WCProductEnhancer;

use \WCProductEnhancer\Admin\AdminManager;
use \WCProductEnhancer\Frontend\FrontendManager;
use \WCProductEnhancer\Cart\CartManager;

class WCProductEnhancer{
    protected $admin_manager;
    protected $frontend_manager;
    protected $cart_manager;

    public function __construct(){
        $this->load_dependencies();
    }

    private function load_dependencies()
    {
        $this->admin_manager = new AdminManager();
        $this->frontend_manager = new FrontendManager();
        $this->cart_manager = new CartManager();
    }
}