/*
 * Turtle Template Form Knitter - paste filter for Contact Form 7 textareas.
 *
 * When HTML is pasted into a CF7 textarea, only these tags are kept:
 * h1, h2, h3, h4, a, p, b, strong, i, em, ul, ol, li, br. All
 * attributes are removed, except href on <a>, and only for http,
 * https, or mailto URLs.
 * Other tags are removed, but their text is kept. Script, style, and
 * similar elements are removed with their content.
 *
 * The cleaned HTML is inserted as text, so the textarea shows the
 * tags. The server filters the value again, so this is only a
 * convenience for the user, not the security check.
 *
 * Also, after a form is submitted, any errors the plugin found are
 * shown in a popup (ttfk_fShowErrors).
 *
 * Function prefix: ttfk_f
 */
(function () {
    'use strict';

    const ttfk_cTagList = ['H1', 'H2', 'H3', 'H4', 'A', 'P', 'B', 'STRONG', 'I', 'EM',
        'UL', 'OL', 'LI', 'BR'];

    // Kept tags that are followed by a new line in the textarea.
    const ttfk_cLineEndList = ['H1', 'H2', 'H3', 'H4', 'P', 'UL', 'OL', 'LI', 'BR'];

    // Removed with all their content.
    const ttfk_cDropList = ['SCRIPT', 'STYLE', 'TEMPLATE', 'NOSCRIPT', 'IFRAME', 'OBJECT',
        'EMBED', 'SVG', 'MATH', 'HEAD', 'TITLE', 'META', 'LINK'];

    // Removed tags that end a line, so their text does not run together.
    const ttfk_cBreakList = ['DIV', 'TR', 'H5', 'H6', 'BLOCKQUOTE', 'PRE'];

    const ttfk_cProtocolList = ['http:', 'https:', 'mailto:'];

    // ----------------------------------------
    // Return the href if it is a http, https, or mailto URL, else ''.
    function ttfk_fSafeHref(pHref) {
        if (!pHref) {
            return '';
        }
        try {
            const tUrl = new URL(pHref.trim());
            return ttfk_cProtocolList.includes(tUrl.protocol) ? tUrl.href : '';
        } catch (tErr) {
            return '';
        }
    }

    // ----------------------------------------
    // Copy the allowed parts of pNode's children into pOut.
    function ttfk_fCopyNodes(pNode, pOut, pDoc) {
        for (const tChild of pNode.childNodes) {
            if (tChild.nodeType === Node.TEXT_NODE) {
                pOut.appendChild(pDoc.createTextNode(tChild.nodeValue.replace(/\s+/g, ' ')));
                continue;
            }
            if (tChild.nodeType !== Node.ELEMENT_NODE) {
                continue; // comments, etc.
            }
            const tTag = tChild.tagName.toUpperCase();
            if (ttfk_cDropList.includes(tTag)) {
                continue;
            }
            if (ttfk_cTagList.includes(tTag)) {
                const tNew = pDoc.createElement(tTag.toLowerCase());
                if (tTag === 'A') {
                    const tHref = ttfk_fSafeHref(tChild.getAttribute('href'));
                    if (tHref) {
                        tNew.setAttribute('href', tHref);
                    }
                }
                ttfk_fCopyNodes(tChild, tNew, pDoc);
                pOut.appendChild(tNew);
                if (ttfk_cLineEndList.includes(tTag)) {
                    pOut.appendChild(pDoc.createTextNode('\n'));
                }
                continue;
            }
            ttfk_fCopyNodes(tChild, pOut, pDoc);
            if (ttfk_cBreakList.includes(tTag)) {
                pOut.appendChild(pDoc.createTextNode('\n'));
            }
        }
    }

    // ----------------------------------------
    // Return the cleaned HTML string. DOMParser does not run scripts
    // or load images.
    function ttfk_fCleanHtml(pHtml) {
        const tSrc = new DOMParser().parseFromString(pHtml, 'text/html');
        const tDoc = document.implementation.createHTMLDocument('');
        const tOut = tDoc.createElement('div');
        ttfk_fCopyNodes(tSrc.body, tOut, tDoc);
        return tOut.innerHTML.replace(/[ \t]*\n[ \t]*/g, '\n').replace(/\n{2,}/g, '\n').trim();
    }

    // ----------------------------------------
    function ttfk_fOnPaste(pEvent) {
        const tArea = pEvent.target;
        if (!(tArea instanceof HTMLTextAreaElement) || !tArea.closest('.wpcf7-form')) {
            return;
        }
        const tData = pEvent.clipboardData;
        const tHtml = tData ? tData.getData('text/html') : '';
        if (!tHtml) {
            return; // plain text paste, let the browser do it
        }
        pEvent.preventDefault();
        tArea.setRangeText(ttfk_fCleanHtml(tHtml), tArea.selectionStart, tArea.selectionEnd, 'end');
        tArea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // ----------------------------------------
    // CF7 fires wpcf7submit when its reply comes back. The plugin adds
    // its errors to the reply as ttfk_errors (ttfk_fFeedbackResponse).
    function ttfk_fShowErrors(pEvent) {
        const tResponse = pEvent.detail ? pEvent.detail.apiResponse : null;
        const tErrorList = tResponse ? tResponse.ttfk_errors : null;
        if (!Array.isArray(tErrorList) || !tErrorList.length) {
            return;
        }
        alert('Turtle Template Form Knitter errors (post not created):\n- ' + tErrorList.join('\n- '));
    }

    document.addEventListener('paste', ttfk_fOnPaste);
    document.addEventListener('wpcf7submit', ttfk_fShowErrors);
}());
