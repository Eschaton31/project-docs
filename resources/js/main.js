import { Handler, registerHandlers } from 'pagedjs'
import { createPinia } from 'pinia'
import Prism from 'prismjs'
import 'prismjs/components/prism-ini.js'
import 'prismjs/components/prism-sql.js'
import 'prismjs/components/prism-xml-doc.js'
import 'prismjs/components/prism-yaml.js'
import 'prismjs/plugins/command-line/prism-command-line.css'
import 'prismjs/plugins/command-line/prism-command-line.js'
import 'prismjs/plugins/line-numbers/prism-line-numbers.css'
import 'prismjs/plugins/line-numbers/prism-line-numbers.js'

import { createApp } from 'vue'

import '../css/app.css'
import App from './App.vue'
import router from './router'

Prism.languages.r = {
  comment: /#.*/,
  string: {
    pattern: /(['"])(?:\\.|(?!\1)[^\\\r\n])*\1/,
    greedy: true,
  },
  'percent-operator': {
    // Includes user-defined operators
    // and %%, %*%, %/%, %in%, %o%, %x%
    pattern: /%[^%\s]*%/,
    alias: 'operator',
  },
  boolean: /\b(?:FALSE|TRUE)\b/,
  ellipsis: /\.\.(?:\.|\d+)/,
  number: [/\b(?:Inf|NaN)\b/, /(?:\b0x[\dA-Fa-f]+(?:\.\d*)?|\b\d+(?:\.\d*)?|\B\.\d+)(?:[EePp][+-]?\d+)?[iL]?/],
  keyword:
    /\b(?:NA|NA_character_|NA_complex_|NA_integer_|NA_real_|NULL|break|else|for|function|if|in|next|repeat|while)\b/,
  operator: /->?>?|<(?:=|<?-)?|[>=!]=?|::?|&&?|\|\|?|[+*\/^$@~]/,
  punctuation: /[(){}\[\],;]/,
  constant: /\b[A-Za-z][A-Za-z0-9._]*\b(?:\s|\()/,
  function: [
    // Class / object method access: object$method or `weird name`$method
    {
      pattern:
        /(?:`[^`]+`|\b[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)*\b)\$(?:`[^`]+`|[A-Za-z][A-Za-z0-9_]*)(?!\s*\()/,
      greedy: true,
      alias: 'function',
    },
    // Bare function names (unchanged)
    {
      pattern: /\b[A-Za-z][A-Za-z0-9._]*(?!\s|\()\b/,
      alias: 'function',
    },
  ],
}
Prism.languages.clickhouse = Prism.languages.extend('sql', {
  // Add ClickHouse-specific keywords
  keyword: /\b(?:ENGINE|SETTINGS|ORDER BY|PARTITION BY|SAMPLE BY|TTL|DISTINCT|ARRAY|MAP)\b/i,

  // Add specialized ClickHouse functions
  function: {
    pattern:
      /\b(?:tuple|arrayJoin|JSONExtract|toStartOfInterval|uniqCombined|any|groupArray|runningAccumulate|dictGet|dictHas)\b/i,
    greedy: true,
  },
})
Prism.languages.caddyfile = {
  comment: {
    pattern: /#.*/,
    greedy: true,
  },

  directive: {
    // Matches top-level directives like: reverse_proxy, root, php_fastcgi
    pattern: /^[\t ]*[a-zA-Z0-9._-]+(?=\s|$)/m,
    alias: 'keyword',
  },

  block: {
    // Matches blocks like: handle { ... }, route { ... }
    pattern: /[a-zA-Z0-9._-]+\s*\{/,
    alias: 'function',
  },

  number: /\b\d+(?:\.\d+)?\b/,

  boolean: {
    pattern: /\b(?:true|false|on|off)\b/,
    alias: 'boolean',
  },

  string: {
    pattern: /"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'/,
    greedy: true,
  },

  ip: {
    pattern: /\b\d{1,3}(?:\.\d{1,3}){3}\b/,
    alias: 'constant',
  },

  path: {
    pattern: /(?:\/[^\s#{}]+)+/,
    alias: 'url',
  },

  operator: /[{}]/,

  punctuation: /[():,]/,
}
// Add ClickHouse highlighting by extending Prism's SQL grammar
Prism.languages.clickhouse = Prism.languages.extend('sql', {})

// Maintainable list of ClickHouse functions (sample; expand as needed)
const clickhouseFunctions = [
  // Type conversions
  'toInt8',
  'toInt16',
  'toInt32',
  'toInt64',
  'toUInt64',
  'toFloat32',
  'toFloat64',
  'toDecimal',
  'toString',
  'toBool',
  // Date/time
  'toDate',
  'toDateTime',
  'toDateTime64',
  'toStartOfDay',
  'toStartOfMonth',
  'toStartOfHour',
  'addDays',
  'addMonths',
  'dateDiff',
  'formatDateTime',
  'parseDateTimeBestEffort',
  // Arrays & strings
  'array',
  'arrayJoin',
  'arrayElement',
  'arrayConcat',
  'length',
  'splitByChar',
  'has',
  'indexOf',
  'splitByString',
  'replaceOne',
  // Aggregates & window
  'sum',
  'avg',
  'min',
  'max',
  'count',
  'quantile',
  'runningDifference',
  'windowFunnel',
  // Utilities
  'cityHash64',
  'UUIDv7ToDateTime',
  'toUnixTimestamp',
  'now',
  'today',
  'yesterday',
  'dictGet',
  'dictHas',
]

// Build a case-insensitive whole-word regex safely
const funcPattern = new RegExp('\\b(?:' + clickhouseFunctions.join('|') + ')\\b', 'i')

// Insert token before 'keyword' so functions get styled as 'function'
Prism.languages.insertBefore('clickhouse', 'keyword', {
  'clickhouse-function': {
    pattern: funcPattern,
    alias: 'function',
  },
})

const createToc = (config) => {
  const content = config.content
  const tocElement = config.tocElement
  const titleElements = config.titleElements

  let tocElementDiv = content.querySelector(tocElement)
  // stored documents can switch the table of contents off
  if (!tocElementDiv) return

  let tocUl = document.createElement('ul')
  tocUl.id = 'list-toc-generated'
  tocUl.classList.add('gap-2')
  tocElementDiv.appendChild(tocUl)

  // add class to all title elements
  let tocElementNbr = 0
  for (var i = 0; i < titleElements.length; i++) {
    let titleHierarchy = i + 1
    let titleElement = content.querySelectorAll(titleElements[i])

    titleElement.forEach(function (element) {
      // add classes to the element
      element.classList.add('title-element')
      element.setAttribute('data-title-level', titleHierarchy)

      // add id if doesn't exist
      tocElementNbr++
      const idElement = element.id
      if (idElement == '') {
        element.id = 'title-element-' + tocElementNbr
      }
      let newIdElement = element.id
    })
  }

  // create toc list
  let tocElements = content.querySelectorAll('.title-element')

  for (var i = 0; i < tocElements.length; i++) {
    let tocElement = tocElements[i]

    let tocNewLi = document.createElement('li')

    // Add class for the hierarcy of toc
    tocNewLi.classList.add('toc-element')
    tocNewLi.classList.add('text-bene-gray')
    tocNewLi.classList.add('text-[10pt]')
    tocNewLi.classList.add('toc-element-level-' + tocElement.dataset.titleLevel)

    // Create the element
    tocNewLi.innerHTML = '<a href="#' + tocElement.id + '">' + tocElement.innerHTML + '</a>'
    tocUl.appendChild(tocNewLi)
  }
}

class TOCHandler extends Handler {
  constructor(chunker, polisher, caller) {
    super(chunker, polisher, caller)
  }

  beforeParsed(content) {
    createToc({
      content: content,
      tocElement: '#toc-page',
      titleElements: ['.primary-topic', '.secondary-topic', '.tertiary-topic', '.quaternary-topic'],
    })
  }
}

registerHandlers(TOCHandler)
registerHandlers(
  class FixPrism extends Handler {
    afterPageLayout(pageElement, page, breakToken) {
      document.querySelectorAll('pre.line-numbers').forEach((pre) => {
        Prism.plugins.lineNumbers.resize(pre)
      })
    }
  }
)

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(Prism)

app.mount('#app')
