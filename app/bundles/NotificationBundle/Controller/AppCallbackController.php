<?php

namespace Mautic\NotificationBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\NotificationBundle\Entity\Notification;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AppCallbackController extends CommonController
{
    public function indexAction(Request $request)
    {
        $requestBody = json_decode($request->getContent(), true);
        $em          = $this->get('doctrine.orm.entity_manager');
        $contactRepo = $em->getRepository(Lead::class);

        $matchData = null;

        if(array_key_exists('email', $requestBody)){
            $matchData   = [
                'email' => $requestBody['email'],
            ];
        }

        if(array_key_exists('id', $requestBody)){
            $matchData   = [
                'id' => $requestBody['id'],
            ];
        }

        if ($matchData === null || !array_key_exists('push_id', $requestBody) || !array_key_exists('enabled', $requestBody)) {
            return new JsonResponse(
                [
                    'errors' => [
                        [
                            'code'    => 400
                        ],
                    ],
                ],
                400 
            );
        }

        /** @var Lead $contact */
        $contact = $contactRepo->findOneBy($matchData);

        if (null === $contact) {
            return new JsonResponse(
                [
                    'errors' => [
                        [
                            'code'    => 400
                        ],
                    ],
                ],
                400 
            );
        }

        $pushIdCreated = false;

        if (array_key_exists('push_id', $requestBody)) {
            $pushIdCreated = true;
            $contact->addPushIDEntry($requestBody['push_id'], $requestBody['enabled'], true);
            $contactRepo->saveEntity($contact);
        }

        $statCreated = false;

        if (array_key_exists('stat', $requestBody)) {
            $stat             = $requestBody['stat'];
            $notificationRepo = $em->getRepository(Notification::class);
            $notification     = $notificationRepo->getEntity($stat['notification_id']);

            if (null !== $notification) {
                $statCreated = true;
                $this->getModel('notification')->createStatEntry($notification, $contact, $stat['source'], $stat['source_id']);
            }
        }

        return new JsonResponse([
            'contact_id'       => $contact->getId(),
            'stat_recorded'    => $statCreated,
            'push_id_recorded' => $pushIdCreated ?: 'existing',
        ]);
    }
}
