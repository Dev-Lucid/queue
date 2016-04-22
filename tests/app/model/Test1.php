<?php
namespace App\Model;

class Test1 extends \App\Model
{
    public function hasPermissionSelect(array $data)
    {
        return true;
    }

    public function hasPermissionInsert(array $data)
    {
        return true;
    }

    public function hasPermissionUpdate(array $data)
    {
        return true;
    }

    public function hasPermissionDelete(array $data)
    {
        return true;
    }

}