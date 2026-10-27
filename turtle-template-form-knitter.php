<?php
/*
 * Plugin Name: Turtle Template Form Knitter
 * Plugin URI: https://github.com/TurtleEngr/WP-turtle-template-form-knitter
 * Description: This plugin knits form fields into templates to generate posts that are almost publication-ready.
 * Version: VERSION
 * Requires at least: 6.0
 * Requires Plugins: contact-form-7, flamingo
 * Requires PHP: 8.0
 * Author: TurtleEngr
 * Author URI: https://github.com/TurtleEngr
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/*
 * Constant prefix: ttfk_c
 * Function prefix: ttfk_f
 */

if (!defined('ABSPATH')) {
    exit;
}

const ttfk_cSlug = 'turtle-template-form-knitter';

// Special syntax lines in the template page that set post attributes.
// They are between the ttfk_cAttrBegin and ttfk_cAttrEnd lines.
const ttfk_cAttrList = ['title', 'categories', 'tags', 'publish-date', 'expire-date', 'expire-time'];
const ttfk_cAttrBegin = 'ATTR-BEGIN';
const ttfk_cAttrEnd = 'ATTR-END';

// Meta key used by the "ninja-auto-post-expire" plugin. Format: "Y-m-d H:i"
const ttfk_cExpireMetaKey = '_njtape_expiration_date';

// Attachment meta key: the image sizes still need to be made.
const ttfk_cNeedMetaKey = '_ttfk_need_meta';

// HTML allowed in textarea values. Only <a> has an attribute (href).
const ttfk_cHtmlTagList = [
    'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'p' => [],
    'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'a' => ['href' => true],
    'ul' => [], 'ol' => [], 'li' => [], 'br' => [],
];
const ttfk_cHtmlProtocolList = ['http', 'https', 'mailto'];

add_action('plugins_loaded', function () {
    if (!class_exists('WPCF7') || !class_exists('Flamingo_Inbound_Message')) {
        add_action('admin_notices', function () {
            if (!current_user_can('activate_plugins')) {
                return;
            }
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Turtle Template Form Knitter</strong> requires the Contact Form 7 and Flamingo plugins to be installed and active.';
            echo '</p></div>';
        });
        return;
    }
    // Uploads are saved first (wpcf7_before_send_mail), then the post is
    // created after Flamingo saves the message (wpcf7_after_flamingo).
    // Both run in the same request.
    add_action('wpcf7_before_send_mail', 'ttfk_fOnBeforeCf7SendMail');
    add_action('wpcf7_after_flamingo', 'ttfk_fAfterFlamingo');
    // Errors are added to the reply to the browser, which shows them
    // in a popup (js/ttfk-paste.js). This runs after both of the above.
    add_filter('wpcf7_feedback_response', 'ttfk_fFeedbackResponse');
    // Clean HTML pasted into the form's textareas.
    add_action('wpcf7_enqueue_scripts', 'ttfk_fEnqueuePasteScript');
});

// Documentation and Support links on the admin Plugins page. Added
// even when the required plugins are not active.
add_filter('plugin_row_meta', 'ttfk_fMetaLinks', 10, 2);

// Make image sizes for saved uploads. Added even when the required
// plugins are not active, so no saved upload is left without them.
add_action('admin_init', 'ttfk_fMakeAttachMeta');

// ----------------------------------------
// Add links after the Version and Author in this plugin's row.
function ttfk_fMetaLinks($pLinks, $pFile)
{
    if ($pFile !== plugin_basename(__FILE__)) {
        return $pLinks;
    }
    $tRowMeta = [
        'docs'    => '<a href="' . esc_url('https://turtleengr.github.io/WP-turtle-template-form-knitter/')
            . '" target="_blank" rel="noopener noreferrer">Documentation</a>',
        'support' => '<a href="' . esc_url('https://turtleengr.github.io/WP-turtle-template-form-knitter/index.html#support')
            . '" target="_blank" rel="noopener noreferrer">Support</a>',
    ];
    return array_merge($pLinks, $tRowMeta);
}

