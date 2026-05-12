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

use App\Entity\Board;
use App\Entity\Mods;
use App\Entity\OurGames;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BoardFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'kanban.create_board_modal.form.title_label',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'kanban.create_board_modal.form.title_placeholder',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'kanban.create_board_modal.form.description_label',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'kanban.create_board_modal.form.description_placeholder',
                ],
            ])
            ->add('backgroundColor', ColorType::class, [
                'label' => 'kanban.create_board_modal.form.background_color_label',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('isPublic', CheckboxType::class, [
                'label' => 'kanban.create_board_modal.form.is_public_label',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])
            ->add('ourGame', EntityType::class, [
                'class' => OurGames::class,
                'choice_label' => 'name',
                'placeholder' => 'kanban.create_board_modal.form.our_game_placeholder',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('mod', EntityType::class, [ // Add mod field
                'class' => Mods::class,
                'choice_label' => 'name',
                'placeholder' => 'kanban.create_board_modal.form.mod_placeholder',
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
            'data_class' => Board::class,
            'csrf_protection' => false, // Disable CSRF for API forms
            // Removed 'block_prefix' => '', to allow default form naming (e.g., 'board_form')
            'allow_extra_fields' => true, // Allow extra fields to be ignored
            'translation_domain' => 'kanban',
        ]);
    }
}
