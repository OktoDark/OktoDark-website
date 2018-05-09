<?php
/**
 * Copyright (c) 2018 OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * File date of modification: 09.05.2018 12:20
 */

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Patreon\Patreon;
use App\Entity\Settings;
use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Patreon\API;
use Patreon\OAuth;

class PatronController extends Controller
{
    public function patron(Connection $connection): Response
    {
        $client_id = '';
        $client_secret = '';

        $oauth_client = new Patreon\OAuth($client_id, $client_secret);

        $redirect_uri = '';

        $tokens = $oauth_client->get_tokens($_GET['code'], $redirect_uri);
        $access_token = $tokens['access_token'];
        $refresh_token = $tokens['refresh_token'];

        $api_client = new Patreon\API($access_token);
        $patron_response = $api_client->fetch_user();

        $patron = $patron_response->get('data');

        $pledge = null;

        if ($user->has('relationship.pledges'))
        {
            $pledge = $user->relationship('pledges')->get(0)->resolve($user_response);
        }


        $selectSettings = $connection->fetchAll('SELECT * FROM settings');

        return $this->render('@theme/patron.html.twig', ['settings' => $selectSettings]);
    }
}
