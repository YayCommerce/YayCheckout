# Login editor hints Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking.

**Status:** active
**Goal:** Trong editor, merchant biết option login của Woo đang tắt và bật ở đâu, và chọn đoạn chữ nhận click bằng cách bôi đen.
**Spec:** none, planned from the request
**Architecture:** Option `CheckoutLogin::OPTION_REMINDER` được PHP in vào `window.yayCheckoutLogin` trước editor script của block Login, vì bundle đó không chạy sau inline script của block Checkout. Đoạn click là một format Rich Text `yaycheckout/login-trigger` (span class `yaycheckout-login__trigger`) lưu trong attribute `text`. Không dùng format link của WordPress, vì format đó hỏi URL trong khi đích đến đã là behavior modal hoặc page. `CheckoutLogin::trigger_parts()` đọc span đó. Block cũ chỉ có `text` thuần và `linkText` vẫn đi qua `split_link()`.
**Touches:** `includes/Blocks/BlockRegistry.php`, `includes/Renderer/CheckoutLogin.php`, `packages/blocks/src/login/editor-data.ts` (mới), `packages/blocks/src/login/trigger-format.tsx` (mới), `packages/blocks/src/login/edit.tsx`, `packages/blocks/src/login/index.ts`, `packages/blocks/src/login/render.php`, `docs/architecture.md`
**Tests:** none. Không đụng publish rules, layout assignment, required fields hay import review. Mỗi task có bước kiểm tra tay.

## Requirements

- **Goal:** Merchant thấy block sẽ không hiện khi option tắt, có link tới chỗ bật, và gán click bằng cách bôi đen một đoạn trong câu.
- **Out of scope:** modal login, `view.ts`, endpoint wc-ajax, `default_markup()`, dịch câu mặc định, cập nhật option mà không reload editor, cho phép nhiều đoạn click, format in đậm hoặc link URL.
- **Acceptance criteria:**
  1. Option `woocommerce_enable_checkout_login_reminder` khác `yes`: trên canvas block Login có cảnh báo và link tới `admin.php?page=wc-settings&tab=account#woocommerce_enable_checkout_login_reminder`. Option là `yes`: cảnh báo đó không còn; sidebar nói khách chưa đăng nhập mới thấy block.
  2. Bôi một đoạn rồi bấm Login link trên toolbar: chỉ đoạn đó là trigger. Bôi đoạn khác và bấm lại: đoạn cũ hết đánh dấu, đoạn mới là trigger.
  3. Layout đã lưu với `text` thuần và `linkText` nằm trong câu, chưa save lại: storefront vẫn bấm đúng đoạn `linkText`.
  4. Merchant xóa trigger rồi save: storefront in câu chữ thường, không có nút hoặc link, và không tự thêm chữ `Log in` ở cuối.
  5. HTML dán vào câu (`<script>`, `<img>`, `<a>`) không còn thẻ khi in ra storefront.
  6. End-to-end: option tắt thì editor báo và storefront không có `.yaycheckout-login`. Bật option, bôi `Log in`, save, checkout ẩn danh hiện câu và chỉ `Log in` mở modal.
- **Decided with the user:** làm đúng giải pháp đã chốt: notice trên canvas khi option tắt, kèm link Accounts & Privacy; một trigger chọn bằng toolbar, không dùng core/link; giữ `linkText` cho layout cũ; xóa trigger thì không nối thêm `Log in`.

## Global constraints

- PHP sản phẩm chạy trên PHP 7.4: không enum, `match`, `readonly`, promoted constructor properties, `?->`, named arguments, union types. Không dùng `str_contains`.
- Không sửa tay `assets/dist/`. Sửa `packages/blocks/src/login/`, rồi `nvm use` và `pnpm build:blocks`.
- Không nhắc `YayCheckout\Pro`.
- Option key chỉ qua `CheckoutLogin::OPTION_REMINDER`.
- Dữ liệu in cho script dùng camelCase (`reminderEnabled`, `settingsUrl`), cùng kiểu `window.yayCheckoutBlocks`.
- Bản TypeScript của class CSS ghi comment tên hằng PHP sở hữu.
- Commit Conventional Commits, trailer `Co-Authored-By: Claude Grok 4.7 <noreply@anthropic.com>`. Stage từng file theo tên.

## Review focus

