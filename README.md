# govcms_backup_scripts with Lagoon

## Local Development (via docker-compose)

#### Requirements

- pygmy (https://docs.amazee.io/local_docker_development/pygmy.html, no need for the local resolver so can be started with `pygmy up --no-resolver`)
- docker-compose
- Docker

#### Execute

1. Make sure you have an ssh key added to the ssh-agent (check with `pygmy status`)
2. Run `docker-compose run backup`
