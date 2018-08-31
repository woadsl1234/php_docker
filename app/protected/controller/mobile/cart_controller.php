<?php
class cart_controller extends general_controller
{
    public function action_index()
    {
        $vcache = vcache::instance();
        $this->full_cut = $vcache->full_cut_model('get_underway_activity');
        $this->compiler('cart.html');
    }

}