<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Form\Type;

use App\Utils\MomentFormatConverter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\String\u;

class DateTimePickerType extends AbstractType
{
    public function __construct(
        private MomentFormatConverter $formatConverter,
    ) {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['attr']['data-date-format'] = $this->formatConverter->convert($options['format']);
        $view->vars['attr']['data-date-locale'] = u(\Locale::getDefault())->replace('_', '-')->lower();
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'widget' => 'single_text',
            // if true, the browser will display the native date picker widget
            // however, this app uses a custom JavaScript widget, so it must be set to false
            'html5' => false,
        ]);
    }

    public function getParent(): ?string
    {
        return DateTimeType::class;
    }
}
