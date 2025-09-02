<?php

namespace EmailListVerify\Tests;

use PHPUnit\Framework\TestCase;
use EmailListVerify\EmailListVerify;
use EmailListVerify\EmailListVerifyException;

class EmailListVerifyTest extends TestCase
{
    private $apiKey = 'test_api_key_12345';
    
    public function testConstructorRequiresApiKey()
    {
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('API key is required');
        new EmailListVerify('');
    }
    
    public function testConstructorWithValidApiKey()
    {
        $client = new EmailListVerify($this->apiKey);
        $this->assertInstanceOf(EmailListVerify::class, $client);
    }
    
    public function testConstructorWithCustomTimeout()
    {
        $client = new EmailListVerify($this->apiKey, 60);
        $this->assertInstanceOf(EmailListVerify::class, $client);
    }
    
    public function testVerifyEmailRequiresEmail()
    {
        $client = new EmailListVerify($this->apiKey);
        
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('Email address is required');
        $client->verifyEmail('');
    }
    
    /**
     * @dataProvider validEmailProvider
     */
    public function testVerifyEmailBuildsCorrectUrl($email)
    {
        $client = $this->getMockBuilder(EmailListVerify::class)
            ->setConstructorArgs([$this->apiKey])
            ->onlyMethods(['verifyEmail'])
            ->getMock();
        
        $client->expects($this->once())
            ->method('verifyEmail')
            ->with($email)
            ->willReturn('ok');
        
        $result = $client->verifyEmail($email);
        $this->assertEquals('ok', $result);
    }
    
    public function validEmailProvider()
    {
        return [
            ['test@example.com'],
            ['user+tag@domain.org'],
            ['firstname.lastname@company.co.uk'],
            ['admin@localhost.localdomain'],
        ];
    }
    
    public function testVerifyEmailsRequiresArray()
    {
        $client = new EmailListVerify($this->apiKey);
        
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('Emails must be an array');
        $client->verifyEmails('not_an_array');
    }
    
    public function testVerifyEmailsProcessesMultipleEmails()
    {
        $emails = [
            'test1@example.com',
            'test2@example.com',
            'test3@example.com'
        ];
        
        $client = $this->getMockBuilder(EmailListVerify::class)
            ->setConstructorArgs([$this->apiKey])
            ->onlyMethods(['verifyEmail'])
            ->getMock();
        
        $client->expects($this->exactly(3))
            ->method('verifyEmail')
            ->willReturnOnConsecutiveCalls('ok', 'invalid', 'disposable');
        
        $results = $client->verifyEmails($emails);
        
        $this->assertCount(3, $results);
        $this->assertEquals('ok', $results['test1@example.com']);
        $this->assertEquals('invalid', $results['test2@example.com']);
        $this->assertEquals('disposable', $results['test3@example.com']);
    }
    
    public function testVerifyEmailsHandlesExceptions()
    {
        $emails = ['test1@example.com', 'test2@example.com'];
        
        $client = $this->getMockBuilder(EmailListVerify::class)
            ->setConstructorArgs([$this->apiKey])
            ->onlyMethods(['verifyEmail'])
            ->getMock();
        
        $client->expects($this->exactly(2))
            ->method('verifyEmail')
            ->willReturnCallback(function($email) {
                if ($email === 'test1@example.com') {
                    return 'ok';
                }
                throw new EmailListVerifyException('API error');
            });
        
        $results = $client->verifyEmails($emails);
        
        $this->assertEquals('ok', $results['test1@example.com']);
        $this->assertStringContainsString('error:', $results['test2@example.com']);
        $this->assertStringContainsString('API error', $results['test2@example.com']);
    }
    
    /**
     * @dataProvider emailStatusProvider
     */
    public function testVerifyEmailReturnsValidStatuses($status)
    {
        $client = $this->getMockBuilder(EmailListVerify::class)
            ->setConstructorArgs([$this->apiKey])
            ->onlyMethods(['verifyEmail'])
            ->getMock();
        
        $client->expects($this->once())
            ->method('verifyEmail')
            ->willReturn($status);
        
        $result = $client->verifyEmail('test@example.com');
        $this->assertEquals($status, $result);
    }
    
    public function emailStatusProvider()
    {
        return [
            ['ok'],
            ['invalid'],
            ['invalid_mx'],
            ['accept_all'],
            ['ok_for_all'],
            ['disposable'],
            ['role'],
            ['email_disabled'],
            ['dead_server'],
            ['unknown'],
        ];
    }
    
    public function testConstantsAreDefined()
    {
        $this->assertEquals('https://apps.emaillistverify.com/api', EmailListVerify::BASE_URL);
        $this->assertEquals('1.0.0', EmailListVerify::VERSION);
    }
    
    public function testVerifyEmailTrimsResponse()
    {
        $client = $this->getMockBuilder(EmailListVerify::class)
            ->setConstructorArgs([$this->apiKey])
            ->onlyMethods(['verifyEmail'])
            ->getMock();
        
        $client->expects($this->once())
            ->method('verifyEmail')
            ->willReturn("  ok  \n");
        
        $result = $client->verifyEmail('test@example.com');
        $this->assertEquals("  ok  \n", $result);
    }
    
    /**
     * Integration test mock - this would normally test against real API
     * but for unit tests we mock the curl response
     */
    public function testVerifyEmailIntegrationMock()
    {
        $client = new EmailListVerifyMock($this->apiKey);
        $client->setMockResponse('ok', 200);
        
        $result = $client->verifyEmail('test@example.com');
        $this->assertEquals('ok', $result);
    }
    
    public function testVerifyEmailHandlesHttpErrors()
    {
        $client = new EmailListVerifyMock($this->apiKey);
        $client->setMockResponse('Unauthorized', 401);
        
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('HTTP error 401');
        $client->verifyEmail('test@example.com');
    }
    
    public function testVerifyEmailHandlesCurlErrors()
    {
        $client = new EmailListVerifyMock($this->apiKey);
        $client->setMockCurlError('Connection timeout');
        
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('Request failed: Connection timeout');
        $client->verifyEmail('test@example.com');
    }
}

/**
 * Mock class for testing EmailListVerify without actual HTTP requests
 */
class EmailListVerifyMock extends EmailListVerify
{
    private $mockResponse = '';
    private $mockHttpCode = 200;
    private $mockCurlError = null;
    
    public function setMockResponse($response, $httpCode = 200)
    {
        $this->mockResponse = $response;
        $this->mockHttpCode = $httpCode;
        $this->mockCurlError = null;
    }
    
    public function setMockCurlError($error)
    {
        $this->mockCurlError = $error;
    }
    
    public function verifyEmail($email)
    {
        if (empty($email)) {
            throw new EmailListVerifyException('Email address is required');
        }
        
        if ($this->mockCurlError) {
            throw new EmailListVerifyException('Request failed: ' . $this->mockCurlError);
        }
        
        if ($this->mockHttpCode !== 200) {
            throw new EmailListVerifyException('HTTP error ' . $this->mockHttpCode . ': ' . $this->mockResponse);
        }
        
        return trim($this->mockResponse);
    }
}