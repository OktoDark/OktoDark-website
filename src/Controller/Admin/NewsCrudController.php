<?php

/*
 * Copyright (c) 2013 - 2020 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\News;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;


class NewsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return News::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('News')
            ->setDateTimeFormat('full')
            ->addFormTheme('@FOSCKEditor/Form/ckeditor_widget.html.twig')
            ->setFormOptions([
                'validation_groups' => ['content']
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title');
        yield TextField::new('author');
        yield DateTimeField::new('createdAt');
        yield TextEditorField::new('content');
    }
}
