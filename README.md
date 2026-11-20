# Dopple

![](/banner.png)

Convert a website to static files without changing any URLs.

```
dopple <url> [output-path] [--concurrency=N] [--timeout=N] [--max-pages=N]
```


## File Structure

Downloaded files are mapped to files on disk in the following ways. The `.htaccess` maps the file paths back to the
original URLs.

- `/` => `/index.html`
- `/about` => `/about.html`
- `/blog/` => `/blog/index.html`
- `/js/app.js` => `/css/app.js`
- `/blog?page2` => `/blog@query=<HASH_OF_QUERY_STRING>.html`
- `/css/app.css?v=2` => `/css/app@query=<HASH_OF_QUERY_STRING>.css`


## Points Of Interest

- Redirects are maintained via `RedirectMatch` directives written to `.htaccess`
- Headers common to all requests are maintained
- External URLs are not touched
- CSS paths are crawled (`@import` and `url()`)
- JS paths are crawled (`import`, `import()` and string literals ending in a file extension, like `'/img/logo.svg'`)
- Unlinked paths (`/robots.txt`, `/sitemap.xml`, etc) are automatically included
- Interrupting with Ctrl-C will stop further requests, but will process already downloaded paths
- Custom 404 pages are maintained. An Apache `ErrorDocument` directive is registered
- The `.htaccess` config can be moved to Apache vhost config if preferred


## Local Preview

Host a local copy at <http://localhost:8080>:

```bash
docker run
    --rm \
    -p 8080:80 \
    -v "$PWD":/usr/local/apache2/htdocs:ro \
    httpd:2.4 \
    sh -c "sed -i \
        -e 's/^#LoadModule rewrite_module/LoadModule rewrite_module/' \
        -e 's/AllowOverride None/AllowOverride FileInfo Indexes/' \
        conf/httpd.conf && exec httpd-foreground"
```
