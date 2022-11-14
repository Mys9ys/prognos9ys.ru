<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

$arResult["btn"]["goals"]["score"] = [
   ["name"=>"3-1", "cell"=> "goal", "type"=>"double"],
   ["name"=>"2-1", "cell"=> "goal", "type"=>"double"],
   ["name"=>"1-1", "cell"=> "goal", "type"=>"double"],
   ["name"=>"1-0", "cell"=> "goal", "type"=>"double"],
   ["name"=>"0-0", "cell"=> "goal", "type"=>"double"],
   ["name"=>"0-1", "cell"=> "goal", "type"=>"double"],
   ["name"=>"1-2", "cell"=> "goal", "type"=>"double"],
   ["name"=>"1-3", "cell"=> "goal", "type"=>"double"],
];
$arResult["btn"]["goals"]["inc_home"] = [
    ["name"=>"+3", "cell"=> "goal_home", "type"=>"one"],
    ["name"=>"+1", "cell"=> "goal_home", "type"=>"one"],
    ["name"=>"0", "cell"=> "goal_home", "type"=>"one"],
];

$arResult["btn"]["goals"]["inc_guest"] = [
    ["name"=>"0", "cell"=> "goal_guest", "type"=>"one"],
    ["name"=>"+1", "cell"=> "goal_guest", "type"=>"one"],
    ["name"=>"+3", "cell"=> "goal_guest", "type"=>"one"],
];

$arResult["btn"]["dom"]["home"] = [
    ["name"=>"+10", "cell"=> "dom_home", "type"=>"one"],
    ["name"=>"+5", "cell"=> "dom_home", "type"=>"one"],
    ["name"=>"+3", "cell"=> "dom_home", "type"=>"one"],
    ["name"=>"+1", "cell"=> "dom_home", "type"=>"one"],
    ["name"=>"50", "cell"=> "dom_home", "type"=>"one"],
];
$arResult["btn"]["dom"]["guest"] = [
    ["name"=>"+1", "cell"=> "dom_guest", "type"=>"one"],
    ["name"=>"+3", "cell"=> "dom_guest", "type"=>"one"],
    ["name"=>"+5", "cell"=> "dom_guest", "type"=>"one"],
    ["name"=>"+10", "cell"=> "dom_guest", "type"=>"one"],
];

$arResult["btn"]["cards"]["yellow"] = [
    ["name"=>"+3", "cell"=> "c_yellow", "type"=>"one"],
    ["name"=>"+1", "cell"=> "c_yellow", "type"=>"one"],
    ["name"=>"0", "cell"=> "c_yellow", "type"=>"one"],
];

$arResult["btn"]["cards"]["red"] = [
    ["name"=>"0", "cell"=> "c_red", "type"=>"one"],
    ["name"=>"+1", "cell"=> "c_red", "type"=>"one"],
];