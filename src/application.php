<?php

namespace Squinones\ApiExample;

use Popshack\Silex\Provider\Hal\HalServiceProvider;
use Silex\Provider\SerializerServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Squinones\ApiExample\Models\Book;
use Squinones\ApiExample\Models\BookRepository;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app = new Application();

$app['format'] = 'json';

$app['db'] = $app->share(function() {
	$conn = 'sqlite:' . __DIR__ . '/../data/silex-rest.db';
	return new \PDO($conn, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
});

$app['repo.books'] = $app->share(function ($app) {
	return new BookRepository($app['db']);
});

/**
 * Given a book id, return a Book object or throw not found
 *
 * @param int $id
 * @return Book
 * @throws NotFoundHttpException
 */
$app['converters.book'] = $app->protect(function ($id) use ($app) {
	$book = $app['repo.books']->get($id);
	if (!$book) {
		throw new NotFoundHttpException('Book '.$id.' not found');
	}
	return $book;
});

$app['resources.book'] = $app->protect(function (Book $book) use ($app) {
	return [
		'id' => $book->getId(),
		'title' => $book->getTitle(),
		'author' => $book->getAuthor(),
		'_links' => [
			'self' => [
				'href' => $app['url_generator']->generate('book', [ 'book' => $book->getId() ])
			]
		]
	];
});

$app->register(new SerializerServiceProvider());
$app->register(new UrlGeneratorServiceProvider());

/**
 * For JSON requests with bodies, populate data from request
 */
$app->before(function (Request $request) {
	if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
		$data = json_decode($request->getContent(), true);
		$request->request->replace(is_array($data) ? $data : array());
	}
});

$app->after(function (Request $request, Response $response) use ($app) {
	$response->headers->set('Content-Type', 'application/json+hal');
});

$app->after(function (Request $request, Response $response) use ($app) {
	if (!$response->getMaxAge()) {
		$response->setMaxAge(3600);
	}
	$response->setPublic();
});

$app->error(function (HttpException $exc, $code) {
	return new Response(null, $code);
});

// -- Controllers
$app->get('/books', function (Application $app) {
	$books = $app['repo.books']->getAll();
	$collection = [
		"count" => count($books),
		"total" => count($books),
		"_embedded" => [
			"books" => array_map($app['resources.book'], $books),
		],
		"_links" => [ 'self' => [ 'href' => $app['url_generator']->generate('books') ] ]
	];
	return $app['serializer']->serialize($collection, $app['format']);
})->after(function (Request $request, Response $response) {
	$response->setMaxAge(60);
})->bind('books');

$app->get('/books/{book}', function (Application $app, Book $book) {
	return $app['serializer']->serialize($app['resources.book']($book), $app['format']);
})->convert('book', $app['converters.book'])
  ->bind('book');

$app->post('/books', function (Application $app, Request $request) {
	$book = new Book();
	$book->setAuthor($request->request->get('author'));
	$book->setTitle($request->request->get('title'));
	$id = $app['repo.books']->save($book);

	$response = new Response(null, 201);
	$response->headers->set('Location', $app['url_generator']->generate('book', ['book' => $id]));
	return $response;
});

$app->put('/books/{book}', function (Application $app, Request $request, Book $book) {
	$book->setAuthor($request->request->get('author'));
	$book->setTitle($request->request->get('title'));
	$app['repo.books']->save($book);

	return new Response(null, 200);
})->convert('book', $app['converters.book']);

$app->delete('/books/{book}', function (Application $app, Book $book) {
	$app['repo.books']->delete($book);
	return new Response(null, 204);
})->convert('book', $app['converters.book']);