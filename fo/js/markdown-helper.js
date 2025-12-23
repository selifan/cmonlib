/* Dynamic Markdown parser using jQuery, markdown-it and mermaid (thanks, mashaGPT!), 2025-12-22 */
(function(global, $){
  if (!$) throw new Error('jQuery ($) is required for this module');

  // Utility: generate unique ids
  function uid(prefix){
    return prefix + '_' + Math.random().toString(36).slice(2,9) + '_' + Date.now().toString(36);
  }

  // Default configuration
  var _cfg = {
    mermaidConfig: { startOnLoad: false },
    markdownItOptions: { html: true, linkify: true, typographer: true },
    markdownItInstance: null // if provided, will use this instance
  };

  // Extract mermaid fences and replace with placeholder DIVs
  function extractMermaidBlocks(markdown){
    var blocks = new Map();
    var transformed = markdown.replace(/```mermaid\s*\n([\s\S]*?)```/g, function(_, code){
      var id = uid('mermaid');
      blocks.set(id, code.trim());
      return '<div class="mermaid-placeholder" data-mermaid-id="' + id + '"></div>';
    });
    return { text: transformed, blocks: blocks };
  }

  // Render mermaid placeholders into actual diagrams inside a jQuery container
  function renderMermaidBlocks($container, blocks, mermaidConfig){
    return new Promise(function(resolve){
      if (!window.mermaid){
        console.warn('mermaid.js not found. Include mermaid.min.js');
        return resolve();
      }

      // Initialize mermaid with merged config (ensure startOnLoad=false)
      var cfg = $.extend(true, {}, { startOnLoad: false }, mermaidConfig || {});
      try{ mermaid.initialize(cfg); }catch(e){ console.warn('mermaid.initialize failed', e); }

      // Replace placeholders with .mermaid divs
      blocks.forEach(function(code, id){
        var $ph = $container.find('.mermaid-placeholder[data-mermaid-id="' + id + '"]');
        if ($ph.length === 0) return;
        var $div = $('<div/>', { 'class': 'mermaid', 'data-mermaid-id': id }).text(code);
        $ph.replaceWith($div);
      });

      var $mermaidEls = $container.find('.mermaid');
      if ($mermaidEls.length === 0) return resolve();

      // Try bulk init if available
      try{
        if (typeof mermaid.init === 'function'){
          // mermaid.init(config, elements) - some versions accept NodeList/array
          // Convert jQuery collection to Array of DOM nodes
          var nodes = $mermaidEls.toArray();
          mermaid.init(undefined, nodes);
          return resolve();
        }
      }catch(err){
        // ignore and fallback to per-element render
        console.warn('mermaid.init failed, falling back to per-element render', err);
      }

      // Per-element rendering (supports newer mermaid.render returning Promise)
      var promises = [];
      $mermaidEls.each(function(){
        var el = this;
        var $el = $(el);
        var code = $el.text() || '';
        var renderId = el.id || uid('m');
        el.id = renderId;
        try{
          if (typeof mermaid.render === 'function'){
            var result = mermaid.render(renderId, code);
            if (result && typeof result.then === 'function'){
              var p = result.then(function(res){
                if (typeof res === 'string') $el.html(res);
                else if (res && res.svg) $el.html(res.svg);
                else $el.html(String(res));
              }).catch(function(err){
                console.error('mermaid.render error', err);
                $el.text('Ошибка рендеринга диаграммы: ' + (err && err.message ? err.message : err));
              });
              promises.push(p);
            }else if (typeof result === 'string'){
              $el.html(result);
            }else if (result && result.svg){
              $el.html(result.svg);
            }else{
              // fallback
              try{ mermaid.init(undefined, el); }catch(e){ console.warn('single init fallback failed', e); }
            }
          }else{
            // If mermaid.render not available, try init on element
            try{ mermaid.init(undefined, el); }catch(e){ console.warn('mermaid.init single failed', e); }
          }
        }catch(err){
          console.error('Error rendering mermaid element', err);
          $el.text('mermaid render error: ' + (err && err.message ? err.message : err));
        }
      });

      if (promises.length) Promise.all(promises).then(resolve).catch(function(){ resolve(); });
      else resolve();
    });
  }

  // Process markdown and append result into jQuery container
  function processMarkdownAppend($container, markdown, options){
    options = options || {};
    var mergedOptions = $.extend(true, {}, _cfg, options || {});

    var mdInstance = mergedOptions.markdownItInstance;
    if (!mdInstance){
      if (window.markdownit){
        mdInstance = window.markdownit(mergedOptions.markdownItOptions);
      }else{
        throw new Error('markdown-it not found. Include markdown-it.min.js on the page.');
      }
    }

    var extracted = extractMermaidBlocks(markdown);
    var html = mdInstance.render(extracted.text);

    // Append HTML to container
    $container.append(html);

    // Render mermaid blocks within this container
    return renderMermaidBlocks($container, extracted.blocks, mergedOptions.mermaidConfig);
  }

  // Convenience: add chat message (creates wrapper div and processes markdown)
  function addMessage($container, markdown, className, options){
    var $msg = $('<div/>', { 'class': 'md-block' + (className ? ' ' + className : '') });
    $container.append($msg);
    return processMarkdownAppend($msg, markdown, options).then(function(){ return $msg; });
  }

  // Init allows overriding defaults
  function init(options){
    _cfg = $.extend(true, {}, _cfg, options || {});
    return _cfg;
  }

  // Export
  global.MermaidMarkdown = {
    init: init,
    processMarkdownAppend: processMarkdownAppend,
    addMessage: addMessage
  };

})(window, window.jQuery);