// ----------------------------------------
function ttfk_fEnqueuePasteScript()
{
    $tFile = 'js/ttfk-paste.js';
    wp_enqueue_script(ttfk_cSlug . '-paste', plugins_url($tFile, __FILE__), [],
        (string) filemtime(plugin_dir_path(__FILE__) . $tFile), true);
}

// ----------------------------------------
// Keep only the ttfk_cHtmlTagList tags, with no attributes except a
// safe href on <a>. Script and style elements are removed with their
// content, and HTML comments are removed (they could add block markup).
function ttfk_fCleanHtml($pHtml)
{
    $pHtml = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $pHtml);
    $pHtml = preg_replace('/<!--.*?(-->|$)/s', '', $pHtml);
    return force_balance_tags(wp_kses($pHtml, ttfk_cHtmlTagList, ttfk_cHtmlProtocolList));
}

// ----------------------------------------
// Save an error message for the current submission. Call with no
// argument to get the saved list. The list is kept in memory for this
// request (one form submission), and is shown with ttfk_fFeedbackResponse.
function ttfk_fErrorLog($pMsg = null)
{
    static $tMsgList = [];

    if ($pMsg !== null) {
        $tMsgList[] = $pMsg;
    }
    return $tMsgList;
}

// ----------------------------------------
// Add the saved errors to CF7's reply to the browser, as ttfk_errors.
function ttfk_fFeedbackResponse($pResponse)
{
    $tErrorList = ttfk_fErrorLog();
    if ($tErrorList) {
        $pResponse['ttfk_errors'] = $tErrorList;
    }
    return $pResponse;
}

// ----------------------------------------
// Replace chars not in [a-zA-Z0-9._-] with "_", then collapse
// repeated "_".
function ttfk_fNormalizeFileName($pFileName)
{
    $pFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $pFileName);
    return preg_replace('/_+/', '_', $pFileName);
}

// ----------------------------------------
// If the file exists, insert "_N" before the extension, N = 1, 2,
// ... until unused.
function ttfk_fUniqueFileName($pFilePath)
{
    if (!file_exists($pFilePath)) {
        return $pFilePath;
    }
    $tInfo = pathinfo($pFilePath);
    $tExt = isset($tInfo['extension']) ? '.' . $tInfo['extension'] : '';
    $tN = 1;
    do {
        $tNewPath = $tInfo['dirname'] . '/' . $tInfo['filename'] . '_' . $tN . $tExt;
        $tN++;
    } while (file_exists($tNewPath));
    return $tNewPath;
}

// ----------------------------------------
// Copy an uploaded image to the uploads dir and add it to the Media
// Library. Returns the attachment ID, or false if the file was not
// added to the Media Library. The image sizes are made later, by
// ttfk_fMakeAttachMeta, the next time an admin page is loaded.
function ttfk_fCreateAttachment($pFileName)
{
    // The extension must also be an allowed image type, so a file is
    // never saved with an extension the web server would run or serve
    // as a page.
    $tExtType = wp_check_filetype(basename($pFileName));
    if (!$tExtType['type'] || strpos($tExtType['type'], 'image/') !== 0) {
        ttfk_fErrorLog('file extension not allowed, not saved: ' . basename($pFileName));
        return false;
    }
    // Only image types allowed by WordPress, detected from the file content.
    $tMimeType = wp_get_image_mime($pFileName);
    if (!$tMimeType || !in_array($tMimeType, get_allowed_mime_types(), true)) {
        ttfk_fErrorLog('file type not allowed, not saved: ' . basename($pFileName));
        return false;
    }
    $tUploadDir = wp_upload_dir();

    $tAttachFileName = $tUploadDir['path'] . '/' . ttfk_fNormalizeFileName(basename($pFileName));
    $tAttachFileName = apply_filters('ttfk_fCreateAttachment_file_name', $tAttachFileName);
    $tAttachFileName = ttfk_fUniqueFileName($tAttachFileName);
    if (!copy($pFileName, $tAttachFileName)) {
        ttfk_fErrorLog('copy failed, not saved: ' . $tAttachFileName);
        return false;
    }

    $tSkipMediaLibrary = apply_filters('ttfk_should_skip_save_attachment_to_media_library', false);
    if ($tSkipMediaLibrary) {
        return false;
    }

    $tAttachment = [
        'guid'           => str_replace($tUploadDir['basedir'], $tUploadDir['baseurl'], $tAttachFileName),
        'post_mime_type' => $tMimeType,
        'post_title'     => preg_replace('/\.[^.]+$/', '', basename($tAttachFileName)),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];
    $tAttachment = apply_filters('ttfk_before_insert_attachment', $tAttachment);
    $tAttachId = wp_insert_attachment($tAttachment, $tAttachFileName, 0, true);
    if (is_wp_error($tAttachId) || !$tAttachId) {
        $tReason = is_wp_error($tAttachId) ? ': ' . $tAttachId->get_error_message() : '';
        ttfk_fErrorLog('not added to Media Library, file removed: ' . $tAttachFileName . $tReason);
        wp_delete_file($tAttachFileName);
        return false;
    }
    add_post_meta($tAttachId, ttfk_cNeedMetaKey, 1, true);

    ttfk_fSaveAttachId($tAttachId);
    return $tAttachId;
}

