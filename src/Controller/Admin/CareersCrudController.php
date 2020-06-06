<?php

namespace App\Controller\Admin;

use App\Entity\Careers;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class CareersCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Careers::class;
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
