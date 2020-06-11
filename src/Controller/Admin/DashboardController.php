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

use App\Entity\Assets;
use App\Entity\Careers;
use App\Entity\News;
use App\Entity\Services;
use App\Entity\Settings;
use App\Entity\Team;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @IsGranted("ROLE_ADMIN")
 */
class DashboardController extends AbstractDashboardController
{
    /**
     * @Route("/admin", name="admin")
     */
    public function index(): Response
    {
        return parent::index();
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('OktoDark Website');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('Homepage', 'fas fa-home', 'homepage');
        yield MenuItem::linktoDashboard('Dashboard', 'fas fa-chalkboard');

        yield MenuItem::section('Website', 'fas fa-folder-open');
        yield MenuItem::linkToCrud('News', 'far fa-newspaper', News::class);
        yield MenuItem::linkToCrud('Settings', 'fa fa-wrench ', Settings::class);
        yield MenuItem::linkToCrud('Services', 'fa fa-vector-square ', Services::class);
        yield MenuItem::linkToCrud('Assets', 'fa fa-archive ', Assets::class);
        yield MenuItem::linkToCrud('Careers', 'fa fa-id-badge ', Careers::class);
        yield MenuItem::linkToCrud('Team', 'fa fa-users ', Team::class);

        yield MenuItem::section('Member Info', 'fas fa-folder-open');
        yield MenuItem::linkToCrud('User', 'fa fa-user', User::class);
    }
}