// ----------------------------------------
// Make the metadata and image sizes for attachments saved by
// ttfk_fCreateAttachment. wp_generate_attachment_metadata is only
// loaded by WordPress for admin requests, so this runs on admin_init.
function ttfk_fMakeAttachMeta()
{
    $tIdList = get_posts([
        'post_type'   => 'attachment',
        'post_status' => 'inherit',
        'meta_key'    => ttfk_cNeedMetaKey,
        'fields'      => 'ids',
        'numberposts' => 10,
    ]);
    foreach ($tIdList as $tAttachId) {
        delete_post_meta($tAttachId, ttfk_cNeedMetaKey);
        $tFile = get_attached_file($tAttachId);
        if ($tFile && file_exists($tFile)) {
            wp_update_attachment_metadata($tAttachId, wp_generate_attachment_metadata($tAttachId, $tFile));
        }
    }
}

// ----------------------------------------
// Save all the uploaded files for the current submission.
function ttfk_fOnBeforeCf7SendMail()
{
    $tSubmission = WPCF7_Submission::get_instance();
    if (!$tSubmission) {
        return;
    }
    foreach ((array) $tSubmission->uploaded_files() as $tFilePath) {
        if (is_array($tFilePath)) {
            foreach ($tFilePath as $tValue) {
                ttfk_fCreateAttachment($tValue);
            }
        } else {
            ttfk_fCreateAttachment($tFilePath);
        }
    }
}

// ----------------------------------------
// Collect attachment IDs saved for the current submission.
// Called with no arg to get the list.
function ttfk_fSaveAttachId($pAttachId = null)
{
    static $tIdList = [];

    if ($pAttachId !== null && (int) $pAttachId > 0) {
        $tIdList[] = (int) $pAttachId;
    }
    return $tIdList;
}

// ----------------------------------------
function ttfk_fAfterFlamingo($pResult)
{
    if (empty($pResult['flamingo_inbound_id']) || empty($pResult['contact_form_id'])) {
        return;
    }
    if (isset($pResult['status']) && $pResult['status'] === 'spam') {
        return;
    }

    $tForm = WPCF7_ContactForm::get_instance((int) $pResult['contact_form_id']);
    if (!$tForm || !$tForm->is_true('create_post')) {
        return;
    }

    $tSlug = sanitize_title((string) $tForm->pref('post_template'));
    if ($tSlug === '') {
        ttfk_fErrorLog('post_template is not set for form ' . $tForm->id());
        return;
    }
    $tPage = get_page_by_path($tSlug, OBJECT, 'page');
    if (!$tPage) {
        ttfk_fErrorLog('post_template page not found: ' . $tSlug);
        return;
    }

    $tMsg = new Flamingo_Inbound_Message((int) $pResult['flamingo_inbound_id']);
    $tFields = is_array($tMsg->fields) ? $tMsg->fields : [];

    // Textarea values can have some HTML. See ttfk_fCleanHtml.
    $tHtmlFieldList = [];
    foreach ($tForm->scan_form_tags(['basetype' => 'textarea']) as $tTag) {
        $tHtmlFieldList[] = $tTag->name;
    }

    ttfk_fCreatePost($tPage->post_content, $tFields, ttfk_fSaveAttachId(), $tHtmlFieldList);
}

