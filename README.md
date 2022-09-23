# NextMagentacloud app for SPICA integration

## Delivery

As this repo is kept private the customer does not have access to the repos and releases, therefore both are currently mirrored manually to the private repo at the customer side https://github.com/nextmcloud/nmc_spica. Currently Julius, Julien and Tobias have access to their organization on GitHub, Björn has the customer contact in case additional people may need access.

## Implemented features:
- Unread email counter
- Address book search

## Requirements
- Required OIDC connection to be setup

## OIDC token handling

During login the access_token and refresh token are passed by the user_oidc app to the nmc_spica app thorugh a dispatched event. nmc_spica will request a fresh token with the `spica` scope and regularly refreshs it with the refresh token that was initially provided by the OpenID Connect login.

## Configuration:

Configure SPICA API endpoint:

	occ config:app:set nmc_spica spica-baseurl --value="https://spica.ver.sul.t-online.de"
	occ config:app:set nmc_spica spica-appid --value="my-app-id"
	occ config:app:set nmc_spica spica-appsecret --value="my-secret-key"

Setting a webmail url:

	occ config:app:set nmc_spica webmail-url --value="https://emailvt.sgp.telekom.de"

## Local testing

A refresh token needs to be obtained from a system connected to Telekom Login. This can be done in debug mode (e.g. on dev2 provided by T-Systems) when browsing the https://dev2.next.magentacloud.de/apps/nmc_spica/ url as the logged in user.

As a more permanent testing access it is also possible to configure a debug token that can be used in case debug mode is not feasible for security reasons:

	occ config:app:set nmc_spica debug_token --value=my-secure-random-token

With that the token can be obtained from the system by passing it as an url parameter: https://dev2.next.magentacloud.de/apps/nmc_spica/?debug_token=my-secure-random-token

Even without using Telekom Login, this app can be tested by manually providing a valid user token through app config:

	occ config:app:set nmc_spica spica-usertoken --value="idtokenvalue"

