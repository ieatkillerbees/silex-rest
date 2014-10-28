<?php

namespace Squinones\ApiExample\Models;

class BookRepository {

	const BOOK_CLASS = 'Squinones\ApiExample\Models\Book';

	private $db;
	private $table = 'books';

	public function __construct(\PDO $db)
	{
		$this->db = $db;
	}

	public function getAll()
	{
		$result = $this->db->query('SELECT * FROM books');
		return $result->fetchAll(\PDO::FETCH_CLASS, self::BOOK_CLASS);
	}

	public function get($id)
	{
		$result = $this->db->prepare('SELECT * FROM books WHERE id = :id');
		$result->setFetchMode(\PDO::FETCH_CLASS, self::BOOK_CLASS);
		$result->execute(['id' => $id]);
		return $result->fetch(\PDO::FETCH_CLASS);
	}

	public function save(Book $book)
	{
		$query = $book->getId() ? 'UPDATE books SET title = :title, author = :author WHERE id = :id'
			                    : 'INSERT INTO books (id, title, author) VALUES (:id, :title, :author)';
 		$query = $this->db->prepare($query);
 	 	$query->execute(['id' => $book->getId(), 'title' => $book->getTitle(), 'author' => $book->getAuthor()]);
		return $this->db->lastInsertId();
	}

	public function delete(Book $book)
	{
		$query = $this->db->prepare('DELETE FROM books WHERE id = :id');
		$query->execute(['id' => $book->getId()]);
	}
}