- Layout cũ chưa có span vẫn phải bấm được đúng `linkText` sau khi deploy, không cần mở editor. Task 2 Step 3.
- Xóa trigger không được làm `split_link()` nối thêm `Log in`, vì `linkText` rỗng hiện đi vào nhánh append. Task 2 Step 3.
- Câu có `&` hoặc `<` không được in thành `&amp;amp;`. `trigger_parts()` trả text đã decode, `render.php` mới `esc_html`. Task 2 Step 3.
- Option tắt không đổi storefront: `reminder_shown()` vẫn là cửa. Task 1 Step 3.

---

### Task 1: Báo option login đang tắt

**Files:**
- Create: `packages/blocks/src/login/editor-data.ts`
- Modify: `includes/Blocks/BlockRegistry.php` (sau inline script của `yaycheckout/checkout`)
- Modify: `packages/blocks/src/login/edit.tsx`

**Interfaces:**
- Produces: `window.yayCheckoutLogin = { reminderEnabled: bool, settingsUrl: string }`, in bằng `wp_add_inline_script( generate_block_asset_handle( 'yaycheckout/login', 'editorScript' ), ..., 'before' )`. `reminderEnabled` là `get_option( CheckoutLogin::OPTION_REMINDER ) === 'yes'`. `settingsUrl` là `admin_url( 'admin.php?page=wc-settings&tab=account#woocommerce_enable_checkout_login_reminder' )`.
- Produces: `loginEditorData` trong `editor-data.ts`. Thiếu global thì `{ reminderEnabled: false, settingsUrl: '' }`. Comment ghi PHP sở hữu (`BlockRegistry`, `CheckoutLogin::OPTION_REMINDER`).

- [ ] **Step 1: In `window.yayCheckoutLogin` trong `includes/Blocks/BlockRegistry.php`.** Thêm `use YayCheckout\Renderer\CheckoutLogin`. Gọi `wp_add_inline_script` sau vòng `register_block_type`, cùng chỗ với `window.yayCheckoutBlocks`.
- [ ] **Step 2: Notice trong `packages/blocks/src/login/edit.tsx`.** `reminderEnabled` false: `Notice` `status="warning"` `isDismissible={ false }` ngay trên câu, trong phần edit (canvas), chữ `Shoppers will not see this block until Enable log-in during checkout is turned on.` `settingsUrl` khác rỗng thì `ExternalLink` từ `@wordpress/components`, chữ `Accounts & Privacy`, `href` là `settingsUrl`. `reminderEnabled` true: không notice đó; đoạn sidebar hiện tại đổi thành `Logged-out shoppers see this block.`
- [ ] **Step 3: Kiểm tra.** `nvm use` rồi `pnpm build:blocks` thoát 0. `composer lint -- includes/Blocks/BlockRegistry.php` không lỗi. Trong editor layout:
  - Option Woo tắt: notice và link hiện trên block; href chứa `page=wc-settings&tab=account#woocommerce_enable_checkout_login_reminder`.
  - Bật option, reload editor: notice biến mất; sidebar còn câu logged-out.
  - Option tắt, checkout ẩn danh: không có `.yaycheckout-login`.
- [ ] **Step 4: Commit.** Stage `includes/Blocks/BlockRegistry.php`, `packages/blocks/src/login/editor-data.ts`, `packages/blocks/src/login/edit.tsx`. Message: `feat(blocks): show whether checkout login is enabled` và trailer Co-Authored-By.

### Task 2: Bôi đen để chọn trigger

**Files:**
- Create: `packages/blocks/src/login/trigger-format.tsx`
- Modify: `packages/blocks/src/login/index.ts`, `packages/blocks/src/login/edit.tsx`, `packages/blocks/src/login/render.php`, `includes/Renderer/CheckoutLogin.php`, `docs/architecture.md`

**Interfaces:**
- Consumes: class CSS đang in trong `render.php` (`yaycheckout-login__trigger`), `CheckoutLogin::split_link( string $text, string $link_text ): array`
- Produces: `CheckoutLogin::TRIGGER_CLASS = 'yaycheckout-login__trigger'`
- Produces: `CheckoutLogin::trigger_parts( string $html ): array`. Có span trigger không rỗng thì trả `[ before, trigger, after ]`, ba chuỗi plain đã decode. Không có thì `array()`.
- Produces: format `yaycheckout/login-trigger`, `tagName` `span`, `className` là `TRIGGER_CLASS`. Hằng TS cùng giá trị, comment `Mirrors CheckoutLogin::TRIGGER_CLASS in PHP.`