// ----------------------------------------
function ttfk_fCreatePost($pTemplate, $pFields, $pAttachIdList, $pHtmlFieldList = [])
{
    $tAttr = [];
    $tBlockList = [];

    $tSkipBlank = false;
    $tInAttr = false;
    foreach (parse_blocks($pTemplate) as $tBlock) {
        // Also drop the blank separator that followed a removed attribute block.
        if ($tSkipBlank && $tBlock['blockName'] === null && trim($tBlock['innerHTML']) === '') {
            $tSkipBlank = false;
            continue;
        }
        $tLineAttr = ttfk_fGetAttrLines($tBlock, $pFields, $tInAttr);
        $tSkipBlank = ($tLineAttr !== null);
        if ($tLineAttr !== null) {
            $tAttr = array_merge($tAttr, $tLineAttr);
            continue;
        }
        $tBlockList = array_merge($tBlockList, ttfk_fReplaceInBlock($tBlock, $pFields, $pHtmlFieldList));
    }
    if ($tInAttr) {
        ttfk_fErrorLog(ttfk_cAttrEnd . ' not found in the template');
    }

    $tTitle = isset($tAttr['title']) ? sanitize_text_field($tAttr['title']) : '';
    $tPost = [
        'post_type'    => 'post',
        'post_status'  => 'pending',
        'post_author'  => ttfk_fGetAdminId(),
        'post_title'   => $tTitle !== '' ? $tTitle : 'UNDEFINED',
        'post_content' => serialize_blocks($tBlockList),
    ];

    if (isset($tAttr['publish-date'])) {
        $tDate = ttfk_fParseDate($tAttr['publish-date']);
        if ($tDate) {
            $tPost['post_date'] = $tDate . ' 12:00:00';
            $tPost['post_date_gmt'] = get_gmt_from_date($tPost['post_date']);
        }
    }

    // Core applies kses to the content when the submitter lacks unfiltered_html.
    $tPostId = wp_insert_post(wp_slash($tPost), true);
    if (is_wp_error($tPostId)) {
        ttfk_fErrorLog('wp_insert_post failed: ' . $tPostId->get_error_message());
        return 0;
    }

    if (isset($tAttr['categories'])) {
        wp_set_object_terms($tPostId, ttfk_fGetTermIdList($tAttr['categories'], 'category'), 'category');
    }
    if (isset($tAttr['tags'])) {
        wp_set_object_terms($tPostId, ttfk_fGetTermIdList($tAttr['tags'], 'post_tag'), 'post_tag');
    }

    if (isset($tAttr['expire-date'])) {
        $tDate = ttfk_fParseDate($tAttr['expire-date']);
        if ($tDate) {
            $tTime = isset($tAttr['expire-time']) ? ttfk_fParseTime($tAttr['expire-time']) : '';
            update_post_meta($tPostId, ttfk_cExpireMetaKey, $tDate . ' ' . ($tTime ? $tTime : '00:00'));
        } else {
            ttfk_fErrorLog('invalid expire-date: "' . $tAttr['expire-date'] . '"');
        }
    }

    $tFeatured = false;
    foreach ($pAttachIdList as $tAttachId) {
        if (get_post_type($tAttachId) !== 'attachment') {
            continue;
        }
        wp_update_post(['ID' => $tAttachId, 'post_parent' => $tPostId]);
        if (!$tFeatured && wp_attachment_is_image($tAttachId)) {
            $tFeatured = set_post_thumbnail($tPostId, $tAttachId);
        }
    }

    return $tPostId;
}

