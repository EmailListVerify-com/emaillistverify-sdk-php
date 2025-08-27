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
     * Verify a single email address
     * 
     * @param string $email Email address to verify
     * @return string Verification result (ok, invalid, invalid_mx, accept_all, ok_for_all, disposable, role, email_disabled, dead_server, unknown)
     * @throws EmailListVerifyException
     */
    public function verifyEmail($email)
    {
        if (empty($email)) {
            throw new EmailListVerifyException('Email address is required');
        }
        
        $url = self::BASE_URL . '/verifyEmail?' . http_build_query([
            'secret' => $this->apiKey,
            'email' => $email
        ]);
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'EmailListVerify-PHP-SDK/' . self::VERSION);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new EmailListVerifyException('Request failed: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new EmailListVerifyException('HTTP error ' . $httpCode . ': ' . $response);
        }
        
        return trim($response);
    }
    
    /**
     * Verify multiple emails
     * 
     * @param array $emails Array of email addresses
     * @return array Array of results with email => status
     * @throws EmailListVerifyException
     */
    public function verifyEmails($emails)
    {
        if (!is_array($emails)) {
            throw new EmailListVerifyException('Emails must be an array');
        }
        
        $results = [];
        
        foreach ($emails as $email) {
            try {
                $results[$email] = $this->verifyEmail($email);
                // Add small delay to avoid rate limiting
                usleep(100000); // 0.1 second
            } catch (EmailListVerifyException $e) {
                $results[$email] = 'error: ' . $e->getMessage();
            }
        }
        
        return $results;
    }
}