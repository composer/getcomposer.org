import docsearch from '@docsearch/js';
import Plausible from 'plausible-tracker'
import '@docsearch/css';
import 'prismjs/themes/prism.css';
import '../css/style.css';
import 'prismjs';
import 'prismjs/components/prism-markup-templating';
import 'prismjs/components/prism-php';
import 'prismjs/components/prism-bash';
import 'prismjs/components/prism-json';
import 'prismjs/components/prism-json5';
import 'prismjs/components/prism-javascript';

function processQuery(query) {
    var parts = query.split(' ');

    parts = parts.map(function (part, index) {
        var word = part.trim();

        // quote --foo args so they are not parsed as negations but rather return actual results
        if (word.length >= 2 && word[0] === '-' && word[1] === '-') {
            word = '"' + word + (index === parts.length - 1 ? '' : '"');
        }
        // quote -x args
        if (word.length == 2 && word[0] === '-') {
            word = '"' + word + (index === parts.length - 1 ? '' : '"');
        }

        return word;
    });

    return parts.join(' ');
}

if (document.getElementById('docsearch')) {
    docsearch({
        container: '#docsearch',
        appId: 'DLYN25JSFJ',
        indexName: 'getcomposer',
        apiKey: '59b32c5fdb7d9198939d01eebcc4ecab',
        transformSearchClient: (searchClient) => {
          return {
            ...searchClient,
            search: (spec) => {
                let f = spec.requests[0].query;
                spec.requests[0].query = processQuery(spec.requests[0].query);

                return searchClient.search(spec);
            }
          };
        },
    });
}

const plausible = Plausible({
  domain: 'getcomposer.org',
  apiHost: 'https://getcomposer.org',
});
plausible.trackPageview();
