<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

$arResult["btn"]["goals"]["score"] = [
   ["name"=>"3-0", "cell"=> "goal"],
   ["name"=>"2-1", "cell"=> "goal"],
   ["name"=>"2-0", "cell"=> "goal"],
   ["name"=>"1-0", "cell"=> "goal"],
   ["name"=>"0-2", "cell"=> "goal"],
   ["name"=>"0-1", "cell"=> "goal"],
   ["name"=>"1-2", "cell"=> "goal"],
   ["name"=>"1-3", "cell"=> "goal"],
];
$arResult["btn"]["goals"]["inc_home"] = [
    ["name"=>"+3", "cell"=> "goal_home"],
    ["name"=>"+1", "cell"=> "goal_home"],
    ["name"=>"0", "cell"=> "goal_home"],
];

$arResult["btn"]["goals"]["inc_guest"] = [
    ["name"=>"0", "cell"=> "goal_guest"],
    ["name"=>"+1", "cell"=> "goal_guest"],
    ["name"=>"+3", "cell"=> "goal_guest"],
];

$arResult["btn"]["dom"]["home"] = [
    ["name"=>"+10", "cell"=> "dom_home"],
    ["name"=>"+5", "cell"=> "dom_home"],
    ["name"=>"+3", "cell"=> "dom_home"],
    ["name"=>"+1", "cell"=> "dom_home"],
    ["name"=>"50", "cell"=> "dom_home"],
];
$arResult["btn"]["dom"]["guest"] = [
    ["name"=>"+1", "cell"=> "dom_guest"],
    ["name"=>"+3", "cell"=> "dom_guest"],
    ["name"=>"+5", "cell"=> "dom_guest"],
    ["name"=>"+10", "cell"=> "dom_guest"],
];

$arResult["btn"]["cards"]["yellow"] = [
    ["name"=>"+5", "cell"=> "c_yellow"],
    ["name"=>"+3", "cell"=> "c_yellow"],
    ["name"=>"+1", "cell"=> "c_yellow"],
    ["name"=>"0", "cell"=> "c_yellow"],
];

$arResult["btn"]["cards"]["red"] = [
    ["name"=>"0", "cell"=> "c_red"],
    ["name"=>"+1", "cell"=> "c_red"],
];

$arResult["btn"]["corner"] = [
    ["name"=>"+5", "cell"=> "o_corner_i"],
    ["name"=>"+3", "cell"=> "o_corner_i"],
    ["name"=>"+1", "cell"=> "o_corner_i"],
    ["name"=>"0", "cell"=> "o_corner_i"],
];

$arResult["btn"]["penalty"] = [
    ["name"=>"0", "cell"=> "o_penalty_i"],
    ["name"=>"+1", "cell"=> "o_penalty_i"],
];
