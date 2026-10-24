// "How to play" help center (/help): instant search, #article deep links and the active section
// in the contents. Loaded with the page's CSP nonce; the page works without it (?q= search on the
// server). Folding and matching mirror App\Modules\Core\Support\HelpCenter::fold()/terms()/search().
(function () {
    'use strict';

    var form = document.getElementById('help-search');
    var input = document.getElementById('help-q');
    var status = document.getElementById('help-status');
    var empty = document.getElementById('help-empty');
    if (!form || !input || !status || !empty) {
        return;
    }

    document.documentElement.classList.add('js');

    var sections = Array.prototype.slice.call(document.querySelectorAll('.help-section'));
    var articles = Array.prototype.slice.call(document.querySelectorAll('details.article'));
    var chips = document.querySelector('.chips');
    var cards = document.getElementById('help-cards');
    var stopWords = (form.getAttribute('data-stop-words') || '').split(' ');
    var MAX_TERMS = 8;
    var MAX_LENGTH = 100;

    /** Lowercase ASCII, accents removed, every other character a space (HelpCenter::fold). */
    function fold(text) {
        return text
            .replace(/\*\*/g, ' ')
            .replace(/[œŒ]/g, 'oe').replace(/[æÆ]/g, 'ae').replace(/ß/g, 'ss')
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();
    }

    /** Search terms: no stop words or one-letter words, plural "s" trimmed (HelpCenter::terms). */
    function terms(query) {
        var words = fold(query.slice(0, MAX_LENGTH)).split(' ');
        var out = [];
        words.forEach(function (word) {
            if (word.length < 2 || stopWords.indexOf(word) !== -1) {
                return;
            }
            var term = word.length > 3 && word.charAt(word.length - 1) === 's' ? word.slice(0, -1) : word;
            if (out.indexOf(term) === -1) {
                out.push(term);
            }
        });
        return out.slice(0, MAX_TERMS);
    }

    // Each article's searchable text, folded once.
    var index = articles.map(function (article) {
        var parts = Array.prototype.map.call(article.querySelectorAll('[data-search]'), function (el) {
            return el.textContent;
        });
        parts.push(article.getAttribute('data-keywords') || '');
        return fold(parts.join(' '));
    });

    /** Removes the <mark>s of the previous search. */
    function clearMarks() {
        Array.prototype.forEach.call(document.querySelectorAll('#help-articles mark'), function (mark) {
            var parent = mark.parentNode;
            parent.replaceChild(document.createTextNode(mark.textContent), mark);
            parent.normalize();
        });
    }

    /** Wraps every occurrence of the terms inside an element's text nodes in <mark>. */
    function highlight(root, list) {
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        var nodes = [];
        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }
        nodes.forEach(function (node) {
            var text = node.nodeValue;
            // Folded text with a map from each folded character back to its original index.
            var folded = '';
            var map = [];
            for (var i = 0; i < text.length; i++) {
                var piece = text.charAt(i).replace(/[œŒ]/, 'oe').replace(/[æÆ]/, 'ae').replace(/ß/, 'ss')
                    .normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
                for (var j = 0; j < piece.length; j++) {
                    folded += piece.charAt(j);
                    map.push(i);
                }
            }
            var ranges = [];
            list.forEach(function (term) {
                var at = folded.indexOf(term);
                while (at !== -1) {
                    // Mark the whole word (a trimmed plural "s" or a longer word stays readable).
                    var end = map[at + term.length - 1] + 1;
                    while (end < text.length && /[0-9A-Za-zÀ-ɏ]/.test(text.charAt(end))) {
                        end++;
                    }
                    ranges.push([map[at], end]);
                    at = folded.indexOf(term, at + term.length);
                }
            });
            if (ranges.length === 0) {
                return;
            }
            ranges.sort(function (a, b) { return a[0] - b[0]; });
            var merged = [];
            ranges.forEach(function (range) {
                var last = merged[merged.length - 1];
                if (last && range[0] <= last[1]) {
                    last[1] = Math.max(last[1], range[1]);
                } else {
                    merged.push(range.slice());
                }
            });
            var fragment = document.createDocumentFragment();
            var pos = 0;
            merged.forEach(function (range) {
                fragment.appendChild(document.createTextNode(text.slice(pos, range[0])));
                var mark = document.createElement('mark');
                mark.textContent = text.slice(range[0], range[1]);
                fragment.appendChild(mark);
                pos = range[1];
            });
            fragment.appendChild(document.createTextNode(text.slice(pos)));
            node.parentNode.replaceChild(fragment, node);
        });
    }

    function resultText(count) {
        if (count === 0) {
            return form.getAttribute('data-result-none');
        }
        return count === 1
            ? form.getAttribute('data-result-one')
            : form.getAttribute('data-result-many').replace(':count', String(count));
    }

    var lastQuery = null;

    function run(updateUrl) {
        var query = input.value;
        if (query === lastQuery) {
            return;
        }
        lastQuery = query;
        clearMarks();
        var list = terms(query);

        if (list.length === 0) {
            articles.forEach(function (article) {
                article.hidden = false;
                article.open = false;
            });
            sections.forEach(function (section) { section.hidden = false; });
            empty.hidden = true;
            status.textContent = '';
            if (chips) {
                chips.hidden = false;
            }
            if (cards) {
                cards.hidden = false;
            }
        } else {
            // Every term (HelpCenter::search); when nothing matches them all, any term.
            var hits = index.map(function (text) {
                return list.filter(function (term) { return text.indexOf(term) !== -1; }).length;
            });
            var needed = hits.indexOf(list.length) !== -1 ? list.length : 1;
            var count = 0;
            articles.forEach(function (article, i) {
                var match = hits[i] >= needed;
                article.hidden = !match;
                article.open = match;
                if (match) {
                    count++;
                    Array.prototype.forEach.call(article.querySelectorAll('[data-search]'), function (el) {
                        highlight(el, list);
                    });
                }
            });
            sections.forEach(function (section) {
                section.hidden = section.querySelector('details.article:not([hidden])') === null;
            });
            empty.hidden = count !== 0;
            status.textContent = resultText(count);
            if (chips) {
                chips.hidden = true;
            }
            if (cards) {
                cards.hidden = true;
            }
        }

        if (updateUrl && window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            if (query.trim() === '') {
                url.searchParams.delete('q');
            } else {
                url.searchParams.set('q', query.trim());
            }
            url.hash = '';
            window.history.replaceState(null, '', url.toString());
        }
    }

    var timer = null;
    input.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () { run(true); }, 80);
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        run(true);
    });
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && input.value !== '') {
            input.value = '';
            run(true);
        }
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-clear]'), function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            input.value = '';
            run(true);
            input.focus();
        });
    });

    /** Opens the article (or shows the section) named by the URL hash, clearing a search that hides it. */
    function openHash() {
        var id = decodeURIComponent(window.location.hash.slice(1));
        if (!id) {
            return;
        }
        var target = document.getElementById(id);
        if (!target || !(target.matches('details.article') || target.matches('.help-section'))) {
            return;
        }
        if (target.hidden || (target.parentElement && target.parentElement.hidden)) {
            input.value = '';
            run(false);
        }
        if (target.matches('details.article')) {
            target.open = true;
        }
        target.scrollIntoView();
        var summary = target.querySelector('summary');
        if (summary) {
            summary.focus({ preventScroll: true });
        }
    }
    window.addEventListener('hashchange', openHash);

    // The server already filtered a ?q= search; repeat it here to add the highlights.
    lastQuery = '';
    if (input.value.trim() !== '') {
        run(false);
    }
    openHash();

    // Contents: mark the section being read.
    var tocLinks = {};
    Array.prototype.forEach.call(document.querySelectorAll('[data-toc]'), function (link) {
        tocLinks[link.getAttribute('data-toc')] = link;
    });
    if ('IntersectionObserver' in window && sections.length > 0) {
        var current = null;
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    if (current && tocLinks[current]) {
                        tocLinks[current].removeAttribute('aria-current');
                    }
                    current = entry.target.id;
                    if (tocLinks[current]) {
                        tocLinks[current].setAttribute('aria-current', 'true');
                    }
                }
            });
        }, { rootMargin: '0px 0px -70% 0px' });
        sections.forEach(function (section) { observer.observe(section); });
    }
})();