- [ ] **Step 1: `trigger_parts()` trong `includes/Renderer/CheckoutLogin.php`.** `wp_kses` chỉ cho `span` với attribute `class`. Lấy span đầu tiên có class token đúng `TRIGGER_CLASS`, inner không rỗng. Before là phần trước span, after là phần sau (span sau nữa chỉ còn text). Lấy text bằng DOM (`textContent`) để `&amp;` thành `&`. Không có span hợp lệ thì `array()`. `split_link()` giữ nguyên.
- [ ] **Step 2: `render.php` dùng `trigger_parts()` trước.** Có 3 phần thì in như hiện tại (`esc_html` từng phần, `<a>` hoặc `<button>` quanh phần giữa). Mảng rỗng và `linkText` là chuỗi có mặt trong `text` thuần (không tính thẻ) thì gọi `split_link()` như cũ. Còn lại: `esc_html` của text đã strip thẻ và decode, không in control, không gọi `split_link()` (nhánh đó nối `Log in` khi phrase trống). Class của `<a>` và `<button>` lấy `CheckoutLogin::TRIGGER_CLASS`.
- [ ] **Step 3: Format và editor.** `trigger-format.tsx` gọi `registerFormatType`. Nút toolbar `RichTextToolbarButton`, title `Login link`, icon `admin-links`. Selection rỗng (`start === end`): không làm gì. Selection đang active format: `removeFormat` trên selection. Selection khác: xóa mọi format này trên cả câu, rồi `applyFormat` lên selection. `index.ts` import file này trước `registerDynamicBlock`. `edit.tsx`: bỏ `TextControl` Link text và notice `linkMissing`. `RichText` `allowedFormats={ [ 'yaycheckout/login-trigger' ] }`. Value đưa vào RichText: `text` đã có span thì dùng `text`; không thì nếu `linkText` nằm trong `text` thì bọc đúng lần xuất hiện đầu (cùng quy tắc `split_link`, không nối thêm) để editor hiện trigger của layout cũ; không thì dùng `text` thuần. `onChange` ghi `text` và đặt `linkText` thành `''`. Không `useEffect` để khỏi làm layout dirty chỉ vì mở block. Khi value không có span trigger: `Notice` warning trong sidebar, chữ `Select the words shoppers should click, then choose Login link in the toolbar.` Preview lúc không chọn block cũng đọc span, hoặc `split_link` khi còn `linkText` khớp; không có trigger thì in câu thường.
- [ ] **Step 4: `docs/architecture.md`, bullet Login.** Đổi mệnh đề merchant chọn phrase qua `split_link()`: merchant bôi đen một đoạn, lưu một span `CheckoutLogin::TRIGGER_CLASS` trong `text`; `trigger_parts()` đọc span; block cũ còn `text` thuần và `linkText` vẫn qua `split_link()`. Thêm một mệnh đề: editor biết option bật hay tắt qua `window.yayCheckoutLogin`. Giữ nguyên phần modal, nonce và reload.
- [ ] **Step 5: Kiểm tra.** `nvm use` rồi `pnpm build:blocks` thoát 0. `composer lint -- includes/Renderer/CheckoutLogin.php packages/blocks/src/login/render.php` không lỗi. Trong editor, option bật, block Login:
  - Câu mặc định: `Log in` đang là trigger mà chưa cần gõ ô Link text (ô đó không còn).
  - Bôi `Returning customers`, bấm Login link: chỉ đoạn đó gạch chân; `Log in` là chữ thường.
  - Save. Checkout ẩn danh: chỉ đoạn đã bôi là `button.yaycheckout-login__trigger` (behavior modal) và mở dialog.
  - Gỡ format, save: câu không có nút, không kết thúc bằng `Log in` thừa.
  - Dán `a & b <img src=x onerror=alert(1)>` vào câu, save: storefront thấy `a & b`, không có thẻ `img`.
  - Một layout lưu từ trước (comment block có `text` thuần và `linkText` là `Log in`, không có span): storefront vẫn chỉ `Log in` là nút, không cần save lại.
- [ ] **Step 6: Commit.** Stage `includes/Renderer/CheckoutLogin.php`, `packages/blocks/src/login/trigger-format.tsx`, `packages/blocks/src/login/index.ts`, `packages/blocks/src/login/edit.tsx`, `packages/blocks/src/login/render.php`, `docs/architecture.md`, và `docs/plans/2026-10-07-login-editor.md` nếu file plan chưa được commit. Message: `feat(blocks): mark the login trigger by selecting text` và trailer Co-Authored-By.
