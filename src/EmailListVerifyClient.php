<?php
/**
 * EmailListVerify PHP SDK
 * Simple PHP wrapper for EmailListVerify REST API
 */

namespace EmailListVerify;

use Exception;

class EmailListVerifyException extends Exception {}

class EmailListVerify
{
    const BASE_URL = 'https://apps.emaillistverify.com/api';
    const VERSION = '1.0.0';
    
    private $apiKey;
    private $timeout;
    
    /**
     * Initialize EmailListVerify client
     * 
     * @param string $apiKey Your EmailListVerify API key
     * @param int $timeout Request timeout in seconds (default: 30)
     * @throws EmailListVerifyException
     */
    public function __construct($apiKey, $timeout = 30)
    {
        if (empty($apiKey)) {
            throw new EmailListVerifyException('API key is required');
        }
        
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }
    
    /**
     * Make HTTP request to API
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $params Query parameters
     * @param array $data POST data
     * @param array $files Files to upload
     * @return mixed API response
     * @throws EmailListVerifyException
     */
    private function makeRequest($endpoint, $method = 'GET', $params = [], $data = [], $files = [])
    {
        $url = self::BASE_URL . '/' . $endpoint;
        
        // Add API key to params
        $params['secret'] = $this->apiKey;
        
        // Build query string for GET params
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'EmailListVerify-PHP-SDK/' . self::VERSION);
        
        // Set method and data
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            
            if (!empty($files)) {
                // Handle file upload
                $postData = $data;
                foreach ($files as $key => $file) {
                    if (is_array($file)) {
                        $postData[$key] = new \CURLFile($file['path'], $file['type'], $file['name']);
                    } else {
                        $postData[$key] = new \CURLFile($file);
                    }
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            } elseif (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new EmailListVerifyException('Request failed: ' . $error);
        }
        
        if ($httpCode >= 400) {
            throw new EmailListVerifyException('HTTP error ' . $httpCode . ': ' . $response);
        }
        
        // Try to decode JSON response
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        
        return $response;
    }
    
    /**
     * Verify a single email address
     * 
     * @param string $email Email address to verify
     * @return array Verification result
     * @throws EmailListVerifyException
     */
    public function verifyEmail($email)
    {
        if (empty($email)) {
            throw new EmailListVerifyException('Email address is required');
        }
        
        $result = $this->makeRequest('verifyEmail', 'GET', ['email' => $email]);
        
        // Parse response
        if (is_string($result)) {
            return [
                'email' => $email,
                'status' => trim($result),
                'timestamp' => date('c')
            ];
        }
        
        return $result;
    }
    
    /**
     * Verify email with detailed information
     * 
     * @param string $email Email address to verify
     * @return array Detailed verification result
     * @throws EmailListVerifyException
     */
    public function verifyEmailDetailed($email)
    {
        if (empty($email)) {
            throw new EmailListVerifyException('Email address is required');
        }
        
        return $this->makeRequest('verifyEmailDetailed', 'GET', ['email' => $email]);
    }
    
    /**
     * Get account credits information
     * 
     * @return array Account credits info
     * @throws EmailListVerifyException
     */
    public function getCredits()
    {
        return $this->makeRequest('getCredits');
    }
    
    /**
     * Upload file for bulk verification
     * 
     * @param string $filePath Path to CSV file with emails
     * @param string|null $filename Optional custom filename
     * @return string File ID for tracking
     * @throws EmailListVerifyException
     */
    public function bulkUpload($filePath, $filename = null)
    {
        if (!file_exists($filePath)) {
            throw new EmailListVerifyException('File not found: ' . $filePath);
        }
        
        if ($filename === null) {
            $filename = 'bulk_verify_' . date('Ymd_His') . '.csv';
        }
        
        $files = [
            'file_contents' => [
                'path' => $filePath,
                'type' => 'text/csv',
                'name' => $filename
            ]
        ];
        
        $result = $this->makeRequest(
            'verifApiFile',
            'POST',
            ['filename' => $filename],
            [],
            $files
        );
        
        if (is_string($result)) {
            return trim($result);
        } elseif (is_array($result) && isset($result['file_id'])) {
            return $result['file_id'];
        }
        
        throw new EmailListVerifyException('Failed to get file ID from response');
    }
    
    /**
     * Get bulk verification status
     * 
     * @param string $fileId File ID from bulk_upload
     * @return array Verification status and progress
     * @throws EmailListVerifyException
     */
    public function getBulkStatus($fileId)
    {
        if (empty($fileId)) {
            throw new EmailListVerifyException('File ID is required');
        }
        
        return $this->makeRequest('getApiFileInfo', 'GET', ['file_id' => $fileId]);
    }
    
    /**
     * Download bulk verification results
     * 
     * @param string $fileId File ID from bulk_upload
     * @param string $resultType 'all' or 'clean' (default: 'all')
     * @return string CSV content with results
     * @throws EmailListVerifyException
     */
    public function downloadBulkResult($fileId, $resultType = 'all')
    {
        if (empty($fileId)) {
            throw new EmailListVerifyException('File ID is required');
        }
        
        if (!in_array($resultType, ['all', 'clean'])) {
            throw new EmailListVerifyException("result_type must be 'all' or 'clean'");
        }
        
        $endpoint = $resultType === 'all' ? 'downloadApiFile' : 'downloadCleanFile';
        return $this->makeRequest($endpoint, 'GET', ['file_id' => $fileId]);
    }
    
