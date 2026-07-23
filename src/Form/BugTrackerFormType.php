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

use App\Entity\BugTracker;
use App\Entity\Mods;
use App\Entity\OurGames;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BugTrackerFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'admin.bug_trackers.form.name_label',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'admin.bug_trackers.form.name_placeholder',
                ],
            ])
            ->add('slug', TextType::class, [
                'label' => 'admin.bug_trackers.form.slug_label',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'admin.bug_trackers.form.slug_placeholder',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'admin.bug_trackers.form.description_label',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'admin.bug_trackers.form.description_placeholder',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'admin.bug_trackers.form.is_active_label',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])
            ->add('ourGame', EntityType::class, [
                'class' => OurGames::class,
                'choice_label' => 'name',
                'placeholder' => 'admin.bug_trackers.form.project_placeholder',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('mod', EntityType::class, [
                'class' => Mods::class,
                'choice_label' => 'name',
                'placeholder' => 'admin.bug_trackers.form.mod_placeholder',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BugTracker::class,
            'csrf_protection' => true,
            'translation_domain' => 'admin',
        ]);
    }
}
