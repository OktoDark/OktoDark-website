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

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SecuritySettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('loginAlertsEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'settings.login_alerts',
            ])
            ->add('trustedDevicesEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'settings.trusted_devices_toggle',
            ])
            ->add('twofaResendEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'settings.allow_resend_code',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'settings',
        ]);
    }
}
