<?php

namespace EmailListVerify\Tests\Helpers;

use EmailListVerify\EmailListVerifyException;

/**
 * Mock HTTP client for testing API interactions without real HTTP requests
 */
class MockHttpClient
{
    private $responses = [];
    private $requestHistory = [];
    private $defaultResponse = null;
    private $shouldThrowException = false;
    private $exceptionMessage = '';
    
    /**
     * Add a mocked response for a specific endpoint
     */
    public function addResponse($endpoint, $response, $httpCode = 200)
    {
        $this->responses[$endpoint] = [
            'response' => $response,
            'httpCode' => $httpCode
        ];
    }
    
    /**
     * Set a default response for any unmatched endpoint
     */
    public function setDefaultResponse($response, $httpCode = 200)
    {
        $this->defaultResponse = [
            'response' => $response,
            'httpCode' => $httpCode
        ];
    }
    
    /**
     * Configure to throw an exception on next request
     */
    public function throwException($message)
    {
        $this->shouldThrowException = true;
        $this->exceptionMessage = $message;
    }
    
    /**
     * Mock a request and return configured response
     */
    public function request($endpoint, $method = 'GET', $params = [], $data = [], $files = [])
    {
        $this->requestHistory[] = [
            'endpoint' => $endpoint,
            'method' => $method,
            'params' => $params,
            'data' => $data,
            'files' => $files,
            'timestamp' => microtime(true)
        ];
        
        if ($this->shouldThrowException) {
            $this->shouldThrowException = false;
            throw new EmailListVerifyException($this->exceptionMessage);
        }
        
        if (isset($this->responses[$endpoint])) {
            $response = $this->responses[$endpoint];
            if ($response['httpCode'] >= 400) {
                throw new EmailListVerifyException(
                    'HTTP error ' . $response['httpCode'] . ': ' . $response['response']
                );
            }
            return $response['response'];
        }
        
        if ($this->defaultResponse !== null) {
            if ($this->defaultResponse['httpCode'] >= 400) {
                throw new EmailListVerifyException(
                    'HTTP error ' . $this->defaultResponse['httpCode'] . ': ' . $this->defaultResponse['response']
                );
            }
            return $this->defaultResponse['response'];
        }
        
        throw new EmailListVerifyException('No mock response configured for endpoint: ' . $endpoint);
    }
    
    /**
     * Get the history of all requests made
     */
    public function getRequestHistory()
    {
        return $this->requestHistory;
    }
    
    /**
     * Get the last request made
     */
    public function getLastRequest()
    {
        if (empty($this->requestHistory)) {
            return null;
        }
        return end($this->requestHistory);
    }
    
    /**
     * Clear all request history
     */
    public function clearHistory()
    {
        $this->requestHistory = [];
    }
    
    /**
     * Reset all mock configurations
     */
    public function reset()
    {
        $this->responses = [];
        $this->requestHistory = [];
        $this->defaultResponse = null;
        $this->shouldThrowException = false;
        $this->exceptionMessage = '';
    }
    
    /**
     * Assert that a specific endpoint was called
     */
    public function assertEndpointCalled($endpoint)
    {
        foreach ($this->requestHistory as $request) {
            if ($request['endpoint'] === $endpoint) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Assert that a specific endpoint was called with specific parameters
     */
    public function assertEndpointCalledWith($endpoint, $params = [], $method = null)
    {
        foreach ($this->requestHistory as $request) {
            if ($request['endpoint'] !== $endpoint) {
                continue;
            }
            
            if ($method !== null && $request['method'] !== $method) {
                continue;
            }
            
            $paramsMatch = true;
            foreach ($params as $key => $value) {
                if (!isset($request['params'][$key]) || $request['params'][$key] !== $value) {
                    $paramsMatch = false;
                    break;
                }
            }
            
            if ($paramsMatch) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get count of calls to a specific endpoint
     */
    public function getCallCount($endpoint = null)
    {
        if ($endpoint === null) {
            return count($this->requestHistory);
        }
        
        $count = 0;
        foreach ($this->requestHistory as $request) {
            if ($request['endpoint'] === $endpoint) {
                $count++;
            }
        }
        return $count;
    }
}

/**
 * Mock response builder for common API responses
 */
class MockResponseBuilder
{
    /**
     * Build a successful email verification response
     */
    public static function emailVerificationSuccess($email, $status = 'ok')
    {
        return [
            'email' => $email,
            'status' => $status,
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Build a detailed email verification response
     */
    public static function emailVerificationDetailed($email, $status = 'ok')
    {
        return [
            'email' => $email,
            'status' => $status,
            'domain' => substr($email, strpos($email, '@') + 1),
            'mx_found' => true,
            'mx_record' => 'mail.' . substr($email, strpos($email, '@') + 1),
            'smtp_check' => true,
            'catch_all' => false,
            'role' => false,
            'disposable' => false,
            'free' => false,
            'score' => 95,
            'user' => substr($email, 0, strpos($email, '@')),
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Build a credits response
     */
    public static function creditsResponse($total = 10000, $used = 2500)
    {
        return [
            'credits' => $total,
            'used' => $used,
            'remaining' => $total - $used,
            'plan' => 'premium',
            'reset_date' => date('Y-m-d', strtotime('+30 days'))
        ];
    }
    
    /**
     * Build a bulk upload response
     */
    public static function bulkUploadResponse($fileId = null)
    {
        if ($fileId === null) {
            $fileId = 'file_' . uniqid();
        }
        
        return [
            'file_id' => $fileId,
            'status' => 'queued',
            'message' => 'File uploaded successfully',
            'estimated_time' => 300
        ];
    }
    
    /**
     * Build a bulk status response
     */
    public static function bulkStatusResponse($fileId, $status = 'processing', $progress = 50)
    {
        $total = 1000;
        $processed = (int)($total * $progress / 100);
        
        return [
            'file_id' => $fileId,
            'status' => $status,
            'progress' => $progress,
            'total' => $total,
            'processed' => $processed,
            'valid' => (int)($processed * 0.7),
            'invalid' => (int)($processed * 0.2),
            'unknown' => (int)($processed * 0.1),
            'start_time' => date('c', strtotime('-5 minutes')),
            'estimated_completion' => $status === 'completed' ? date('c') : date('c', strtotime('+5 minutes'))
        ];
    }
    
    /**
     * Build a CSV result for bulk download
     */
    public static function bulkCsvResult($emails = [])
    {
        if (empty($emails)) {
            $emails = [
                'test1@example.com' => 'ok',
                'test2@example.com' => 'invalid',
                'test3@example.com' => 'disposable',
                'test4@example.com' => 'role',
                'test5@example.com' => 'unknown'
            ];
        }
        
        $csv = "email,status,reason\n";
        foreach ($emails as $email => $status) {
            $reason = $status === 'ok' ? '' : 'Failed ' . $status . ' check';
            $csv .= "$email,$status,$reason\n";
        }
        
        return $csv;
    }
    
    /**
     * Build an error response
     */
    public static function errorResponse($message, $code = 'API_ERROR')
    {
        return [
            'error' => true,
            'code' => $code,
            'message' => $message,
            'timestamp' => date('c')
        ];
    }
}