    /**
     * Verify multiple emails in batches
     * 
     * @param array $emails List of email addresses
     * @param int $maxBatchSize Maximum emails per request (default: 100)
     * @return array List of verification results
     */
    public function verifyBatch($emails, $maxBatchSize = 100)
    {
        $results = [];
        
        $chunks = array_chunk($emails, $maxBatchSize);
        
        foreach ($chunks as $batch) {
            foreach ($batch as $email) {
                try {
                    $result = $this->verifyEmail($email);
                    $results[] = $result;
                    usleep(100000); // Rate limiting (0.1 second)
                } catch (EmailListVerifyException $e) {
                    $results[] = [
                        'email' => $email,
                        'status' => 'error',
                        'error' => $e->getMessage(),
                        'timestamp' => date('c')
                    ];
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Wait for bulk verification to complete
     * 
     * @param string $fileId File ID from bulk_upload
     * @param int $checkInterval Seconds between status checks (default: 10)
     * @param int $maxWait Maximum seconds to wait (default: 3600)
     * @return array Final status when completed
     * @throws EmailListVerifyException
     */
    public function waitForBulkCompletion($fileId, $checkInterval = 10, $maxWait = 3600)
    {
        $startTime = time();
        
        while ((time() - $startTime) < $maxWait) {
            $status = $this->getBulkStatus($fileId);
            
            if (isset($status['status'])) {
                if ($status['status'] === 'completed') {
                    return $status;
                } elseif ($status['status'] === 'failed') {
                    $error = isset($status['error']) ? $status['error'] : 'Unknown error';
                    throw new EmailListVerifyException('Bulk verification failed: ' . $error);
                }
            }
            
            sleep($checkInterval);
        }
        
        throw new EmailListVerifyException('Timeout waiting for bulk verification (waited ' . $maxWait . 's)');
    }
}

/**
 * Helper class for email validation utilities
 */
class EmailValidator
{
    /**
     * Check if email has valid syntax
     * 
     * @param string $email Email address to check
     * @return bool True if syntax is valid
     */
    public static function isValidSyntax($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Extract domain from email address
     * 
     * @param string $email Email address
     * @return string|null Domain part or null
     */
    public static function extractDomain($email)
    {
        if (strpos($email, '@') !== false) {
            $parts = explode('@', $email);
            return strtolower($parts[1]);
        }
        return null;
    }
    
    /**
     * Check if domain is in common disposable email domains list
     * 
     * @param string $domain Domain to check
     * @return bool True if domain appears to be disposable
     */
    public static function isDisposableDomain($domain)
    {
        $disposableDomains = [
            'tempmail.com', 'throwaway.email', 'guerrillamail.com',
            'mailinator.com', '10minutemail.com', 'trashmail.com',
            'yopmail.com', 'temp-mail.org', 'fakeinbox.com'
        ];
        
        return in_array(strtolower($domain), $disposableDomains);
    }
}

/**
 * Manager for handling bulk email verification workflows
 */
class BulkVerificationManager
{
    private $client;
    private $activeJobs = [];
    
    /**
     * Initialize bulk verification manager
     * 
     * @param EmailListVerifyClient $client EmailListVerifyClient instance
     */
    public function __construct(EmailListVerifyClient $client)
    {
        $this->client = $client;
    }
    
    /**
     * Process CSV file with email verification
     * 
     * @param string $inputFile Path to input CSV file
     * @param string $outputFile Path to save results
     * @param bool $waitForCompletion Whether to wait for completion
     * @return array Job information
     * @throws EmailListVerifyException
     */
    public function processCsvFile($inputFile, $outputFile, $waitForCompletion = true)
    {
        // Upload file
        $fileId = $this->client->bulkUpload($inputFile);
        
        $jobInfo = [
            'file_id' => $fileId,
            'input_file' => $inputFile,
            'output_file' => $outputFile,
            'start_time' => date('c'),
            'status' => 'processing'
        ];
        
        $this->activeJobs[$fileId] = $jobInfo;
        
        if ($waitForCompletion) {
            // Wait for completion
            $finalStatus = $this->client->waitForBulkCompletion($fileId);
            
            // Download results
            $results = $this->client->downloadBulkResult($fileId, 'all');
            
            // Save to output file
            file_put_contents($outputFile, $results);
            
            $jobInfo['status'] = 'completed';
            $jobInfo['end_time'] = date('c');
            $jobInfo['final_status'] = $finalStatus;
            
            $this->activeJobs[$fileId] = $jobInfo;
        }
        
        return $jobInfo;
    }
    
    /**
     * Get status of a verification job
     * 
     * @param string $fileId File ID to check
     * @return array Job status information
     * @throws EmailListVerifyException
     */
    public function getJobStatus($fileId)
    {
        if (!isset($this->activeJobs[$fileId])) {
            throw new EmailListVerifyException('Unknown job ID: ' . $fileId);
        }
        
        $status = $this->client->getBulkStatus($fileId);
        $this->activeJobs[$fileId]['last_status'] = $status;
        
        return $this->activeJobs[$fileId];
    }
    
    /**
     * Get all active jobs
     * 
     * @return array All active jobs
     */
    public function getActiveJobs()
    {
        return $this->activeJobs;
    }
}