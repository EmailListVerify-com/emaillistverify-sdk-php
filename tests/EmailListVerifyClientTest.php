<?php

namespace EmailListVerify\Tests;

use PHPUnit\Framework\TestCase;
use EmailListVerify\EmailListVerifyClient;
use EmailListVerify\EmailListVerifyException;

class EmailListVerifyClientTest extends TestCase
{
    private $apiKey = 'test_api_key_12345';
    private $client;
    
    protected function setUp(): void
    {
        $this->client = $this->getMockBuilder(EmailListVerifyClient::class)
            ->setConstructorArgs([$this->apiKey])
            ->onlyMethods(['makeRequest'])
            ->getMock();
    }
    
    public function testConstructorRequiresApiKey()
    {
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('API key is required');
        new EmailListVerifyClient('');
    }
    
    public function testConstructorSetsApiKeyAndTimeout()
    {
        $client = new EmailListVerifyClient($this->apiKey, 45);
        $this->assertInstanceOf(EmailListVerifyClient::class, $client);
    }
    
    public function testVerifyEmailRequiresEmail()
    {
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('Email address is required');
        $this->client->verifyEmail('');
    }
    
    public function testVerifyEmailReturnsFormattedResult()
    {
        $this->client->expects($this->once())
            ->method('makeRequest')
            ->with('verifyEmail', 'GET', ['email' => 'test@example.com'])
            ->willReturn('ok');
        
        $result = $this->client->verifyEmail('test@example.com');
        
        $this->assertIsArray($result);
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals('ok', $result['status']);
        $this->assertArrayHasKey('timestamp', $result);
    }
    
    public function testVerifyEmailHandlesJsonResponse()
    {
        $jsonResponse = ['status' => 'valid', 'email' => 'test@example.com'];
        
        $this->client->expects($this->once())
            ->method('makeRequest')
            ->willReturn($jsonResponse);
        
        $result = $this->client->verifyEmail('test@example.com');
        $this->assertEquals($jsonResponse, $result);
    }
    
    public function testVerifyEmailDetailed()
    {
        $detailedResponse = [
            'email' => 'test@example.com',
            'status' => 'ok',
            'domain' => 'example.com',
            'mx_found' => true,
            'mx_record' => 'mail.example.com',
            'disposable' => false,
            'role' => false,
            'free' => false
        ];
        
        $this->client->expects($this->once())
            ->method('makeRequest')
            ->with('verifyEmailDetailed', 'GET', ['email' => 'test@example.com'])
            ->willReturn($detailedResponse);
        
        $result = $this->client->verifyEmailDetailed('test@example.com');
        $this->assertEquals($detailedResponse, $result);
    }
    
    public function testVerifyEmailDetailedRequiresEmail()
    {
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('Email address is required');
        $this->client->verifyEmailDetailed('');
    }
    
    public function testGetCredits()
    {
        $creditsResponse = [
            'credits' => 10000,
            'used' => 2500,
            'remaining' => 7500
        ];
        
        $this->client->expects($this->once())
            ->method('makeRequest')
            ->with('getCredits')
            ->willReturn($creditsResponse);
        
        $result = $this->client->getCredits();
        $this->assertEquals($creditsResponse, $result);
    }
    
    public function testBulkUploadRequiresExistingFile()
    {
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('File not found: /non/existent/file.csv');
        $this->client->bulkUpload('/non/existent/file.csv');
    }
    
    public function testBulkUploadWithValidFile()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_emails');
        file_put_contents($tempFile, "email\ntest@example.com\n");
        
        $this->client->expects($this->once())
            ->method('makeRequest')
            ->willReturn('file_id_12345');
        
        $result = $this->client->bulkUpload($tempFile);
        $this->assertEquals('file_id_12345', $result);
        
