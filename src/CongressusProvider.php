<?php

namespace DrowningElysium\LaravelSocialiteCongressus;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class CongressusProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = [
        'openid', // Mandatory to even get data
        'email',
        'profile',
    ];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * The domain of the association.
     *
     * @var string
     */
    protected $domain;

    /**
     * Set the domain to use to send our requests to.
     *
     * @param string $domain
     *
     * @return void
     */
    public function setDomain(string $domain): void
    {
        // Make sure there is no trailing / or any other illegal characters.
        $domain = trim($domain, " \t\n\r\0\x0B/");

        // Make sure the url starts with "https://www."
        if (!\Str::startsWith($domain, 'https://www.')) {
            throw new \DomainException('Make sure the domain configured starts with https://www. according to the documentation of Congressus.');
        }

        // Make sure there is no trailing / or any other illegal characters.
        $this->domain = $domain;
    }

    /**
     * Set the scopes of the requested access.
     *
     * @param  array|string  $scopes
     * @return $this
     */
    public function setScopes($scopes): static
    {
        if (!is_array($scopes)) {
            $scopes = [$scopes];
        }

        /** @var string[] $scopes */
        $scopes[] = 'openid';

        return parent::setScopes($scopes);
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->domain.'/oauth/authorize', $state);
    }

    /**
     * Get the headers for the access token request.
     *
     * @param  string  $code
     * @return array
     */
    protected function getTokenHeaders($code): array
    {
        return [
            'Accept' => 'application/json',
            'Content' => 'application/x-www-form-urlencoded',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl(): string
    {
        return $this->domain.'/oauth/token';
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param  string  $code
     * @return array
     */
    protected function getTokenFields($code)
    {
        $fields = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
        ];

        return $fields;
    }

    /**
     * Get the access token response for the given code.
     *
     * @param string $code
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     */
    public function getAccessTokenResponse($code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::AUTH => [$this->clientId, $this->clientSecret], // Basic Auth
            RequestOptions::HEADERS => $this->getTokenHeaders($code),
            RequestOptions::FORM_PARAMS => $this->getTokenFields($code),
        ]);

        return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * {@inheritdoc}
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get($this->domain.'/oauth/userinfo', [
            RequestOptions::HEADERS => ['Authorization' => 'Bearer '.$token],
        ]);

        return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User())->setRaw($user)->map([
            'id' => $user['user_id'],
            'nickname' => $user['username'],
            'name' => Arr::get($user, 'name'),
            'email' => Arr::get($user, 'email'),
            'avatar' => Arr::get($user, 'picture'),
        ]);
    }

    /**
     * Convert the expires_at into a expires_in.
     *
     * @param string $expiresAt
     *
     * @return int
     */
    private function calculateExpiresIn(string $expiresAt): int
    {
        $time = Carbon::parse($expiresAt, 'UTC');

        return Carbon::now()->diffInRealSeconds($time);
    }

    /**
     * {@inheritdoc}
     */
    public function user(): User
    {
        if ($this->user) {
            return $this->user;
        }

        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }

        $response = $this->getAccessTokenResponse($this->getCode());

        $this->user = $this->mapUserToObject($this->getUserByToken(
            $token = Arr::get($response, 'access_token')
        ));

        return $this->user->setToken($token)
            ->setRefreshToken(Arr::get($response, 'refresh_token'))
            ->setExpiresIn($this->calculateExpiresIn(Arr::get($response, 'expires_at')))
            ->setApprovedScopes(explode($this->scopeSeparator, Arr::get($response, 'scope', '')));
    }
}
