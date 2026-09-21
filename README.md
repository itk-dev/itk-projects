# ITK Projects

A Symfony application for registering and browsing municipal **projects**.
It is a rebuild of the previous Drupal-based [project-database](https://github.com/itk-dev/project-database)
with an accompanied react application for graph visualizations [project-database-app](https://github.com/itk-dev/project-database-app),
focused on a friendlier interface for creating and getting an overview
of projects.

The project follows the itk-dev
[`symfony` Docker template](https://github.com/itk-dev/devops_itkdev-docker) and
runs on PHP 8.4 / Symfony 8.

## Requirements

- [Docker](https://www.docker.com/) and the itk-dev
  [`itkdev-docker-compose`](https://github.com/itk-dev/devops_itkdev-docker) setup.
- [Task](https://taskfile.dev/) (optional, but the commands below use it).

## Installation

Start the containers and install everything (dependencies, database schema and
development fixtures):

```sh
task install
```

The site is served on the domain configured in `.env`
(`COMPOSE_DOMAIN`, e.g. `https://itk-projects.local.itkdev.dk`).

### Signing in

The development fixtures create two users (password `password` for both):

- `admin@example.com` — administrator
- `editor@example.com` — editor

Create an administrator manually with:

```sh
task create-admin -- you@example.com "Your Name"
```

## Development

```sh
task                        # list all tasks
task console -- <command>   # run a Symfony console command
task compose -- <command>   # run a composer command
task coding-standards:check # Check coding standards
task coding-standards:apply # Apply coding standards
task static-analysis        # run PHPStan
task test                   # run the test suite
```
