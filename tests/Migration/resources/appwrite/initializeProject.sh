#!/bin/sh

# Create Console Account
curl -s -k 'https://localhost/v1/account' \
  -H 'content-type: application/json' \
  -H 'x-appwrite-project: console' \
  --data-raw '{"userId":"admin","email":"admin@appwrite.io","password":"Password123","name":"Admin"}' \
  --compressed \
  --insecure

# Login
curl -s -k -b console-cookies.txt -c console-cookies.txt 'https://localhost/v1/account/sessions/email' \
  -H 'content-type: application/json' \
  -H 'x-appwrite-project: console' \
  --data-raw '{"email":"admin@appwrite.io","password":"Password123"}' \
  --compressed \
  --insecure

# Create Team
curl -s -k -b console-cookies.txt -c console-cookies.txt 'https://localhost/v1/teams' \
  -H 'content-type: application/json' \
  -H 'x-appwrite-project: console' \
  --data-raw '{"teamId":"personal","name":"Personal Projects"}' \
  --compressed \
  --insecure

# Create Project
curl -s -k -b console-cookies.txt -c console-cookies.txt 'https://localhost/v1/projects' \
  -H 'content-type: application/json' \
  -H 'x-appwrite-project: console' \
  --data-raw '{"projectId":"test","name":"Test","teamId":"personal","region":"default"}' \
  --compressed \
  --insecure