<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Form;

use App\Entity\ModCategory;
use App\Entity\Mods;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ModsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // BASIC INFO
            ->add('name', TextType::class, [
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'required' => true,
            ])
            ->add('version', TextType::class, [
                'required' => false,
            ])
            ->add('download', UrlType::class, [
                'required' => true,
            ])

            // CATEGORIES (multiple)
            ->add('categories', EntityType::class, [
                'class' => ModCategory::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true, // checkboxes
                'required' => false,
                'label' => 'Categories',
            ])

            // TAGS
            ->add('tags', CollectionType::class, [
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => ['class' => 'form-control'],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
            ])

            // COMPATIBLE VERSIONS
            ->add('compatible', CollectionType::class, [
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => ['class' => 'form-control'],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
            ])

            // BANNER UPLOAD (auto thumbnail generated)
            ->add('bannerFile', FileType::class, [
                'mapped' => false,
                'required' => false,
            ])

            // GALLERY UPLOAD (auto small versions generated)
            ->add('galleryFiles', FileType::class, [
                'mapped' => false,
                'multiple' => true,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Mods::class,
        ]);
    }
}