// ----------------------------------------
// If the top-level block is in the ATTR-BEGIN ... ATTR-END section,
// return [attr => value, ...] for its "attr: value" lines, with {field}
// replaced (plain text). Else null. The section can span blocks, so
// pInAttr keeps the state between calls. The whole block is dropped.
function ttfk_fGetAttrLines($pBlock, $pFields, &$pInAttr)
{
    $tText = preg_replace('/<br\s*\/?>/i', "\n", (string) $pBlock['innerHTML']);
    $tText = html_entity_decode(wp_strip_all_tags($tText), ENT_QUOTES, 'UTF-8');
    // The editor often saves a space as &nbsp; which trim() and \s miss.
    $tText = str_replace("\xC2\xA0", ' ', $tText);

    $tInBlock = $pInAttr;
    $tAttr = [];
    $tPattern = '/^(' . implode('|', array_map('preg_quote', ttfk_cAttrList)) . ')\s*:\s*(.*)$/i';
    foreach (preg_split('/\R/', $tText) as $tLine) {
        $tLine = trim($tLine);
        if ($tLine === ttfk_cAttrBegin) {
            $pInAttr = true;
            $tInBlock = true;
        } elseif ($tLine === ttfk_cAttrEnd) {
            $pInAttr = false;
        } elseif ($pInAttr && preg_match($tPattern, $tLine, $tMatch)) {
            $tAttr[strtolower($tMatch[1])] = ttfk_fReplaceFields($tMatch[2], $pFields, false);
        }
    }
    return $tInBlock ? $tAttr : null;
}

// ----------------------------------------
// Replace {field} in the block's HTML (and its inner blocks). Block
// attributes (the JSON in the block comment) are not changed.
// Returns a list of blocks, because a paragraph with a {field} that
// has block HTML (p, h1-h4, ul, ol) is split into several blocks.
function ttfk_fReplaceInBlock($pBlock, $pFields, $pHtmlFieldList = [])
{
    if ($pBlock['blockName'] === 'core/paragraph') {
        $tSplitList = ttfk_fSplitParagraph($pBlock, $pFields, $pHtmlFieldList);
        if ($tSplitList !== null) {
            return $tSplitList;
        }
    }
    $pBlock['innerHTML'] = ttfk_fReplaceFields((string) $pBlock['innerHTML'], $pFields, true, $pHtmlFieldList);
    $tContentList = [];
    $tInnerList = [];
    $tN = 0;
    foreach ($pBlock['innerContent'] as $tChunk) {
        if (is_string($tChunk)) {
            $tContentList[] = ttfk_fReplaceFields($tChunk, $pFields, true, $pHtmlFieldList);
            continue;
        }
        // One null in innerContent for each inner block.
        foreach (ttfk_fReplaceInBlock($pBlock['innerBlocks'][$tN++], $pFields, $pHtmlFieldList) as $tInner) {
            $tInnerList[] = $tInner;
            $tContentList[] = null;
        }
    }
    $pBlock['innerContent'] = $tContentList;
    $pBlock['innerBlocks'] = $tInnerList;
    return [$pBlock];
}

// ----------------------------------------
// If the paragraph has a {field} whose value has block HTML, return
// the list of blocks: the paragraph text before the {field}, the
// blocks made from the value, the text after it. Else null.
function ttfk_fSplitParagraph($pBlock, $pFields, $pHtmlFieldList)
{
    if (!preg_match('#^(\s*<p\b[^>]*>)(.*)(</p>\s*)$#is', (string) $pBlock['innerHTML'], $tMatch)) {
        return null;
    }
    $tPartList = preg_split('/(\{[A-Za-z0-9_-]+\})/', $tMatch[2], -1, PREG_SPLIT_DELIM_CAPTURE);
    $tBlockList = [];
    $tText = '';
    $tFound = false;
    foreach ($tPartList as $tI => $tPart) {
        $tHtml = ($tI % 2) ? ttfk_fBlockHtmlValue(substr($tPart, 1, -1), $pFields, $pHtmlFieldList) : '';
        if ($tHtml === '') {
            $tText .= $tPart;
            continue;
        }
        $tFound = true;
        $tBlockList = array_merge($tBlockList, ttfk_fTextBlock($pBlock, $tMatch, $tText, $pFields, $pHtmlFieldList),
            ttfk_fHtmlToBlocks($tHtml));
        $tText = '';
    }
    if (!$tFound) {
        return null;
    }
    return array_merge($tBlockList, ttfk_fTextBlock($pBlock, $tMatch, $tText, $pFields, $pHtmlFieldList));
}

