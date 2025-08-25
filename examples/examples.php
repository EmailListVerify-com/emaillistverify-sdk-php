<?php
/**
 * EmailListVerify PHP SDK Examples
 */

require_once __DIR__ . '/../src/EmailListVerifyClient.php';

use EmailListVerify\EmailListVerifyClient;
use EmailListVerify\EmailValidator;
use EmailListVerify\BulkVerificationManager;
use EmailListVerify\EmailListVerifyException;

/**
 * Example: Verify a single email address
 */
function exampleSingleVerification()
{
    // Initialize client with your API key
    $client = new EmailListVerifyClient('YOUR_API_KEY_HERE');
    
    try {
        // Verify single email
        $result = $client->verifyEmail('test@example.com');
        echo "Email: " . $result['email'] . PHP_EOL;
        echo "Status: " . $result['status'] . PHP_EOL;
        echo "Timestamp: " . $result['timestamp'] . PHP_EOL;
        
        // Verify with detailed information
        $detailedResult = $client->verifyEmailDetailed('test@example.com');
        echo "Detailed result: " . json_encode($detailedResult, JSON_PRETTY_PRINT) . PHP_EOL;
        
    } catch (EmailListVerifyException $e) {
        echo "Error: " . $e->getMessage() . PHP_EOL;
    }
}

/**
 * Example: Verify multiple emails in batch
 */
function exampleBatchVerification()
{
    $client = new EmailListVerifyClient('YOUR_API_KEY_HERE');
    
    // List of emails to verify
    $emails = [
        'valid@example.com',
        'invalid@fake-domain-123456.com',
        'test@gmail.com',
        'info@company.com'
    ];
    
    try {
        // Verify batch
        $results = $client->verifyBatch($emails, 50);
        
        // Process results
        foreach ($results as $result) {
            $status = $result['status'] ?? 'unknown';
            $email = $result['email'] ?? '';
            echo $email . ": " . $status . PHP_EOL;
        }
        
    } catch (EmailListVerifyException $e) {
        echo "Error: " . $e->getMessage() . PHP_EOL;
    }
}

/**
 * Example: Bulk verify emails from CSV file
 */
function exampleBulkFileVerification()
{
    $client = new EmailListVerifyClient('YOUR_API_KEY_HERE');
    $manager = new BulkVerificationManager($client);
    
    try {
        // Process CSV file
        $jobInfo = $manager->processCsvFile(
            'emails.csv',
            'verified_emails.csv',
            true // Wait for completion
        );
        
        echo "Job completed: " . $jobInfo['file_id'] . PHP_EOL;
        echo "Results saved to: " . $jobInfo['output_file'] . PHP_EOL;
        
    } catch (EmailListVerifyException $e) {
        echo "Error: " . $e->getMessage() . PHP_EOL;
    }
}

/**
 * Example: Start bulk verification without waiting
 */
function exampleAsyncBulkVerification()
{
    $client = new EmailListVerifyClient('YOUR_API_KEY_HERE');
    
    try {
        // Upload file for verification
        $fileId = $client->bulkUpload('emails.csv', 'my_email_list.csv');
        echo "File uploaded with ID: " . $fileId . PHP_EOL;
        
        // Check status periodically
        while (true) {
            $status = $client->getBulkStatus($fileId);
            echo "Status: " . ($status['status'] ?? 'unknown') . PHP_EOL;
            echo "Progress: " . ($status['progress'] ?? 0) . "%" . PHP_EOL;
            
            if (isset($status['status']) && $status['status'] === 'completed') {
                // Download results
                $allResults = $client->downloadBulkResult($fileId, 'all');
                $cleanResults = $client->downloadBulkResult($fileId, 'clean');
                
                // Save results
                file_put_contents('all_results.csv', $allResults);
                file_put_contents('clean_results.csv', $cleanResults);
                
                echo "Results downloaded successfully!" . PHP_EOL;
                break;
            }
            
            sleep(10); // Wait 10 seconds before next check
        }
        
    } catch (EmailListVerifyException $e) {
        echo "Error: " . $e->getMessage() . PHP_EOL;
    }
}

