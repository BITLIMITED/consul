<?php

namespace Bitlimited\Consul\Method;

use indigerd\consul\models\Service;
use indigerd\consul\ServiceFactory;
use indigerd\consul\services\ServiceDiscoveryInterface;
use indigerd\consul\services\ServiceKeyValueInterface;
use indigerd\consul\services\ServiceRegistryInterface;

class RegistrationMethod
{
    public function __construct(string $dir)
    {

        $factory = new ServiceFactory();

        /* @var ServiceKeyValueInterface $storage */
        $storage = $factory->get('kv');
        $storageKey = gethostname() . '/' . $dir . '/config.json';
        if (apcu_exists($storageKey)) {
            $storageValue = apcu_fetch($storageKey, $success);
            if (!$success) {
                throw new \RuntimeException('Failed to get APCU cache value');
            }
        } else {
            $storageValue = json_decode($storage->getKeyValue($storageKey)->getValue());
            apcu_add($storageKey, $storageValue, 10);

            /** @var ServiceRegistryInterface $registry */
            $registry = $factory->get('registry');

            $service = new Service();
            $service->setName($storageValue->consul->Name);
            $service->setAddress($storageValue->consul->Address);
            $service->setPort($storageValue->consul->Port);
            $service->setTags($storageValue->consul->Tags);
            $service->setId($storageValue->consul->ID);

            /** @var ServiceDiscoveryInterface $discovery */
            $discovery = $factory->get('discovery');

            $addresses = $discovery->getServiceAddresses($service->getName());

            $isRegistered = false;
            foreach ($addresses as $address) {
                if ($address->ServiceID === $storageValue->consul->ID) {
                    $isRegistered = true;
                    break;
                }
            }

            if (!$isRegistered) {
                $res = $registry->register($service);
            }

        }
    }

}