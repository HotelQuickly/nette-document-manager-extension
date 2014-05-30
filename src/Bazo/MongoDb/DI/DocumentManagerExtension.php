<?php

namespace Bazo\MongoDb\DI;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\IndexedReader;
use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;
use Nette\DI\CompilerExtension;
use Nette\DI\Container;



/**
 * @author Martin Bažík <martin@bazo.sk>
 */
class DocumentManagerExtension extends CompilerExtension
{

	/** @var array */
	public $defaults = [
		'documentsDir'					 => '%appDir%/model/documents',
		'proxyDir'						 => '%tempDir%/proxies',
		'hydratorDir'					 => '%tempDir%/hydrators',
		'dbname'						 => 'app',
		'uri'							 => 'mongodb://localhost/app',
		'cachePrefix'					 => 'app',
		'metaDataCacheClass'			 => '\Doctrine\Common\Cache\ArrayCache',
		'autoGenerateHydratorClasses'	 => FALSE,
		'autoGenerateProxyClasses'		 => FALSE,
		'hydratorNamespace'				 => 'Hydrators',
		'proxyNamespace'				 => 'Proxies',
		'cacheAnnotations'				 => TRUE,
		'mongoOptions'					 => ['connect' => TRUE],
		'eventManager'					 => NULL,
		'debug'							 => FALSE,
		'indexAnnotations'				 => TRUE,
		'metaDataCache'					 => NULL
	];

	/**
	 * Processes configuration data
	 *
	 * @return void
	 */
	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();

		$config = $this->getConfig($this->defaults, TRUE);

		$container->addDefinition($this->prefix('documentManager'))
				->setClass('\Doctrine\ODM\MongoDB\DocumentManager')
				->setFactory('\Bazo\MongoDb\DI\DocumentManagerExtension::createDocumentManager', [$config,
					'@container'])
				->setAutowired(FALSE);

		$container->addDefinition('documentManager')
				->setClass('\Doctrine\ODM\MongoDB\DocumentManager')
				->setFactory('@container::getService', [$this->prefix('documentManager')]);
	}


	/**
	 *
	 * @param array $config
	 * @return DocumentManager
	 */
	public static function createDocumentManager($config, Container $container)
	{
		$configuration = new Configuration();

		$configuration->setProxyDir($config['proxyDir']);
		$configuration->setProxyNamespace($config['proxyNamespace']);

		$configuration->setHydratorDir($config['hydratorDir']);
		$configuration->setHydratorNamespace($config['hydratorNamespace']);

		$configuration->setAutoGenerateHydratorClasses($config['autoGenerateHydratorClasses']);
		$configuration->setAutoGenerateProxyClasses($config['autoGenerateProxyClasses']);

		if (isset($config['metaDataCache'])) {
			$metadataCache = $config['metaDataCache'];
		} else {
			$metadataCache = new $config['metaDataCacheClass'];
			$metadataCache->setNamespace($config['cachePrefix']);
		}

		$configuration->setMetadataCacheImpl($metadataCache);

		AnnotationDriver::registerAnnotationClasses();
		if (class_exists('\Gedmo\DoctrineExtensions')) {
			\Gedmo\DoctrineExtensions::registerAnnotations();

			$configuration->addFilter('soft-deleteable', 'Gedmo\SoftDeleteable\Filter\SoftDeleteableFilter');
		}
		$reader = new AnnotationReader;

		if ($config['cacheAnnotations'] == TRUE) {
			$reader = new CachedReader(
					$reader, $metadataCache, $config['debug']
			);
		}

		if ($config['indexAnnotations'] == TRUE) {
			$reader = new IndexedReader($reader);
		}

		$driverImpl = new AnnotationDriver($reader, $config['documentsDir']);

		$configuration->setMetadataDriverImpl($driverImpl);

		$configuration->setDefaultDB($config['dbname']);

		$mongo = new \MongoClient($config['uri'], $config['mongoOptions']);
		$connection = new Connection($mongo);
		$dm = DocumentManager::create($connection, $configuration, $config['eventManager']);

		return $dm;
	}


}