/**
 * Example: Use validation utilities
 */
function exampleEmailValidation()
{
    $emails = [
        'valid@example.com',
        'invalid-email',
        'test@tempmail.com',
        'user@gmail.com'
    ];
    
    foreach ($emails as $email) {
        echo PHP_EOL . "Email: " . $email . PHP_EOL;
        echo "Valid syntax: " . (EmailValidator::isValidSyntax($email) ? 'Yes' : 'No') . PHP_EOL;
        
        $domain = EmailValidator::extractDomain($email);
        if ($domain) {
            echo "Domain: " . $domain . PHP_EOL;
            echo "Disposable: " . (EmailValidator::isDisposableDomain($domain) ? 'Yes' : 'No') . PHP_EOL;
        }
    }
}

/**
 * Example: Check account credits
 */
function exampleGetCredits()
{
    $client = new EmailListVerifyClient('YOUR_API_KEY_HERE');
    
    try {
        $credits = $client->getCredits();
        echo "Available credits: " . ($credits['credits'] ?? 0) . PHP_EOL;
        echo "Used credits: " . ($credits['used_credits'] ?? 0) . PHP_EOL;
        echo "Free credits: " . ($credits['free_credits'] ?? 0) . PHP_EOL;
        
    } catch (EmailListVerifyException $e) {
        echo "Error: " . $e->getMessage() . PHP_EOL;
    }
}

/**
 * Example: Handle API errors properly
 */
function exampleErrorHandling()
{
    $client = new EmailListVerifyClient('YOUR_API_KEY_HERE');
    
    try {
        // Attempt to verify email
        $result = $client->verifyEmail('test@example.com');
        
        if ($result['status'] === 'ok') {
            echo "Email is valid!" . PHP_EOL;
        } elseif ($result['status'] === 'failed') {
            echo "Email verification failed" . PHP_EOL;
        } else {
            echo "Unknown status: " . $result['status'] . PHP_EOL;
        }
        
    } catch (EmailListVerifyException $e) {
        echo "API Error: " . $e->getMessage() . PHP_EOL;
    } catch (Exception $e) {
        echo "Unexpected error: " . $e->getMessage() . PHP_EOL;
    }
}

/**
 * Example: Process emails with custom logic
 */
function exampleCustomProcessing()
{
    $client = new EmailListVerifyClient('YOUR_API_KEY_HERE');
    
    // Read emails from file
    $emails = file('email_list.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    $validEmails = [];
    $invalidEmails = [];
    $unknownEmails = [];
    
    foreach ($emails as $email) {
        try {
            $result = $client->verifyEmail($email);
            
            switch ($result['status']) {
                case 'ok':
                    $validEmails[] = $email;
                    break;
                case 'failed':
                    $invalidEmails[] = $email;
                    break;
                default:
                    $unknownEmails[] = $email;
            }
            
            // Rate limiting
            usleep(100000); // 0.1 second
            
        } catch (EmailListVerifyException $e) {
            echo "Error verifying $email: " . $e->getMessage() . PHP_EOL;
            $unknownEmails[] = $email;
        }
    }
    
    // Save results to separate files
    file_put_contents('valid_emails.txt', implode(PHP_EOL, $validEmails));
    file_put_contents('invalid_emails.txt', implode(PHP_EOL, $invalidEmails));
    file_put_contents('unknown_emails.txt', implode(PHP_EOL, $unknownEmails));
    
    echo "Processing complete!" . PHP_EOL;
    echo "Valid: " . count($validEmails) . PHP_EOL;
    echo "Invalid: " . count($invalidEmails) . PHP_EOL;
    echo "Unknown: " . count($unknownEmails) . PHP_EOL;
}

// Run examples (remember to set your API key first)
if (php_sapi_name() === 'cli') {
    echo "EmailListVerify PHP SDK Examples" . PHP_EOL;
    echo str_repeat("=", 40) . PHP_EOL;
    
    // Uncomment to run examples:
    // exampleSingleVerification();
    // exampleBatchVerification();
    // exampleBulkFileVerification();
    // exampleEmailValidation();
    // exampleGetCredits();
    // exampleErrorHandling();
}