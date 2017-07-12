<?php

error_reporting(-1);
ini_set('display_errors', 1);

require '../vendor/autoload.php';

use Symfony\Component\Validator\Validation;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;

use Symfony\Bridge\Twig\Form\TwigRenderer;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bridge\Twig\Extension\FormExtension;

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Bridge\Twig\Extension\TranslationExtension;

use Symfony\Component\Translation\Loader\ArrayLoader;

class TwigRendererRuntimeLoader implements \Twig\RuntimeLoader\RuntimeLoaderInterface {
    /** @var TwigRenderer */
    private $twigRenderer;

    /**
     * @param TwigRenderer $twigRenderer
     */
    public function __construct(TwigRenderer $twigRenderer)
    {
        $this->twigRenderer = $twigRenderer;
    }

    public function load($class)
    {
        if ($class === TwigRenderer::class) {
            return $this->twigRenderer;
        }

        return null;
    }
}

$configuration = [
    'settings' => [
        'displayErrorDetails' => true,
    ],
];
$c = new \Slim\Container($configuration);
$app = new \Slim\App($c);

define('DEFAULT_FORM_THEME', 'bootstrap_3_layout.html.twig');
define('VENDOR_DIR', realpath(__DIR__ . '/../vendor'));
define('VENDOR_FORM_DIR', VENDOR_DIR . '/symfony/form');
define('VENDOR_VALIDATOR_DIR', VENDOR_DIR . '/symfony/validator');
define('VENDOR_TWIG_BRIDGE_DIR', VENDOR_DIR . '/symfony/twig-bridge');
define('VIEWS_DIR', realpath(__DIR__ . '/../view/template'));

$container = $app->getContainer();

$container['translator'] = function ($container) {
    $translator = new Translator('de', new MessageSelector());
    $translator->addLoader('xlf', new XliffFileLoader());
    // $translator->addResource('xlf', VENDOR_FORM_DIR . '/Resources/translations/validators.en.xlf', 'en', 'forms');
    $translator->addResource('xlf', VENDOR_FORM_DIR . '/Resources/translations/validators.en.xlf', 'en');
    // $translator->addResource('xlf', VENDOR_FORM_DIR . '/Resources/translations/validators.de.xlf', 'de', 'forms');
    $translator->addResource('xlf', VENDOR_FORM_DIR . '/Resources/translations/validators.de.xlf', 'de');
    // $translator->addResource('xlf', VENDOR_VALIDATOR_DIR . '/Resources/translations/validators.en.xlf', 'en', 'validators');
    $translator->addResource('xlf', VENDOR_VALIDATOR_DIR . '/Resources/translations/validators.en.xlf', 'en');
    // $translator->addResource('xlf', VENDOR_VALIDATOR_DIR . '/Resources/translations/validators.de.xlf', 'de', 'validators');
    $translator->addResource('xlf', VENDOR_VALIDATOR_DIR . '/Resources/translations/validators.de.xlf', 'de');

    $translator->addLoader('array', new ArrayLoader());
    $translator->addResource('array', array(
            'Hello World!' => 'Hello',
        ),
        'en'
    );
    $translator->addResource('array', array(
            'Hello World!' => 'Grüezi',
        ),
        'de'
    );
    $translator->addResource('array', array(
            'Firstname' => 'Firstname',
        ),
        'en'
    );
    $translator->addResource('array', array(
            'Firstname' => 'Vorname',
        ),
        'de'
    );

    return $translator;
};

$container['formFactory'] = function ($container) {
    $validator = Validation::createValidatorBuilder()
        ->setTranslator($container->translator)
        ->getValidator();
    // $csrfTokenManager = new CsrfTokenManager();
    $formFactory = Forms::createFormFactoryBuilder()
        ->addExtension(new ValidatorExtension($validator))
        ->getFormFactory();
    return $formFactory;
};

$container['view'] = function ($container) {

    // Set up the Translation component
    $twig = new Twig_Environment(new Twig_Loader_Filesystem(array(
        VIEWS_DIR,
        VENDOR_TWIG_BRIDGE_DIR . '/Resources/views/Form',
    )));

    $formEngine = new TwigRendererEngine(array(DEFAULT_FORM_THEME));
    $formEngine->setEnvironment($twig);
    $twig->addExtension(new TranslationExtension($container->translator));
    // bootstrap_3_layout.html.twig needs a trans filter... let's fake a translation engine
    // $filter = new Twig_SimpleFilter('trans', function ($string) {return $string;});
    // $twig->addFilter($filter);
    $renderer = new TwigRenderer($formEngine);
    $twig->addExtension(new FormExtension($renderer));
    $twig->addRuntimeLoader(new TwigRendererRuntimeLoader($renderer));

    return $twig;
};

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

$app->any('/hello/{name}', function ($request, $response, $args) {
    $response->write("Hello, " . $args['name']);

    $form = $this->formFactory->createBuilder()
        ->setAction('')
        ->add('gender', ChoiceType::class, array(
            'choices'  => array(
                'Mrs.' => 'f',
                'Mr.' => 'm',
            ))
        )
        ->add('message', TextareaType::class, array(
            'required' => true
        ))
        ->add('firstname', TextType::class)
        ->add('lastname', TextType::class, array(
            'constraints' => array(
                new NotBlank(),
                new Length(array('min' => 4)),
            )
        ))
        ->add('save', SubmitType::class, array(
            'attr'  => array(
                'class' => 'btn-primary btn-lg',
            )
        ))
        ->getForm();

    if ($request->getMethod() == 'POST') {
        $form->handleRequest();
        if ($form->isValid()) {
            $data = $form->getData();
        }
    }

    return $this->view->render(
        'index.html',
        array(
            'form' => $form->createView(),
        )
    );
});

$app->run();
