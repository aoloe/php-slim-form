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

$configuration = [
    'settings' => [
        'displayErrorDetails' => true,
    ],
];
$c = new \Slim\Container($configuration);
$app = new \Slim\App($c);

$container = $app->getContainer();

$container['formFactory'] = function ($container) {
    $validator = Validation::createValidator();
    // $csrfTokenManager = new CsrfTokenManager();
    $formFactory = Forms::createFormFactoryBuilder()
        // ->addExtension(new CsrfExtension($csrfTokenManager))
        ->addExtension(new ValidatorExtension($validator))
        ->getFormFactory();
    return $formFactory;
};

define('DEFAULT_FORM_THEME', 'bootstrap_3_layout.html.twig');
define('VENDOR_DIR', realpath(__DIR__ . '/../vendor'));
define('VENDOR_FORM_DIR', VENDOR_DIR . '/symfony/form');
define('VENDOR_VALIDATOR_DIR', VENDOR_DIR . '/symfony/validator');
define('VENDOR_TWIG_BRIDGE_DIR', VENDOR_DIR . '/symfony/twig-bridge');
define('VIEWS_DIR', realpath(__DIR__ . '/../view/template'));

$container['view'] = function ($container) {

    $translator = new Translator('de', new MessageSelector());
    $translator->addLoader('xlf', new XliffFileLoader());
    $translator->addResource('xlf', VENDOR_FORM_DIR . '/Resources/translations/validators.en.xlf', 'en', 'validators');
    $translator->addResource('xlf', VENDOR_FORM_DIR . '/Resources/translations/validators.en.xlf', 'en', 'validators');
    $translator->addResource('xlf', VENDOR_VALIDATOR_DIR . '/Resources/translations/validators.en.xlf', 'en', 'validators');
    $translator->addResource('xlf', VENDOR_VALIDATOR_DIR . '/Resources/translations/validators.de.xlf', 'de', 'validators');

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
    // echo($translator->trans('Firstname', [], 'validators'));
    echo('<pre>'.$translator->trans('Hello World!').'</pre>');
    echo('<pre>'.$translator->trans('This form should not contain extra fields.').'</pre>');
    echo('<pre>'.$translator->transChoice('This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.', 5, ['{{ limit }}' => 5], 'validators').'</pre>');

    // exit();

    // Set up the Translation component
    $twig = new Twig_Environment(new Twig_Loader_Filesystem(array(
        VIEWS_DIR,
        VENDOR_TWIG_BRIDGE_DIR . '/Resources/views/Form',
    )));
    
    $formEngine = new TwigRendererEngine(array(DEFAULT_FORM_THEME));
    $formEngine->setEnvironment($twig);
    $twig->addExtension(new TranslationExtension($translator));
    // bootstrap_3_layout.html.twig needs a trans filter... let's fake a translation engine
    // $filter = new Twig_SimpleFilter('trans', function ($string) {return $string;});
    // $twig->addFilter($filter);
    $twig->addExtension(
        new FormExtension(new TwigRenderer($formEngine))
    );

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