// ----------------------------------------
// A copy of the paragraph block with pText (its {field} replaced) as
// its text. Returns [] if there is no text.
function ttfk_fTextBlock($pBlock, $pMatch, $pText, $pFields, $pHtmlFieldList)
{
    $tEdge = '(\s|&nbsp;|<br\s*/?>)*';
    $pText = preg_replace("#^$tEdge|$tEdge\$#i", '', $pText);
    $pText = force_balance_tags(ttfk_fReplaceFields($pText, $pFields, true, $pHtmlFieldList));
    if (trim(str_replace('&nbsp;', ' ', wp_strip_all_tags($pText))) === '') {
        return [];
    }
    $tHtml = $pMatch[1] . $pText . $pMatch[3];
    $pBlock['innerHTML'] = $tHtml;
    $pBlock['innerContent'] = [$tHtml];
    return [$pBlock];
}

// ----------------------------------------
// The cleaned value of an HTML field, if it has block HTML. Else "".
function ttfk_fBlockHtmlValue($pName, $pFields, $pHtmlFieldList)
{
    if (!in_array($pName, $pHtmlFieldList, true) || !array_key_exists($pName, $pFields)) {
        return '';
    }
    $tHtml = ttfk_fCleanHtml(ttfk_fValueToString($pFields[$pName]));
    return preg_match('#<(p|h[1-4]|ul|ol|li)\b#i', $tHtml) ? $tHtml : '';
}

// ----------------------------------------
// Make editor blocks from cleaned HTML: p => paragraph, h1-h4 =>
// heading, ul/ol => list. Text between them becomes a paragraph.
function ttfk_fHtmlToBlocks($pHtml)
{
    $tDoc = ttfk_fLoadHtml($pHtml);
    $tBlockList = [];
    $tInline = '';
    foreach ($tDoc->getElementsByTagName('body')->item(0)->childNodes as $tNode) {
        $tName = ($tNode instanceof DOMElement) ? strtolower($tNode->nodeName) : '';
        if (!preg_match('/^(p|h[1-4]|ul|ol)$/', $tName)) {
            $tInline .= ($tName === 'li') ? ttfk_fNodeHtml($tDoc, $tNode, false) . '<br>' : $tDoc->saveHTML($tNode);
            continue;
        }
        $tBlockList = array_merge($tBlockList, ttfk_fParagraphBlock(ttfk_fTidyInline($tInline)));
        $tInline = '';
        if ($tName === 'ul' || $tName === 'ol') {
            $tBlockList[] = ttfk_fListBlock($tDoc, $tNode);
        } elseif ($tName === 'p') {
            $tBlockList = array_merge($tBlockList, ttfk_fParagraphBlock(ttfk_fNodeHtml($tDoc, $tNode, false)));
        } else {
            $tText = ttfk_fNodeHtml($tDoc, $tNode, false);
            $tLevel = (int) substr($tName, 1);
            if ($tText !== '') {
                $tBlockList[] = ttfk_fMakeBlock('core/heading', $tLevel === 2 ? [] : ['level' => $tLevel],
                    "\n<$tName class=\"wp-block-heading\">$tText</$tName>\n");
            }
        }
    }
    return array_merge($tBlockList, ttfk_fParagraphBlock(ttfk_fTidyInline($tInline)));
}

// ----------------------------------------
// Returns [paragraph block], or [] if pHtml is empty.
function ttfk_fParagraphBlock($pHtml)
{
    return $pHtml === '' ? [] : [ttfk_fMakeBlock('core/paragraph', [], "\n<p>$pHtml</p>\n")];
}

