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

use App\Entity\MediaMetadata;
use App\Enum\MediaType;
use App\Enum\Source;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaMetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mediaId', TextType::class, [
                'label' => 'Media ID (TMDB, MAL, etc.)',
                'required' => true,
                'help' => 'The external ID from the media source',
            ])
            ->add('source', ChoiceType::class, [
                'choices' => array_combine(
                    array_map(fn (Source $s) => $s->value, Source::cases()),
                    Source::cases()
                ),
                'required' => true,
            ])
            ->add('mediaType', ChoiceType::class, [
                'choices' => array_combine(
                    array_map(fn (MediaType $t) => $t->value, MediaType::cases()),
                    MediaType::cases()
                ),
                'required' => true,
            ])
            ->add('title', TextType::class, [
                'label' => 'Title',
                'required' => true,
            ])
            ->add('image', TextType::class, [
                'label' => 'Image URL',
                'required' => false,
                'help' => 'URL to the media poster/image',
            ])
            ->add('seasonNumber', NumberType::class, [
                'label' => 'Season Number',
                'required' => false,
                'help' => 'Only for season/episode types',
            ])
            ->add('episodeNumber', NumberType::class, [
                'label' => 'Episode Number',
                'required' => false,
                'help' => 'Only for episode types',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MediaMetadata::class,
        ]);
    }
}
