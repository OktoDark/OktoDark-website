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

use App\Entity\TV;
use App\Enum\WatchStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TVFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mediaMetadata', MediaMetadataType::class, [
                'label' => 'TV Show Details',
            ])
            ->add('status', ChoiceType::class, [
                'choices' => array_combine(
                    array_map(static fn (WatchStatus $s) => $s->value, WatchStatus::cases()),
                    WatchStatus::cases()
                ),
                'required' => true,
            ])
            ->add('score', NumberType::class, [
                'label' => 'Rating',
                'required' => false,
                'help' => 'Rating from 0 to 10',
            ])
            ->add('progress', NumberType::class, [
                'label' => 'Episodes Watched',
                'required' => true,
                'help' => 'Total episodes watched across all seasons',
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => 'Start Date',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'End Date',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'rows' => 4,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TV::class,
        ]);
    }
}