// ----------------------------------------
// A list block, with a list-item block for each li. A ul/ol right
// inside the list is put in the item before it.
function ttfk_fListBlock($pDoc, $pList)
{
    $tTag = strtolower($pList->nodeName);
    $tItemList = []; // [[text, [nested list blocks]], ...]
    foreach ($pList->childNodes as $tNode) {
        $tName = ($tNode instanceof DOMElement) ? strtolower($tNode->nodeName) : '';
        if ($tName === 'ul' || $tName === 'ol') {
            if (!$tItemList) {
                $tItemList[] = ['', []];
            }
            $tItemList[count($tItemList) - 1][1][] = ttfk_fListBlock($pDoc, $tNode);
        } elseif ($tName === 'li') {
            $tNestedList = [];
            foreach ($tNode->childNodes as $tChild) {
                if (in_array(strtolower($tChild->nodeName), ['ul', 'ol'], true)) {
                    $tNestedList[] = ttfk_fListBlock($pDoc, $tChild);
                }
            }
            $tItemList[] = [ttfk_fNodeHtml($pDoc, $tNode, true), $tNestedList];
        } else {
            $tText = ttfk_fTidyInline($pDoc->saveHTML($tNode));
            if ($tText !== '') {
                $tItemList[] = [$tText, []];
            }
        }
    }
    $tBlockList = [];
    foreach ($tItemList as $tItem) {
        $tBlockList[] = ttfk_fMakeBlock('core/list-item', [], "\n<li>" . $tItem[0], $tItem[1], "</li>\n");
    }
    return ttfk_fMakeBlock('core/list', $tTag === 'ol' ? ['ordered' => true] : [],
        "\n<$tTag class=\"wp-block-list\">", $tBlockList, "</$tTag>\n");
}

// ----------------------------------------
// The inline HTML in pNode. Block tags inside it (p, h1-h4, li) are
// removed, with a <br> after their text. Lists are skipped if
// pSkipList (a list-item has them as inner blocks), else their items
// are lines.
function ttfk_fNodeHtml($pDoc, $pNode, $pSkipList)
{
    $tHtml = '';
    foreach ($pNode->childNodes as $tChild) {
        $tName = ($tChild instanceof DOMElement) ? strtolower($tChild->nodeName) : '';
        if ($tName === 'ul' || $tName === 'ol') {
            $tHtml .= $pSkipList ? '' : '<br>' . ttfk_fNodeHtml($pDoc, $tChild, false) . '<br>';
        } elseif (preg_match('/^(p|h[1-4]|li)$/', $tName)) {
            $tHtml .= ttfk_fNodeHtml($pDoc, $tChild, $pSkipList) . '<br>';
        } else {
            $tHtml .= $pDoc->saveHTML($tChild);
        }
    }
    return ttfk_fTidyInline($tHtml);
}

// ----------------------------------------
// Collapse white space and repeated <br>, remove them at the ends, and
// encode "[" "]" so the text can not become a shortcode.
function ttfk_fTidyInline($pHtml)
{
    $pHtml = preg_replace('/\s+/', ' ', $pHtml);
    $pHtml = preg_replace('#\s*<br\s*/?>\s*#i', '<br>', $pHtml);
    $pHtml = preg_replace('#(<br>)+#', '<br>', $pHtml);
    $pHtml = trim(preg_replace('#^(<br>|\s)+|(<br>|\s)+$#', '', $pHtml));
    return str_replace(['[', ']'], ['&#91;', '&#93;'], $pHtml);
}

