<?php
/**
 * @author Björn Schießle <schiessle@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Encryption\Tests\Crypto;

use OCA\Encryption\Exceptions\PublicKeyMissingException;
use Test\TestCase;
use OCA\Encryption\Crypto\Encryption;

class EncryptionTest extends TestCase {

	/** @var Encryption */
	private $instance;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	private $keyManagerMock;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	private $cryptMock;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	private $utilMock;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	private $loggerMock;

	public function setUp() {
		parent::setUp();

		$this->cryptMock = $this->getMockBuilder('OCA\Encryption\Crypto\Crypt')
			->disableOriginalConstructor()
			->getMock();
		$this->utilMock = $this->getMockBuilder('OCA\Encryption\Util')
			->disableOriginalConstructor()
			->getMock();
		$this->keyManagerMock = $this->getMockBuilder('OCA\Encryption\KeyManager')
			->disableOriginalConstructor()
			->getMock();
		$this->loggerMock = $this->getMockBuilder('OCP\ILogger')
			->disableOriginalConstructor()
			->getMock();

		$this->instance = new Encryption($this->cryptMock, $this->keyManagerMock, $this->utilMock, $this->loggerMock);
	}

	/**
	 * test if public key from one of the recipients is missing
	 */
	public function testEndUser1() {
		$this->instance->begin('/foo/bar', 'user1', array(), array('users' => array('user1', 'user2', 'user3')));
		$this->endTest();
	}

	/**
	 * test if public key from owner is missing
	 *
	 * @expectedException \OCA\Encryption\Exceptions\PublicKeyMissingException
	 */
	public function testEndUser2() {
		$this->instance->begin('/foo/bar', 'user2', array(), array('users' => array('user1', 'user2', 'user3')));
		$this->endTest();
	}

	/**
	 * common part of testEndUser1 and testEndUser2
	 *
	 * @throws PublicKeyMissingException
	 */
	public function endTest() {
		// prepare internal variables
		$class = get_class($this->instance);
		$module = new \ReflectionClass($class);
		$isWriteOperation = $module->getProperty('isWriteOperation');
		$writeCache = $module->getProperty('writeCache');
		$isWriteOperation->setAccessible(true);
		$writeCache->setAccessible(true);
		$isWriteOperation->setValue($this->instance, true);
		$writeCache->setValue($this->instance, '');
		$isWriteOperation->setAccessible(false);
		$writeCache->setAccessible(false);

		$this->keyManagerMock->expects($this->any())
			->method('getPublicKey')
			->will($this->returnCallback([$this, 'getPublicKeyCallback']));
		$this->keyManagerMock->expects($this->any())
			->method('addSystemKeys')
			->will($this->returnCallback([$this, 'addSystemKeysCallback']));
		$this->cryptMock->expects($this->any())
			->method('multiKeyEncrypt')
			->willReturn(true);
		$this->cryptMock->expects($this->any())
			->method('setAllFileKeys')
			->willReturn(true);

		$this->instance->end('/foo/bar');
	}


	public function getPublicKeyCallback($uid) {
		if ($uid === 'user2') {
			throw new PublicKeyMissingException($uid);
		}
		return $uid;
	}

	public function addSystemKeysCallback($accessList, $publicKeys) {
		$this->assertSame(2, count($publicKeys));
		$this->assertArrayHasKey('user1', $publicKeys);
		$this->assertArrayHasKey('user3', $publicKeys);
		return $publicKeys;
	}

	/**
	 * @dataProvider dataProviderForTestGetPathToRealFile
	 */
	public function testGetPathToRealFile($path, $expected) {
		$this->assertSame($expected,
			\Test_Helper::invokePrivate($this->instance, 'getPathToRealFile', array($path))
		);
	}

	public function dataProviderForTestGetPathToRealFile() {
		return array(
			array('/user/files/foo/bar.txt', '/user/files/foo/bar.txt'),
			array('/user/files/foo.txt', '/user/files/foo.txt'),
			array('/user/files_versions/foo.txt.v543534', '/user/files/foo.txt'),
			array('/user/files_versions/foo/bar.txt.v5454', '/user/files/foo/bar.txt'),
		);
	}


}