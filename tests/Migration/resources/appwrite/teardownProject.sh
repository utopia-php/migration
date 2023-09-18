#!/bin/sh

# Delete Project
curl -s -k -b console-cookies.txt -c console-cookies.txt 'https://localhost/v1/projects/test' \
  -X DELETE \
  -H 'content-type: application/json' \
  -H 'x-appwrite-project: console' \
  --compressed \
  --insecure

# Delete Team
curl -s -k -b console-cookies.txt -c console-cookies.txt 'https://localhost/v1/teams/personal' \
  -X DELETE \
  -H 'content-type: application/json' \
  -H 'x-appwrite-project: console' \
  --compressed \
  --insecure

# Delete Console Account
curl -s -k -b console-cookies.txt -c console-cookies.txt 'https://localhost/v1/account' \
  -X DELETE \
  -H 'content-type: application/json' \
  -H 'x-appwrite-project: console' \
  --compressed \
  --insecure