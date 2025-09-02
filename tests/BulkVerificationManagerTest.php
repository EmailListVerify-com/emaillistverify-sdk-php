<?php

namespace EmailListVerify\Tests;

use PHPUnit\Framework\TestCase;
use EmailListVerify\BulkVerificationManager;
use EmailListVerify\EmailListVerifyClient;
use EmailListVerify\EmailListVerifyException;

class BulkVerificationManagerTest extends TestCase
{
    private $clientMock;
    private $manager;
    private $tempFiles = [];
    
    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(EmailListVerifyClient::class);
        $this->manager = new BulkVerificationManager($this->clientMock);
    }
    
    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];
    }
    
    private function createTempCsvFile($content = "email\ntest@example.com\n")
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'bulk_test_');
        file_put_contents($tempFile, $content);
        $this->tempFiles[] = $tempFile;
        return $tempFile;
    }
    
    public function testConstructorRequiresClient()
    {
        $manager = new BulkVerificationManager($this->clientMock);
        $this->assertInstanceOf(BulkVerificationManager::class, $manager);
    }
    
    public function testProcessCsvFileWithWaitForCompletion()
    {
        $inputFile = $this->createTempCsvFile();
        $outputFile = tempnam(sys_get_temp_dir(), 'output_');
        $this->tempFiles[] = $outputFile;
        
        $fileId = 'file_id_12345';
        $csvResults = "email,status\ntest@example.com,ok\n";
        $finalStatus = ['status' => 'completed', 'progress' => 100];
        
        $this->clientMock->expects($this->once())
            ->method('bulkUpload')
            ->with($inputFile)
            ->willReturn($fileId);
        
        $this->clientMock->expects($this->once())
            ->method('waitForBulkCompletion')
            ->with($fileId)
            ->willReturn($finalStatus);
        
        $this->clientMock->expects($this->once())
            ->method('downloadBulkResult')
            ->with($fileId, 'all')
            ->willReturn($csvResults);
        
        $jobInfo = $this->manager->processCsvFile($inputFile, $outputFile, true);
        
        $this->assertEquals($fileId, $jobInfo['file_id']);
        $this->assertEquals($inputFile, $jobInfo['input_file']);
        $this->assertEquals($outputFile, $jobInfo['output_file']);
        $this->assertEquals('completed', $jobInfo['status']);
        $this->assertEquals($finalStatus, $jobInfo['final_status']);
        $this->assertArrayHasKey('start_time', $jobInfo);
        $this->assertArrayHasKey('end_time', $jobInfo);
        
        $this->assertEquals($csvResults, file_get_contents($outputFile));
    }
    
    public function testProcessCsvFileWithoutWaitForCompletion()
    {
        $inputFile = $this->createTempCsvFile();
        $outputFile = tempnam(sys_get_temp_dir(), 'output_');
        $this->tempFiles[] = $outputFile;
        
        $fileId = 'file_id_67890';
        
        $this->clientMock->expects($this->once())
            ->method('bulkUpload')
            ->with($inputFile)
            ->willReturn($fileId);
        
        $this->clientMock->expects($this->never())
            ->method('waitForBulkCompletion');
        
        $this->clientMock->expects($this->never())
            ->method('downloadBulkResult');
        
        $jobInfo = $this->manager->processCsvFile($inputFile, $outputFile, false);
        
        $this->assertEquals($fileId, $jobInfo['file_id']);
        $this->assertEquals('processing', $jobInfo['status']);
        $this->assertArrayHasKey('start_time', $jobInfo);
        $this->assertArrayNotHasKey('end_time', $jobInfo);
        $this->assertArrayNotHasKey('final_status', $jobInfo);
    }
    
    public function testGetJobStatus()
    {
        $inputFile = $this->createTempCsvFile();
        $outputFile = tempnam(sys_get_temp_dir(), 'output_');
        $this->tempFiles[] = $outputFile;
        
        $fileId = 'file_id_11111';
        $statusResponse = ['status' => 'processing', 'progress' => 75];
        
        $this->clientMock->expects($this->once())
            ->method('bulkUpload')
            ->willReturn($fileId);
        
        $this->manager->processCsvFile($inputFile, $outputFile, false);
        
        $this->clientMock->expects($this->once())
            ->method('getBulkStatus')
            ->with($fileId)
            ->willReturn($statusResponse);
        
        $jobStatus = $this->manager->getJobStatus($fileId);
        
        $this->assertEquals($fileId, $jobStatus['file_id']);
        $this->assertEquals($statusResponse, $jobStatus['last_status']);
    }
    
    public function testGetJobStatusWithUnknownFileId()
    {
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('Unknown job ID: unknown_file_id');
        
        $this->manager->getJobStatus('unknown_file_id');
    }
    
    public function testGetActiveJobs()
    {
        $inputFile1 = $this->createTempCsvFile();
        $outputFile1 = tempnam(sys_get_temp_dir(), 'output1_');
        $this->tempFiles[] = $outputFile1;
        
        $inputFile2 = $this->createTempCsvFile();
        $outputFile2 = tempnam(sys_get_temp_dir(), 'output2_');
        $this->tempFiles[] = $outputFile2;
        
        $this->clientMock->expects($this->exactly(2))
            ->method('bulkUpload')
            ->willReturnOnConsecutiveCalls('file_id_1', 'file_id_2');
        
        $this->manager->processCsvFile($inputFile1, $outputFile1, false);
        $this->manager->processCsvFile($inputFile2, $outputFile2, false);
        
        $activeJobs = $this->manager->getActiveJobs();
        
        $this->assertCount(2, $activeJobs);
        $this->assertArrayHasKey('file_id_1', $activeJobs);
        $this->assertArrayHasKey('file_id_2', $activeJobs);
    }
    
    public function testProcessCsvFileHandlesUploadException()
    {
        $inputFile = $this->createTempCsvFile();
        $outputFile = tempnam(sys_get_temp_dir(), 'output_');
        $this->tempFiles[] = $outputFile;
        
        $this->clientMock->expects($this->once())
            ->method('bulkUpload')
            ->willThrowException(new EmailListVerifyException('Upload failed'));
        
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('Upload failed');
        
        $this->manager->processCsvFile($inputFile, $outputFile);
    }
    
    public function testProcessCsvFileHandlesWaitException()
    {
        $inputFile = $this->createTempCsvFile();
        $outputFile = tempnam(sys_get_temp_dir(), 'output_');
        $this->tempFiles[] = $outputFile;
        
        $fileId = 'file_id_error';
        
        $this->clientMock->expects($this->once())
            ->method('bulkUpload')
            ->willReturn($fileId);
        
        $this->clientMock->expects($this->once())
            ->method('waitForBulkCompletion')
            ->willThrowException(new EmailListVerifyException('Verification failed'));
        
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('Verification failed');
        
        $this->manager->processCsvFile($inputFile, $outputFile, true);
    }
    
    public function testProcessCsvFileHandlesDownloadException()
    {
        $inputFile = $this->createTempCsvFile();
        $outputFile = tempnam(sys_get_temp_dir(), 'output_');
        $this->tempFiles[] = $outputFile;
        
        $fileId = 'file_id_download_error';
        
        $this->clientMock->expects($this->once())
            ->method('bulkUpload')
            ->willReturn($fileId);
        
        $this->clientMock->expects($this->once())
            ->method('waitForBulkCompletion')
            ->willReturn(['status' => 'completed']);
        
        $this->clientMock->expects($this->once())
            ->method('downloadBulkResult')
            ->willThrowException(new EmailListVerifyException('Download failed'));
        
        $this->expectException(EmailListVerifyException::class);
        $this->expectExceptionMessage('Download failed');
        
        $this->manager->processCsvFile($inputFile, $outputFile, true);
    }
    
    public function testMultipleJobsTracking()
    {
        $jobs = [];
        for ($i = 1; $i <= 3; $i++) {
            $inputFile = $this->createTempCsvFile("email\ntest$i@example.com\n");
            $outputFile = tempnam(sys_get_temp_dir(), "output_$i");
            $this->tempFiles[] = $outputFile;
            
            $fileId = "file_id_$i";
            
            $this->clientMock->expects($this->at($i - 1))
                ->method('bulkUpload')
                ->willReturn($fileId);
            
            $jobInfo = $this->manager->processCsvFile($inputFile, $outputFile, false);
            $jobs[$fileId] = $jobInfo;
        }
        
        $activeJobs = $this->manager->getActiveJobs();
        $this->assertCount(3, $activeJobs);
        
        foreach ($jobs as $fileId => $expectedJob) {
            $this->assertArrayHasKey($fileId, $activeJobs);
            $this->assertEquals($expectedJob['input_file'], $activeJobs[$fileId]['input_file']);
            $this->assertEquals($expectedJob['output_file'], $activeJobs[$fileId]['output_file']);
        }
    }
    
    public function testJobStatusUpdate()
    {
        $inputFile = $this->createTempCsvFile();
        $outputFile = tempnam(sys_get_temp_dir(), 'output_');
        $this->tempFiles[] = $outputFile;
        
        $fileId = 'file_id_status_test';
        
        $this->clientMock->expects($this->once())
            ->method('bulkUpload')
            ->willReturn($fileId);
        
        $this->manager->processCsvFile($inputFile, $outputFile, false);
        
        $statusUpdates = [
            ['status' => 'processing', 'progress' => 25],
            ['status' => 'processing', 'progress' => 50],
            ['status' => 'processing', 'progress' => 75],
            ['status' => 'completed', 'progress' => 100],
        ];
        
        foreach ($statusUpdates as $index => $statusUpdate) {
            $this->clientMock->expects($this->at($index))
                ->method('getBulkStatus')
                ->with($fileId)
                ->willReturn($statusUpdate);
            
            $jobStatus = $this->manager->getJobStatus($fileId);
            $this->assertEquals($statusUpdate, $jobStatus['last_status']);
        }
    }
    
    public function testProcessLargeCsvFile()
    {
        $csvContent = "email\n";
        for ($i = 1; $i <= 1000; $i++) {
            $csvContent .= "user$i@example.com\n";
        }
        
        $inputFile = $this->createTempCsvFile($csvContent);
        $outputFile = tempnam(sys_get_temp_dir(), 'large_output_');
        $this->tempFiles[] = $outputFile;
        
        $fileId = 'file_id_large';
        $resultContent = str_replace("\n", ",ok\n", $csvContent) . ",ok";
        
        $this->clientMock->expects($this->once())
            ->method('bulkUpload')
            ->with($inputFile)
            ->willReturn($fileId);
        
        $this->clientMock->expects($this->once())
            ->method('waitForBulkCompletion')
            ->willReturn(['status' => 'completed', 'total' => 1000, 'processed' => 1000]);
        
        $this->clientMock->expects($this->once())
            ->method('downloadBulkResult')
            ->willReturn($resultContent);
        
        $jobInfo = $this->manager->processCsvFile($inputFile, $outputFile, true);
        
        $this->assertEquals('completed', $jobInfo['status']);
        $this->assertEquals(1000, $jobInfo['final_status']['total']);
        $this->assertTrue(file_exists($outputFile));
    }
}