<?php

/*
 * This file is part of the GitLabBundle for Kimai 2.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\GitLabBundle\EventSubscriber;

use App\Event\SystemConfigurationEvent;
use App\Form\Model\Configuration;
use App\Form\Model\SystemConfiguration as SystemConfigurationModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

final class SystemConfigurationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            SystemConfigurationEvent::class => ['onSystemConfiguration', 100],
        ];
    }

    public function onSystemConfiguration(SystemConfigurationEvent $event)
    {
        $event->addConfiguration((new SystemConfigurationModel())
            ->setSection('gitlab')
            ->setConfiguration([
                (new Configuration())
                    ->setName('gitlab_private_token')
                    ->setLabel('gitlab.private_token')
                    ->setOptions(['help' => 'help.gitlab.private_token'])
                    ->setTranslationDomain('system-configuration')
                    ->setType(TextType::class),
                (new Configuration())
                    ->setName('gitlab_instance_base_url')
                    ->setLabel('gitlab.instance_base_url')
                    ->setTranslationDomain('system-configuration')
                    ->setType(UrlType::class),
            ])
        );
    }
}