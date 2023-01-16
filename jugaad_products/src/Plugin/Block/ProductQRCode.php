<?php

namespace Drupal\jugaad_products\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\node\NodeInterface;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Provides a 'ProductQRCode' block.
 *
 * @Block(
 *  id = "product_qr_code",
 *  admin_label = @Translation("Product QR Code block"),
 * )
 */
class ProductQRCode extends BlockBase implements ContainerFactoryPluginInterface {
/**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The config factory to get the installed themes.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file_system service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file url generator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, FileSystemInterface $file_system, ConfigFactoryInterface $config_factory, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->fileSystem = $file_system;
    $this->configFactory = $config_factory;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('file_system'),
      $container->get('config.factory'),
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      $nid = $node->id();
      $title = $node->getTitle();
      $app_link = $node->get('field_app_purchase_link')->getString();
      if (!empty($app_link)) {
        $image_path = $this->generateQRCode($app_link, $nid, $title);
        $image_src = $this->fileUrlGenerator->generateAbsoluteString('public://qr-code/' . $nid . '.png');
        $build['qr_code']['#markup'] = '<img src="' . $image_src . '"/>'; 
      }
    }

    return $build;
  }


  /**
   * {@inheritdoc}
   */
  public function generateQRCode($app_link, $nid, $title) {
    // QR Code Generation. 
    $qrCode = QrCode::create($app_link)
      ->setEncoding(new Encoding('UTF-8'))
      ->setErrorCorrectionLevel(new ErrorCorrectionLevelLow())
      ->setSize(300)
      ->setMargin(10)
      ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
      ->setForegroundColor(new Color(0, 0, 0))
      ->setBackgroundColor(new Color(255, 255, 255));

    if (!is_dir('public://qr-code/')) {
      $this->fileSystem->mkdir('public://qr-code');
    }

    // Create Logo.
    $logo = Logo::create(__DIR__.'/qrcode.png')->setResizeToWidth(50);
    // Create Label.
    $label = Label::create($title)->setTextColor(new Color(255, 0, 0));

    $writer = new PngWriter();

    $result = $writer->write($qrCode, $logo, $label);

    $qr_image_path = $this->fileSystem->realpath('public://') . '/qr-code/' . $nid . '.png';

    $result->saveToFile($qr_image_path);

    // Final URI
    $uri = $result->getDataUri();

    return $uri;
  }

}