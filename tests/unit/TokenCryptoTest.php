<?php

use OCA\UserOIDC\Db\User;
use OCP\Security\ICrypto;

use OCA\NmcSpica\Listener\TokenObtainedEventListener;
use OCA\NmcSpica\Service\SpicaMailService;
use OCA\NmcSpica\Service\TokenService;
use OCA\UserOIDC\Db\Provider;
use OCA\UserOIDC\Event\TokenObtainedEvent;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IResponse;
use Psr\Log\LoggerInterface;

use OCP\AppFramework\App;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;


/**
 * Test refresh token handling for different situations
 */
class TokenCryptoTest extends TestCase {
    private SpicaMailService $mailService;
    private TokenService $tokenService;
    private IClient $client;
    private TokenObtainedEventListener $listener;
    private ICrypto $crypt;
    private LoggerInterface $logger;


    private Provider $provider;


	public function setUp(): void {
		parent::setUp();

        $this->app = new App("nmc_spica");
        $this->crypto = $this->app->getContainer()->get(ICrypto::class);
        $this->tokenService = $this->createMock(TokenService::class);
        $this->mailService = $this->createMock(SpicaMailService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $clientService = $this->createMock(IClientService::class);
        $this->client = $this->createMock(IClient::class);
        $clientService->expects(self::any())
            ->method('newClient')
            ->willReturn($this->client);
 

        $this->provider = $this->getMockBuilder(Provider::class)
            ->addMethods(['getClientId', 'getCLientSecret'])
            ->getMock();
        $this->provider->expects(self::any())
            ->method('getClientId')
            ->willReturn('CLIENT0001T23456');
        $this->provider->expects(self::any())
            ->method('getClientSecret')
            ->willReturn($this->crypto->encrypt("Th1iIs_Vry\$ectre"));

        $this->listener = new TokenObtainedEventListener(
            $clientService,
            $this->tokenService,
            $this->mailService,
            $this->logger,
            $this->crypto
        );
    }


    /**
     * Test whether client secret is properly decrypted
     */
    public function testClientSecretDecrpyt() {
       $tokenResponse = $this->createMock(IResponse::class);
       $tokenResponse->expects(self::any())
            ->method('getBody')
            ->willReturn(json_encode([
                'access_token' => "eyJwMnMiOiI3...",
            ]));
        $tokenResponse->expects(self::any())
            ->method('getStatusCode')
            ->willReturn(200);
        $this->client->expects(self::once())
            ->method('post')
            ->with(
                $this->equalTo("https://provider.my/token"),
                $this->callback(function(array $message): bool {
                    $this->assertArrayHasKey('body', $message);
                    $body = $message['body'];
                    $this->assertEquals($body['scope'], 'spica');
                    $this->assertEquals($body['grant_type'], 'refresh_token');
                    $this->assertEquals($body['client_id'], 'CLIENT0001T23456');
                    $this->assertEquals($body['client_secret'], "Th1iIs_Vry\$ectre");
                    return true; 
                }))
            ->willReturn($tokenResponse);
        $this->tokenService->expects(self::once())
            ->method('storeToken')
            ->with($this->logicalAnd( $this->arrayHasKey('access_token'), 
                    $this->arrayHasKey('provider_id')));
        $this->mailService->expects(self::once())
            ->method('resetCache');
        $this->mailService->expects(self::once())
            ->method('fetchUnreadCounter');
        $this->logger->expects(self::never())
            ->method('error');

        $event = new TokenObtainedEvent([
            'refresh_token' => "RT2:5157ed22-d2d3-421a-b5ec-371dc540f200:848234d1-3fb8-4f73-a971-fe3968f2e266",
            'access_token' => "<dummy>",
            'scope' => "userinfo",
            'token_type' => "Bearer"
        ], $this->provider, [
            'token_endpoint' => "https://provider.my/token"
        ]);

        $this->listener->handle($event);
    }

    public function testNoRefreshToken() {
        $this->client->expects(self::never())
            ->method('post');
        $this->tokenService->expects(self::never())
            ->method('storeToken');
        $this->mailService->expects(self::never())
            ->method('resetCache');
        $this->mailService->expects(self::never())
            ->method('fetchUnreadCounter');
        $this->logger->expects(self::never())
            ->method('error');

        $event = new TokenObtainedEvent([
            'access_token' => "<dummy>",
            'scope' => "userinfo",
            'token_type' => "Bearer"
        ], $this->provider, [
            'token_endpoint' => "https://provider.my/token"
        ]);
    
        $this->listener->handle($event);
    }

    public function testTokenServiceError() {
        $this->client->expects(self::once())
            ->method('post')
            ->will($this->throwException(new \TypeError("Unexpected test type")));
        $this->tokenService->expects(self::never())
            ->method('storeToken');
        $this->mailService->expects(self::never())
            ->method('resetCache');
        $this->mailService->expects(self::never())
            ->method('fetchUnreadCounter');
        $this->logger->expects(self::once())
            ->method('error')
            ->with($this->stringContains('oidc token'));
            
            $event = new TokenObtainedEvent([
                'refresh_token' => "RT2:5157ed22-d2d3-421a-b5ec-371dc540f200:848234d1-3fb8-4f73-a971-fe3968f2e266",
                'access_token' => "<dummy>",
                'scope' => "userinfo",
                'token_type' => "Bearer"
            ], $this->provider, [
                'token_endpoint' => "https://provider.my/token"
            ]);
    
            $this->listener->handle($event);    
    }


}