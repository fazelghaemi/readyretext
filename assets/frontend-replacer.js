/**
 * Ready ReText - Frontend Client-Side Replacer v4.0.0
 * This script ensures all dynamically loaded content is also processed.
 */
document.addEventListener('DOMContentLoaded', function () {
    // Check if we have rules to process
    if (typeof readyReTextData === 'undefined' || !readyReTextData.rules || readyReTextData.rules.length === 0) {
        return;
    }

    const rules = readyReTextData.rules.map(rule => {
        try {
            return {
                regex: new RegExp(rule.find, rule.flags),
                replace: rule.replace
            };
        } catch (e) {
            // Invalid regex pattern, skip this rule
            console.warn('Ready ReText: Invalid Regex Pattern Skipped ->', rule.find);
            return null;
        }
    }).filter(Boolean); // Filter out null (invalid) rules

    if (rules.length === 0) {
        return;
    }

    // Function to process a single text node
    function processTextNode(node) {
        // Skip nodes that are already processed or part of script/style tags
        if (node.nodeValue.trim() === '' || node.parentElement.closest('script, style, textarea, .retext-processed')) {
            return;
        }

        let originalText = node.nodeValue;
        let newText = originalText;

        for (const rule of rules) {
            newText = newText.replace(rule.regex, rule.replace);
        }

        if (newText !== originalText) {
            node.nodeValue = newText;
            // Mark parent as processed to avoid re-processing
            if (node.parentElement) {
                node.parentElement.classList.add('retext-processed');
            }
        }
    }

    // Function to walk through all nodes and find text nodes
    function walkAndProcess(rootNode) {
        const walker = document.createTreeWalker(rootNode, NodeFilter.SHOW_TEXT, null, false);
        let node;
        while ((node = walker.nextNode())) {
            processTextNode(node);
        }
    }

    // Initial run on the whole document body
    walkAndProcess(document.body);

    // Set up a MutationObserver to watch for future changes
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach(function (newNode) {
                    if (newNode.nodeType === Node.ELEMENT_NODE) {
                        walkAndProcess(newNode);
                    } else if (newNode.nodeType === Node.TEXT_NODE) {
                        processTextNode(newNode);
                    }
                });
            }
        });
    });

    // Start observing the body for new nodes
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});