// ----------------------------------------
// Parse an HTML fragment (UTF-8) into a DOMDocument. The fragment is
// the body's content.
function ttfk_fLoadHtml($pHtml)
{
    $tDoc = new DOMDocument();
    $tDoc->loadHTML('<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
        . '</head><body>' . $pHtml . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
    return $tDoc;
}

// ----------------------------------------
// A parsed block array, as from parse_blocks(). pInnerList blocks go
// between pOpen and pClose.
function ttfk_fMakeBlock($pName, $pAttrs, $pOpen, $pInnerList = [], $pClose = '')
{
    return [
        'blockName'    => $pName,
        'attrs'        => $pAttrs,
        'innerBlocks'  => $pInnerList,
        'innerHTML'    => $pOpen . $pClose,
        'innerContent' => array_merge([$pOpen], array_fill(0, count($pInnerList), null), [$pClose]),
    ];
}

// ----------------------------------------
// Single pass, so a value containing "{x}" is not expanded again.
// Unknown {x} are left as is. For HTML: escape the value, and encode
// "[" "]" so submitted text can not become a shortcode. A field in
// pHtmlFieldList keeps the inline tags allowed by ttfk_fCleanHtml
// (block tags were split into blocks by ttfk_fSplitParagraph, or are
// changed to <br> here); if it has none of them, it is escaped like
// other fields.
function ttfk_fReplaceFields($pText, $pFields, $pIsHtml, $pHtmlFieldList = [])
{
    return preg_replace_callback('/\{([A-Za-z0-9_-]+)\}/', function ($pMatch) use ($pFields, $pIsHtml, $pHtmlFieldList) {
        if (!array_key_exists($pMatch[1], $pFields)) {
            return $pMatch[0];
        }
        $tValue = ttfk_fValueToString($pFields[$pMatch[1]]);
        if (!$pIsHtml) {
            return $tValue;
        }
        $tHtml = in_array($pMatch[1], $pHtmlFieldList, true) ? ttfk_fCleanHtml($tValue) : '';
        // wp_strip_all_tags() trims, so trim here too, or a value with
        // no tags but with edge white space would be taken as HTML.
        if (trim($tHtml) === wp_strip_all_tags($tHtml)) {
            return str_replace(['[', ']'], ['&#91;', '&#93;'], nl2br(esc_html($tValue), false));
        }
        $tDoc = ttfk_fLoadHtml($tHtml);
        return ttfk_fNodeHtml($tDoc, $tDoc->getElementsByTagName('body')->item(0), false);
    }, $pText);
}

// ----------------------------------------
function ttfk_fValueToString($pValue)
{
    if (is_array($pValue)) {
        return implode(', ', array_map('strval', array_filter($pValue, 'is_scalar')));
    }
    return is_scalar($pValue) ? (string) $pValue : '';
}

// ----------------------------------------
// Comma separated names or slugs. Only existing terms are returned.
function ttfk_fGetTermIdList($pList, $pTax)
{
    $tIdList = [];
    foreach (explode(',', $pList) as $tName) {
        $tName = sanitize_text_field($tName);
        if ($tName === '') {
            continue;
        }
        $tTerm = get_term_by('name', $tName, $pTax);
        if (!$tTerm) {
            $tTerm = get_term_by('slug', sanitize_title($tName), $pTax);
        }
        if ($tTerm) {
            $tIdList[] = (int) $tTerm->term_id;
        }
    }
    return array_values(array_unique($tIdList));
}

// ----------------------------------------
// The user with the site's admin_email, else the lowest ID administrator.
function ttfk_fGetAdminId()
{
    $tUser = get_user_by('email', get_option('admin_email'));
    if ($tUser && user_can($tUser, 'administrator')) {
        return (int) $tUser->ID;
    }
    $tIdList = get_users(['role' => 'administrator', 'orderby' => 'ID', 'order' => 'ASC', 'number' => 1, 'fields' => 'ID']);
    return $tIdList ? (int) $tIdList[0] : 0;
}

// ----------------------------------------
// Returns "Y-m-d" if pDate is a valid YYYY-MM-DD, else "".
function ttfk_fParseDate($pDate)
{
    $pDate = trim($pDate);
    $tDate = DateTime::createFromFormat('!Y-m-d', $pDate);
    if (!$tDate || $tDate->format('Y-m-d') !== $pDate) {
        return '';
    }
    return $pDate;
}

// ----------------------------------------
// Returns "H:i" if pTime is a valid HH:MM, else "".
function ttfk_fParseTime($pTime)
{
    $pTime = trim($pTime);
    if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $pTime, $tMatch)) {
        return '';
    }
    return sprintf('%02d:%s', $tMatch[1], $tMatch[2]);
}
