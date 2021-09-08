# NextMagentacloud app for SPICA integration

## Implemented features:
- Unread email counter
- Address book search
  
## Requirements
- Required OIDC connection to be setup

## OIDC token handling

During login the access_token and refresh token are passed by the user_oidc app to the nmc_mail app thorugh a dispatched event. nmc_mail will request a fresh token with the `spica` scope and regularly refreshs it with the refresh token that was initially provided by the OpenID Connect login.

## Configuration:

Configure SPICA API endpoint:

	occ config:app:set nmc_mail spica-baseurl --value="https://spica.ver.sul.t-online.de"
	occ	config:app:set nmc_mail spica-appid --value="my-app-id"
	occ config:app:set nmc_mail spica-appsecret --value="my-secret-key"

Setting a webmail url:

	occ config:app:set nmc_mail webmail-url --value="https://emailvt.sgp.telekom.de"

## Local testing

A refresh token needs to be obtained from a system connected to Telekom Login.

Even without using Telekom Login, this app can be tested by manually providing a valid user token through app config:
	
	export REFRESH_TOKEN="RT2:8323c845-328d-4535-81d6-985787516979:945c6cce-6595-48eb-9312-7ab0aac112e3"
	export SPICA_TOKEN=`curl -X POST https://accounts.login00.idm.ver.sul.t-online.de/oauth2/tokens -d "grant_type=refresh_token&client_id=$OIDC_CLIENT_ID&client_secret=$OIDC_CLIENT_SECRET&refresh_token=$REFRESH_TOKEN&scope=spica" | jq -r .access_token`
	occ config:app:set nmc_mail spica-usertoken --value="$SPICA_TOKEN"

