<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Users')
            ->setSearchFields(['id', 'username', 'email']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addPanel('Account Information');
        yield IntegerField::new('id', 'ID')->onlyOnIndex();
        yield BooleanField::new('active');
        yield TextField::new('username');
        yield TextField::new('full_name');
        yield TextField::new('email');
    }
}
