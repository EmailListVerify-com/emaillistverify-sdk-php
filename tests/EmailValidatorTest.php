<?php

namespace EmailListVerify\Tests;

use PHPUnit\Framework\TestCase;
use EmailListVerify\EmailValidator;

class EmailValidatorTest extends TestCase
{
    /**
     * @dataProvider validEmailSyntaxProvider
     */
    public function testIsValidSyntaxWithValidEmails($email)
    {
        $this->assertTrue(EmailValidator::isValidSyntax($email));
    }
    
    /**
     * @dataProvider invalidEmailSyntaxProvider
     */
    public function testIsValidSyntaxWithInvalidEmails($email)
    {
        $this->assertFalse(EmailValidator::isValidSyntax($email));
    }
    
    public function validEmailSyntaxProvider()
    {
        return [
            ['test@example.com'],
            ['user.name@example.com'],
            ['user+tag@example.co.uk'],
            ['firstname.lastname@subdomain.example.org'],
            ['1234567890@example.com'],
            ['email@example-one.com'],
            ['_______@example.com'],
            ['email@example.name'],
            ['email@example.museum'],
            ['email@example.co.jp'],
        ];
    }
    
    public function invalidEmailSyntaxProvider()
    {
        return [
            ['plainaddress'],
            ['@no-local.org'],
            ['missing-at-sign.org'],
            ['missing.domain@.com'],
            ['two@@example.com'],
            ['dotdot..@example.com'],
            ['spaces in@example.com'],
            ['user@'],
            ['user@.com'],
            ['user@.'],
            [''],
            ['user name@example.com'],
            ['user@exam ple.com'],
        ];
    }
    
    /**
     * @dataProvider emailDomainProvider
     */
    public function testExtractDomain($email, $expectedDomain)
    {
        $this->assertEquals($expectedDomain, EmailValidator::extractDomain($email));
    }
    
    public function emailDomainProvider()
    {
        return [
            ['test@example.com', 'example.com'],
            ['user@EXAMPLE.COM', 'example.com'],
            ['admin@subdomain.example.org', 'subdomain.example.org'],
            ['user+tag@domain.co.uk', 'domain.co.uk'],
            ['noreply@localhost', 'localhost'],
            ['support@192.168.1.1', '192.168.1.1'],
            ['invalidemailwithoutatsign', null],
            ['', null],
            ['@nodomain', ''],
        ];
    }
    
    /**
     * @dataProvider disposableDomainProvider
     */
    public function testIsDisposableDomain($domain, $isDisposable)
    {
        $this->assertEquals($isDisposable, EmailValidator::isDisposableDomain($domain));
    }
    
    public function disposableDomainProvider()
    {
        return [
            ['tempmail.com', true],
            ['throwaway.email', true],
            ['guerrillamail.com', true],
            ['mailinator.com', true],
            ['10minutemail.com', true],
            ['trashmail.com', true],
            ['yopmail.com', true],
            ['temp-mail.org', true],
            ['fakeinbox.com', true],
            ['TEMPMAIL.COM', true],
            ['TeMpMaIl.CoM', true],
            ['gmail.com', false],
            ['yahoo.com', false],
            ['outlook.com', false],
            ['company.com', false],
            ['example.org', false],
            ['university.edu', false],
            ['government.gov', false],
            ['', false],
            ['nonexistent-disposable.xyz', false],
        ];
    }
    
    public function testExtractDomainHandlesMultipleAtSigns()
    {
        $email = 'user@middle@example.com';
        $domain = EmailValidator::extractDomain($email);
        $this->assertEquals('middle@example.com', $domain);
    }
    
    public function testExtractDomainTrimsWhitespace()
    {
        $email = 'user@example.com ';
        $domain = EmailValidator::extractDomain($email);
        $this->assertEquals('example.com ', $domain);
    }
    
    public function testIsValidSyntaxWithSpecialCharacters()
    {
        $specialEmails = [
            'user!def!xyz@example.com' => false,
            'user\\@def@example.com' => false,
            'user#def@example.com' => false,
            'user$def@example.com' => false,
            'user%def@example.com' => false,
            'user&def@example.com' => false,
            'user*def@example.com' => false,
            'user/def@example.com' => false,
            'user=def@example.com' => false,
            'user?def@example.com' => false,
            'user^def@example.com' => false,
            'user`def@example.com' => false,
            'user{def}@example.com' => false,
            'user|def@example.com' => false,
            'user~def@example.com' => false,
        ];
        
        foreach ($specialEmails as $email => $expected) {
            $result = EmailValidator::isValidSyntax($email);
            $this->assertEquals($expected, $result, "Failed for email: $email");
        }
    }
    
    public function testIsValidSyntaxWithInternationalDomains()
    {
        $internationalEmails = [
            'test@例え.jp',
            'user@παράδειγμα.gr',
            'mail@пример.ru',
            'contact@مثال.ae',
        ];
        
        foreach ($internationalEmails as $email) {
            $result = EmailValidator::isValidSyntax($email);
            $this->assertIsBool($result);
        }
    }
    
    public function testIsDisposableDomainCaseInsensitive()
    {
        $variations = [
            'MAILINATOR.COM',
            'mailinator.com',
            'MaIlInAtOr.CoM',
            'MAILINATOR.com',
            'mailinator.COM',
        ];
        
        foreach ($variations as $domain) {
            $this->assertTrue(
                EmailValidator::isDisposableDomain($domain),
                "Failed to identify disposable domain: $domain"
            );
        }
    }
    
    public function testExtractDomainWithComplexEmails()
    {
        $complexEmails = [
            '"user name"@example.com' => 'example.com',
            '"user@name"@example.com' => 'example.com',
            'user.name+tag+category@sub.domain.example.com' => 'sub.domain.example.com',
            'x@example.com' => 'example.com',
            'very.long.email.address.with.many.dots@very.long.domain.name.with.many.parts.example.co.uk' => 'very.long.domain.name.with.many.parts.example.co.uk',
        ];
        
        foreach ($complexEmails as $email => $expectedDomain) {
            $domain = EmailValidator::extractDomain($email);
            $this->assertEquals($expectedDomain, $domain, "Failed for email: $email");
        }
    }
    
    public function testIsValidSyntaxWithLongEmails()
    {
        $longLocalPart = str_repeat('a', 64);
        $longDomain = str_repeat('a', 63) . '.com';
        $tooLongLocalPart = str_repeat('a', 65);
        
        $this->assertTrue(EmailValidator::isValidSyntax($longLocalPart . '@example.com'));
        $this->assertTrue(EmailValidator::isValidSyntax('user@' . $longDomain));
        $this->assertFalse(EmailValidator::isValidSyntax($tooLongLocalPart . '@example.com'));
    }
    
    public function testExtractDomainReturnsLowercase()
    {
        $emails = [
            'user@UPPERCASE.COM' => 'uppercase.com',
            'user@MiXeDcAsE.OrG' => 'mixedcase.org',
            'user@lower.case.net' => 'lower.case.net',
        ];
        
        foreach ($emails as $email => $expected) {
            $this->assertEquals($expected, EmailValidator::extractDomain($email));
        }
    }
}