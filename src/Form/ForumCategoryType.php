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

use App\Entity\ForumCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForumCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ForumCategory|null $category */
        $category = $options['data'] ?? null;

        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
            ])
            ->add('slug', TextType::class, [
                'label' => 'URL Slug',
                'required' => false,
                'attr' => ['placeholder' => 'Leave empty to auto-generate'],
            ])
            ->add('parent', EntityType::class, [
                'class' => ForumCategory::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'None (Top Level Category)',
                'label' => 'Parent Category',
                'query_builder' => function (\App\Repository\ForumCategoryRepository $repo) use ($category) {
                    $qb = $repo->createQueryBuilder('c')
                        ->orderBy('c.position', 'ASC');

                    if ($category && $category->getId()) {
                        $qb->andWhere('c.id != :id')
                           ->setParameter('id', $category->getId());
                    }

                    return $qb;
                },
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Short description of this category',
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Display Position',
                'required' => true,
                'attr' => ['min' => 0],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumCategory::class,
        ]);
    }
}
