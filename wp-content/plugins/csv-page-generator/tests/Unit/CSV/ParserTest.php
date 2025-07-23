<?php
/**
 * Unit tests for CSV Parser class
 *
 * @package ReasonDigital\CSVPageGenerator\Tests\Unit\CSV
 */

namespace ReasonDigital\CSVPageGenerator\Tests\Unit\CSV;

use PHPUnit\Framework\TestCase;
use ReasonDigital\CSVPageGenerator\CSV\Parser;
use ReasonDigital\CSVPageGenerator\Utils\Logger;
use Brain\Monkey;
use Mockery;

/**
 * Test the CSV Parser functionality
 */
class ParserTest extends TestCase {

	/**
	 * Set up test environment before each test
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Clean up after each test
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test parser instantiation
	 */
	public function test_parser_can_be_instantiated() {
		$logger = Mockery::mock( Logger::class );
		$parser = new Parser( $logger );
		$this->assertInstanceOf( Parser::class, $parser );
	}

	/**
	 * Test getting supported encodings
	 */
	public function test_get_supported_encodings() {
		$logger = Mockery::mock( Logger::class );
		$parser = new Parser( $logger );

		$encodings = $parser->get_supported_encodings();

		$this->assertIsArray( $encodings );
		$this->assertNotEmpty( $encodings );
		$this->assertContains( 'UTF-8', $encodings );
	}

	/**
	 * Test file validation with nonexistent file
	 */
	public function test_validate_nonexistent_file() {
		$logger = Mockery::mock( Logger::class );
		$parser = new Parser( $logger );

		$result = $parser->validate_file( '/path/to/nonexistent/file.csv' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'valid', $result );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Test parsing file that doesn't exist throws exception
	 */
	public function test_parse_nonexistent_file_throws_exception() {
		$logger = Mockery::mock( Logger::class );
		$parser = new Parser( $logger );

		$this->expectException( \Exception::class );
		$parser->parse_file( '/path/to/nonexistent/file.csv' );
	}
}
