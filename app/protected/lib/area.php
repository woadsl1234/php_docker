<?php
class area
{
    private $area_map;
    
    public function __construct()
    {
        $this->area_map = json_decode(file_get_contents(INCL_DIR.DS.'area_map.json'), true);
    }
    
    public function get_all(){return $this->area_map;}
    
    public function get_children($province = 0, $city = 0)
    {
        $map = $this->area_map;
        $children = array();
        if(!empty($province))
        {
            foreach($map[$province]['children'] as $k => $v) $children[$k] = $v['name'];
            unset($k, $v);
            if(!empty($city))
            {
                $children = array();
                foreach($map[$province]['children'][$city]['children'] as $k => $v) $children[$k] = $v;
            }
        }
        else
        {
            foreach($map as $k => $v) $children[$k] = $v['name'];
        }
        return $children;
    }
    
    public function get_area_name($province = 0, $city = 0, $borough = 0)
    {
        $map = $this->area_map;
        $provinceId = $map['provinces'][$province]['id'];

        return array
        (
            'province' => isset($map['provinces'][$province]) ? $map['provinces'][$province]['name'] : null,
            'city' => isset($map['citys'][$provinceId][$city]) ? $map['citys'][$provinceId][$city]['name'] : null,
            'borough' => isset($map['areas'][$map['citys'][$provinceId][$city]['id']]) ? $map['areas'][$map['citys'][$provinceId][$city]['id']][$borough]['name'] : null,
        );
    }
    
    public function __destruct()
    {
        $this->area_map = null;
    }
}