        unlink($tempFile);
    }
    
    public function testBulkUploadWithCustomFilename()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_emails');
        file_put_contents($tempFile, "email\ntest@example.com\n");
        
        $this->client->expects($this->once())
            ->method('makeRequest')
            ->with(
                'verifApiFile',
                'POST',
                ['filename' => 'custom_file.csv'],
                [],
                $this->anything()
            )
            ->willReturn(['file_id' => 'file_id_98765']);
        
        $result = $this->client->bulkUpload($tempFile, 'custom_file.csv');
        $this->assertEquals('file_id_98765', $result);
        
        unlink($tempFile);
    }
    
    public function testGetBulkStatusRequiresFileId()
    {
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('File ID is required');
        $this->client->getBulkStatus('');
    }
    
    public function testGetBulkStatus()
    {
        $statusResponse = [
            'file_id' => 'file_id_12345',
            'status' => 'processing',
            'progress' => 45,
            'total' => 1000,
            'processed' => 450
        ];
        
        $this->client->expects($this->once())
            ->method('makeRequest')
            ->with('getApiFileInfo', 'GET', ['file_id' => 'file_id_12345'])
            ->willReturn($statusResponse);
        
        $result = $this->client->getBulkStatus('file_id_12345');
        $this->assertEquals($statusResponse, $result);
    }
    
    public function testDownloadBulkResultRequiresFileId()
    {
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('File ID is required');
        $this->client->downloadBulkResult('');
    }
    
    public function testDownloadBulkResultInvalidType()
    {
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage("result_type must be 'all' or 'clean'");
        $this->client->downloadBulkResult('file_id_12345', 'invalid');
    }
    
    public function testDownloadBulkResultAll()
    {
        $csvContent = "email,status\ntest@example.com,ok\ninvalid@test.com,invalid\n";
        
        $this->client->expects($this->once())
            ->method('makeRequest')
            ->with('downloadApiFile', 'GET', ['file_id' => 'file_id_12345'])
            ->willReturn($csvContent);
        
        $result = $this->client->downloadBulkResult('file_id_12345', 'all');
        $this->assertEquals($csvContent, $result);
    }
    
    public function testDownloadBulkResultClean()
    {
        $csvContent = "email\ntest@example.com\nvalid@domain.org\n";
        
        $this->client->expects($this->once())
            ->method('makeRequest')
            ->with('downloadCleanFile', 'GET', ['file_id' => 'file_id_12345'])
            ->willReturn($csvContent);
        
        $result = $this->client->downloadBulkResult('file_id_12345', 'clean');
        $this->assertEquals($csvContent, $result);
    }
    
    public function testVerifyBatch()
    {
        $emails = ['test1@example.com', 'test2@example.com', 'test3@example.com'];
        
        $this->client->expects($this->exactly(3))
            ->method('makeRequest')
            ->willReturnOnConsecutiveCalls('ok', 'invalid', 'disposable');
        
        $results = $this->client->verifyBatch($emails, 10);
        
        $this->assertCount(3, $results);
        $this->assertEquals('test1@example.com', $results[0]['email']);
        $this->assertEquals('ok', $results[0]['status']);
        $this->assertEquals('test2@example.com', $results[1]['email']);
        $this->assertEquals('invalid', $results[1]['status']);
        $this->assertEquals('test3@example.com', $results[2]['email']);
        $this->assertEquals('disposable', $results[2]['status']);
    }
    
    public function testVerifyBatchHandlesErrors()
    {
        $emails = ['test1@example.com', 'test2@example.com'];
        
        $this->client->expects($this->exactly(2))
            ->method('makeRequest')
            ->willReturnCallback(function() {
                static $count = 0;
                $count++;
                if ($count === 1) {
                    return 'ok';
                }
                throw new EmailListVerifyException('API limit reached');
            });
        
        $results = $this->client->verifyBatch($emails);
        
        $this->assertEquals('ok', $results[0]['status']);
        $this->assertEquals('error', $results[1]['status']);
        $this->assertEquals('API limit reached', $results[1]['error']);
    }
    
    public function testWaitForBulkCompletionSuccess()
    {
        $this->client->expects($this->exactly(2))
            ->method('makeRequest')
            ->willReturnOnConsecutiveCalls(
                ['status' => 'processing', 'progress' => 50],
                ['status' => 'completed', 'progress' => 100]
            );
        
        $result = $this->client->waitForBulkCompletion('file_id_12345', 1, 10);
        $this->assertEquals('completed', $result['status']);
    }
    
    public function testWaitForBulkCompletionFailed()
    {
        $this->client->expects($this->once())
            ->method('makeRequest')
            ->willReturn(['status' => 'failed', 'error' => 'Invalid file format']);
        
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('Bulk verification failed: Invalid file format');
        $this->client->waitForBulkCompletion('file_id_12345', 1, 10);
    }
    
    public function testWaitForBulkCompletionTimeout()
    {
        $this->client->expects($this->any())
            ->method('makeRequest')
            ->willReturn(['status' => 'processing', 'progress' => 50]);
        
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('Timeout waiting for bulk verification (waited 2s)');
        $this->client->waitForBulkCompletion('file_id_12345', 1, 2);
    }
    
    public function testConstantsAreDefined()
    {
        $this->assertEquals('https://apps.emaillistverify.com/api', EmailListVerifyClient::BASE_URL);
        $this->assertEquals('1.0.0', EmailListVerifyClient::VERSION);
    }
}