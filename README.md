# getcomposer.org sources

## Contribute

To contribute to the docs, fork the composer/composer repository and add or
change a file in the doc directory. Documentation sources can be browsed
at https://github.com/composer/composer/tree/main/doc

Documentation is then automatically generated on getcomposer.org

## Run this project locally

This is a Symfony project.

The easiest way to run this project is to install [Symfony-cli](https://symfony.com/download) and [run the project](https://symfony.com/doc/current/setup.html#running-symfony-applications) using Symfony.

If you encounter issues with HTTPS/SSL, you can automatically install a self-signed certificate [using `symfony server:ca:install`](https://symfony.com/doc/current/setup/symfony_server.html).

### Versions

 For Symfony to run, you need to create a JSON file in `/web` called `versions` (no extension). It should contain at least the following:

```json
{
  "stable": [{ "version": "<composer version>" }],
  "preview": [{ "version": "<composer version>" }]
}
```

> [!Note]
>`<composer version>` is a [Semantic Versioning](https://semver.org/) **string** for a [Composer version](https://github.com/composer/composer/releases), e.g. "2.8.9".
>


### JS / CSS

Assets are generated using Node's `esbuild`.

To change JS and CSS, make sure you run `npm install` first and then use
`npm run dev` or `npm run build` to build new assets from the sources with watch mode on.

The output of build should be committed to be deployed, but please do not commit
it if you are sending a pull request.
