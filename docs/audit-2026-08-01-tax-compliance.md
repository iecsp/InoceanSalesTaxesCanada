# InoceanSalesTaxesCanada 代码审查报告

**日期**：2026-08-01
**版本**：composer.json `1.0.6`（README 自称 1.0.2，已不一致）
**范围**：全插件（6 个 PHP 类 + 2 个 admin 覆写 + 11 个 Twig 模板 + config.xml）
**背景**：POS 即将上线，本插件是加拿大税务合规的唯一实现，且 POS/退款链路已硬耦合其数据契约。

---

## ✅ 修复状态（2026-08-01，commit `972e935`，main 分支，未推送）

用户确认了两项业务决策后已实施修复：

1. **非加拿大地址不在本插件作用范围**，以 Shopware 原生 Tax 配置为准 → S2-5 由"缺陷"改判为**设计意图**，仅补充 README 说明，代码行为不变。
2. **省份缺失时回退默认省 + 记 error 日志**，保证收银不中断 → S1-1 按此实现。

| 编号 | 状态 | 说明 |
|---|---|---|
| S1-1 省份判空 | ✅ 已修 | 回退默认省 + error 日志 |
| S1-2 `from()` ValueError / 死代码兜底 | ✅ 已修 | 改 `tryFrom` + 真实兜底 |
| S1-3 配置格式错误打挂结账 | ✅ 已修 | 宽松解析（逗号/多位小数/空格）+ 不再抛异常；QST 改 `type="float"` |
| S1-4 GST-only 硬编码 5% | ✅ 已修 | 读省级 GST 配置；HST 省回退到新增的 **Federal GST** 配置项 |
| S1-5 陈旧 payload | ✅ 已修 | 每次计算开头清理（`removePayloadValue`，因为 `setPayload()` 是逐键合并） |
| S1-6 映射表静默丢税 | ✅ 已修 | 映射缺失改为 error 日志；删除重复映射副本；新增双向一致性测试（经变异验证） |
| S1-7 后台税额显示为空 | ✅ 已修 | 订单详情与行项目网格均回退原生税额 |
| S2-1 gross 渠道算错税 | ✅ 已修 | 检测到 gross 则让位原生计算 + error 日志 |
| S2-2 TaxDecimals 无下限 | ✅ 已修 | 钳制在 2–4，非数字回退 2 |
| S2-4 只处理第一个 delivery | ✅ 已修 | 遍历全部 deliveries |
| S2-5 非 CA 税率 | ✅ 已定性 | 设计意图，README 已写明 |
| S2-6 模板按税率匹配 | ✅ 已修 | 直接输出 payload 聚合；以"有无 payload"替代国家判断；负数税额不再隐藏 |
| S3-2/3/4/10/11/14/15 | ✅ 已修 | 价格判空、死代码、服务注册、仓库垃圾文件、README、重复映射、MIT 授权口径统一 |
| S2-3 运费税寄生第一行 | ⚠️ 部分缓解 | 多携带者风险已被 S1-5 的清理消除；**载体本身仍是行项目 payload**，后台订单编辑删掉该行仍会丢失运费税。真正上线运费业务前应改用 cart extension / order customFields |
| S2-7 集合覆写语义 | 📄 已记录 | 未改代码；约束是"同省两档税率不得相同"，已在本文档说明 |
| S3-16 `getTaxName` Twig 死代码 | ⏸ 保留 | 虽无人调用，但它是对外公开的 Twig 过滤器，删除会破坏商家自定义模板的兼容性 |
| S3-1/5/6/7/8/9/12/13 | ⏸ 未改 | 见各条，属技术债，不影响上线 |

### 验证证据

- **单元测试 8 → 41**（`OK (41 tests, 135 assertions)`，输出无告警）。省份映射守卫与促销/运费表征测试均经**变异测试**验证非空转。
- **DI 容器**编译通过，`CanadaTaxProvider` 三个依赖正确解析并注册进 `TaxProviderRegistry`。
- **Twig 全部 11 个模板** `lint:twig` 通过。
- **真实发票渲染**（用真实订单跑 `DocumentGenerator`，验证后删除临时命令）：
  - CA 单档订单 100000273：`Net 13.00 / + GST (5%) 0.65 / Total 13.65` ✅，且旧版 `</tr><tr>` 造成的空行已消失。
  - GST+PST 双档订单 100000268：两档税各占一行 ✅。
  - 无 payload 订单 100000117：回退显示原生 `plus 12% Tax` ✅（此前是**一行税都不显示**）。
- **管理端 bundle** 已用 `bin/build-administration.sh` 重新编译并提交。

### 发现的历史数据问题（非本次改动引入）

发票渲染意外暴露：订单 **100000268** 的折扣行带有 `PST 7%`，导致汇总出现 **PST −0.11** 而全单没有任何正的 PST。该订单创建于 `2026-07-31 15:26`，早于折扣分摊修复（`191f339`，当日 19:09），属**修复前遗留的错误数据**，全库共 **3 单**。

注意：旧模板的 `taxData.total > 0` 过滤会把这一行**隐藏**，使发票各行加总（14.40 + 0.72 = 15.12）与总计（15.01）对不上。改为 `!= 0` 后发票恢复勾稽。这 3 单是否需要人工更正，请你决定。

### 部署注意

- **后端先于前端**；管理端 bundle 已随代码提交，无需再次编译。
- 新增配置项 **Federal GST** 需要执行 `bin/console plugin:update InoceanSalesTaxesCanada` 才会写入默认值 5；未执行时代码会回退到 `Constants::FEDERAL_GST_RATE`（同样是 5），功能不受影响。
- 本次未推送远端。

---

## 0. 结论摘要

代码整体**架构是对的**：走 Shopware 原生 `AbstractTaxProvider`，不改核心；促销折扣按 `composition` 逐行反算税档（`PromotionTaxApportioner`）是真正专业的做法，注释写清了"为什么"，这部分质量高于一般插件。生产库 266 条订单行验证下来，**当前主路径（POS 净价、GST-only、GST+PST、TAX-FREE 小费、促销折扣）的税额计算结果是正确的**。

但作为"合规系统"，它有三类系统性问题：

1. **容错为零**：省份缺失、配置输入格式错误都会**直接把结账链路打挂**（不是降级，是 500）。对 POS 门店 = 全店停止收银。
2. **无状态清理**：写进 line item payload 的税信息从不清除，而 POS 退款、后台订单页都**优先读它且不校验国家**，改地址/关运费税后会按陈旧税率退款和显示。
3. **无测试的正是钱路径**：6 个业务类只有 1 个有测试（8/8 通过），`CanadaTaxProvider`、`TaxConfigService`、省份↔配置字段映射表全部 0 覆盖，而这张手写映射表出错的表现是**静默少收一档税**。

**上线建议**：S1 全部修完再开新门店；S2 排期到上线后两周内。修复量不大，核心改动集中在 `CanadaTaxProvider::provide()` 开头 15 行和 `TaxConfigService`。

---

## 1. 审查与验证方式

**已实证（读源码 + 跑代码 + 查库确认，非推测）：**

| 结论 | 证据 |
|---|---|
| provider 抛出的任何异常都会中断结账 | `vendor/shopware/core/.../TaxProviderProcessor.php:120` catch `\Throwable` → `:64` `throw $exceptions` |
| gross 模式下核心用 `net = gross - taxSum` 反推 | `vendor/.../TaxProviderProcessor/TaxAdjustment.php:69-72` |
| 自定义 payload key 永不被核心清除 | `LineItem::replacePayload()` = `array_replace`（`LineItem.php:315`），`ProductCartProcessor.php:414` 用它 |
| 商品行 payload 确实带 `taxId` | `ProductCartProcessor.php:403` |
| 当前全站为**净价**模式 | `customer_group.display_gross = 0`（两个客户组均是） |
| 插件配置已完整落库，`TaxDecimals=2`、`TaxQstQC=9.975` | `system_config` 查询，23 个 key 齐全 |
| `taxName` 扩展**能**存进 `order_line_item.price` JSON | 真实订单行：`"extensions":{"taxName":{"name":"GST"}}` |
| 税额计算结果正确 | BC 商品 129.99 → GST 6.50 / PST 9.10；GST-only 13.00 → 0.65；Tip → TAX-FREE 0；促销 -1.60 → GST -0.08 |
| 单测 8/8 通过 | `docker exec shopware-app php vendor/bin/phpunit -c custom/plugins/InoceanSalesTaxesCanada/phpunit.xml.dist` |
| 运费税分支**线上从未被执行过** | `order_line_item` 中 `inoceanShippingTaxInfo` 记录数 = 0 |

**需要线上验证（本报告未实测）：**
- S1-1/S1-2 的崩溃场景需要构造一个"国家=CA 但无 country_state"的地址实际下单确认。
- S2-1 gross 模式为**潜在**风险（当前无 gross 客户组），未实测。
- 后台订单编辑（admin order recalculation）后 payload 是否被正确重写，未实测。

---

## 1.5 设计说明：payload 冗余是**必要的**，不要"优化"掉

审查中确认了一件容易被后人误删的事：

`taxName` 扩展**确实**能存进 `order_line_item.price` 的 JSON（真实订单行验证：`"calculatedTaxes":[{"tax":6.5,"taxRate":5,"extensions":{"taxName":{"name":"GST"}}}]`）。所以乍看之下，`payload.inoceanCanadaTaxInfo` 是对同一份数据的重复存储，像是可以删掉的冗余。

**不能删。** 理由：

1. **store-api 会把订单行的 `price` 字段整个剥离**（返回 `null`），POS 前端拿不到 `calculatedTaxes`。这一点已在 `frontends/templates/sw-frontend-pos/app/utils/orderTax.ts` 的文档注释中记录，并且是历史上"退款漏退税"Bug 的根因。
2. `priceDefinition.taxRules` 虽然能穿过 store-api，但它只有商品挂的**单一合并税率**（如 12%），**无法表达 GST 5 + PST 7 的拆分**，不满足加拿大发票必须分列各税种的合规要求。

因此 `inoceanCanadaTaxInfo` 是 POS/store-api 侧**唯一**能拿到税种拆分的通道。若将来重构，删除它之前必须先解决 store-api 的 `price` 剥离问题。

（注：这条只针对 `inoceanCanadaTaxInfo` 的**存在**。它**从不被清理**是独立的缺陷，见 S1-5；`inoceanShippingTaxInfo` 寄生在第一个行项目上则是另一个设计问题，见 S2-3。）

---

## 2. 发现清单（按严重度）

### S1 — 高危：上线前必须修

---

#### S1-1 `country_state` 未判空 → 结账被阻断

**位置**：`src/Core/Checkout/Cart/Tax/CanadaTaxProvider.php:46`

```php
$proviceShortCode = substr(strtoupper($address->getCountryState()->getShortCode()), -2);
```

`getCountryState()` 返回 `?CountryStateEntity`。国家已确认是 CA，但**省份可以为空**。

**失败场景**：任何"国家=Canada、未选省"的地址进入结账 —— POS 的 walk-in 默认客户地址、通过 Admin API / 导入创建的地址、游客只填了国家、或 `country_state` 关联未被 criteria 加载。此时 `Error: Call to a member function getShortCode() on null` → 被 `TaxProviderProcessor` 捕获 → 以 `TaxProviderExceptions` 重新抛出 → **购物车计算失败，收银台无法结账**。对 POS 门店就是停业。

**修复**：

```php
$state = $address->getCountryState();
$code  = $state ? substr(strtoupper($state->getShortCode()), -2) : Constants::DEFAULT_PROVINCE;
```

并明确策略：是回退到默认省，还是返回空结果 + 记 warning 日志（推荐后者，"猜一个省收税"比"不收税并报警"更危险）。

---

#### S1-2 `CanadianProvince::from()` 抛 ValueError；默认省兜底是死代码

**位置**：`CanadaTaxProvider.php:47`

```php
$province = CanadianProvince::from($proviceShortCode ?? Constants::DEFAULT_PROVINCE);
```

两个问题：
1. `substr()` 永远不返回 `null`，所以 `?? Constants::DEFAULT_PROVINCE` **从未生效**，作者意图的兜底根本不存在。
2. `from()` 遇到未知值抛 `\ValueError`，后果同 S1-1：结账中断。

**失败场景**：商家在后台自建/导入了一个非标准的加拿大 `country_state`（短码不是 `CA-XX` 形式，或多了一个自定义"省"），该省客户全部无法结账。

**修复**：`CanadianProvince::tryFrom($code) ?? <明确的降级路径>`。

---

#### S1-3 配置输入格式错误 → 该省结账瘫痪（QST 字段是 `type="text"`）

**位置**：`src/Service/TaxConfigService.php:69-77`，`src/Resources/config/config.xml:161`

```php
if (!preg_match('/^\d+(\.\d{1,3})?$/', $stringValue)) {
    throw new \InvalidArgumentException("Invalid tax rate format: {$stringValue} ...");
}
```

这个异常在**结账运行时**抛出，而不是在保存配置时。正则还很严格：拒绝 `9,975`（逗号）、`9.9750`（4 位小数）、`.5`、`5.`、负数、科学计数法。

**放大器**：`config.xml` 里 QST 唯一一个声明成 `<input-field type="text">`（其余都是 `float`），后台是自由文本框，**没有任何数字校验**。float 字段同样允许输入负数。

**失败场景**：管理员调税率时手滑输入 `9,975` 或 `9.9755` → 该省所有线上订单和 POS 收银**立即全部 500**，后台没有任何提示告诉他是这次改动导致的。当前库里 `TaxQstQC=9.975` 是正常的，但这是运气，不是防护。

**修复**（两层）：
- 运行时不要抛：读取失败 → 记 `error` 日志 + 回退到 `config.xml` 的 `defaultValue`（或 0 并明确报警），绝不把管理员的输入错误变成停业事故。
- 输入层：QST 改 `type="float"`，并在 `config.xml` 上加 `min`/`max`/`step`。

---

#### S1-4 GST-only 商品税率硬编码 5%，绕过后台配置

**位置**：`CanadaTaxProvider.php:64-66`（商品）、`:134-136`（运费）、`:227-235`

```php
} elseif ($lineItem->getPayloadValue('taxId') === Constants::TAXES[2]['id']) {
    $taxRates = ['GST' => $this->getDefaultRateByTaxType('GST-ONLY')];  // ← 恒为 Constants 里的 5
}
```

后台每个省都有可配置的 `TaxGst<省>`，但挂了"(CA) GST only"税种的商品**永远走常量 5%**，配置改了也没用。

**失败场景**：联邦 GST 调整（或商家需要按销售渠道配置不同税率）→ 管理员把 `TaxGstBC` 改成 6，普通商品按 6% 收，但所有 GST-only 品类（餐饮外卖、基本食品）仍按 5% 收。**长期少收税 + 申报口径分裂**，而且没有任何报错。改 `Constants::TAXES` 又会连带影响 `tax` 表记录和历史订单。

**第二重后果（发票对不上账）**：购物车税额汇总的 `rate` 取的是**第一条遇到的行**的税率（`CanadaTaxProvider.php:107`、`:212`）。一旦 `TaxGstBC` 被改成 6 而 GST-only 仍硬编码 5，购物车和发票上只会出现**一行** "GST (5%)"，而它的金额是 5% 和 6% 两部分税额之和 —— 这张发票的**申报税率与税额自身无法勾稽**。这比少收税更难解释。

**修复**：GST-only 分支改读 `$this->taxConfigService->getTaxRate(TaxType::GST, $province, $salesChannelId)`。TAX-FREE 恒为 0，可保留硬编码。

---

#### S1-5 陈旧 payload 从不清除 → POS 按错误税率退款

**位置**：`CanadaTaxProvider.php:42-44`（提前 return 不清理）、`:122`（`if ($freightTaxable)` 为假时不清理）
**下游（实际出钱的那一端）**：`custom/plugins/InoceanErpPos/src/StoreApi/Order/ReturnOrderRoute.php:540` —— **退款金额计算直接读 `inoceanCanadaTaxInfo`**
**下游（显示端）**：`frontends/templates/sw-frontend-pos/app/utils/orderTax.ts:35-39`

已实证核心的 `replacePayload()` 是 `array_replace`，**自定义 key 只会被覆盖、永不被删除**。而 POS 的 `lineItemTaxRate()` 把 `inoceanCanadaTaxInfo` 放在**第一优先级且不做国家判断**：

```ts
const canada = item.payload?.inoceanCanadaTaxInfo;
if (canada && canada.length > 0) {
  return canada.reduce((sum, t) => sum + (t.rate ?? 0), 0) / 100;
}
```

**失败场景 A**：顾客先选 BC 地址（写入 `[{GST,5},{PST,7}]`），改成美国地址后下单 → provider 在 `:43` 提前 return，payload 原封不动留在订单上 → POS 退款按 12% 退税、后台订单页显示 GST/PST 税行，**而这笔订单实际根本没收这两档税**。退多了钱，账也对不上。

**失败场景 B**：`FreightTaxable` 从开改成关，或运费降为 0 → `inoceanShippingTaxInfo` 残留在行项目上 → 发票和后台仍显示运费税。

**修复**：`provide()` 的最开头（在任何 return 之前）遍历全部行项目，`unset` 掉 `inoceanCanadaTaxInfo` / `inoceanShippingTaxInfo`，再按本次计算重写。

---

#### S1-6 省份↔配置字段映射表出错 = 静默少收一档税

**位置**：`src/Config/TaxType.php:24-47`（21 条手写 `[TaxType, Province]` 映射）、`src/Config/CanadianProvince.php:40-57`（**同一份映射的第二个副本**）

链路：`getConfigFieldName()` 未命中 → 返回 `null` → `TaxConfigService.php:22-24` 返回 `0.0` → `TaxConfigService.php:39` 的 `if ($rate > 0)` **把这档税整个从结果里丢掉**。全程无异常、无日志。

**失败场景**：将来新增省份、重命名配置字段、或写错一个字母（`TaxPstNU` 打成 `TaxPstNV`）→ 该省从此只收 GST 不收 PST，**账面上一切"正常"**，直到税务局来查。

**加重因素**：`CanadianProvince::getTaxFieldNames()` 是同一份映射的第二处真相，两边可以静默漂移（目前 `getTaxFieldNames()` 实际无人调用，属死代码，但留着就是坑）。

**修复**：改成单一数据源 —— 由 `province` + `taxType` 拼出 `Tax{Gst|Pst|Hst|Qst}{Code}`，或至少删掉重复副本；并加下面第 3 节的一致性测试。

---

#### S1-7 后台订单详情覆写无国家判断 → 非加拿大/历史订单税额显示为空

**位置**：`src/Resources/app/administration/src/module/sw-order/view/sw-order-detail-general/index.js:sortedCalculatedTaxes`、`sw-order-line-items-grid/index.js:lineItemTaxesMap`、`main.js`（全局注册）

两个 admin 覆写**完全用 payload 重建税额**，payload 为空时返回 `[]`，**没有回退到原生 `order.price.calculatedTaxes`**，也没有任何"这单是不是加拿大"的判断。

**失败场景**：插件安装之前的历史订单、非 CA 地址的订单、后台手工创建的订单 → 订单详情"总计"区**一条税行都不显示**（但总额是含税的），行项目网格的税列也是空的。财务对账和客服会直接看不到税额。同理 `sw-order-line-items-grid.html.twig` 的 `sw_order_line_items_grid_grid_columns_tax_content` 覆写了原生内容，`..._tax_inline_edit` 和 `..._tax_content_tooltip` 被清成空块 —— 原生的税率内联编辑能力被无条件移除了。

**修复**：`sortedCalculatedTaxes` 为空时回退到原生数据；Twig 覆写块在无 payload 时 `{{ parent() }}`。

---

### S2 — 中危：上线后尽快排期

---

#### S2-1 含税价（gross）渠道下多收税 —— 当前潜伏，未触发

**位置**：`CanadaTaxProvider.php:206`（`round($price * $taxRate / 100, ...)`），核心 `TaxAdjustment.php:69-72`

插件一律把 `getTotalPrice()` 当作**净额**并在其上加税。gross 模式下核心会用 `net = gross - taxSum` 反推净额。

**数字**：BC 12%、含税价 $112 → 插件算出税 13.44、净额 98.56；正确应为净额 100.00、税 12.00。**多收 1.44**。

**当前状态**：已查库，两个客户组 `display_gross = 0`，全站净价，**该 Bug 未被触发**。风险在于任何人新建一个含税客户组或 B2C 渠道就会静默多收，且没有任何报警。

**修复**：`provide()` 开头判断 `$context->getTaxState()`；`TAX_STATE_GROSS` 时按 `tax = price - price/(1 + rate/100)` 反算，或直接返回空结果 + 记 error 日志（明确声明本插件只支持净价）。

---

#### S2-2 `TaxDecimals` 无下限，可被清成 0 → 税额四舍五入到整元

**位置**：`TaxConfigService.php:87-90`

```php
return (int) $this->systemConfigService->get('InoceanSalesTaxesCanada.config.TaxDecimals', $salesChannelId);
```

配置被清空 → `get()` 返回 `null` → `(int) null = 0` → 所有税额 `round($x, 0)`。`CanadaTaxProvider.php:39` 的 `?? 2` 是死代码（返回类型已是 `int`，永不为 null），作者意图的兜底不存在。

**当前状态**：已查库，值为 2，未触发。

**修复**：`$v = ...get(...); return is_numeric($v) ? max(2, min(4, (int) $v)) : 2;`

---

#### S2-3 运费税 payload 寄生在"第一个行项目"上（且该分支线上从未跑过）

**位置**：`CanadaTaxProvider.php:163-167`

```php
if (!empty($aggregatedShippingTaxesPayload) && $cart->getLineItems()->first()) {
    $payload = $cart->getLineItems()->first()->getPayload();
    $payload['inoceanShippingTaxInfo'] = ...;
}
```

而所有消费端（`summary-tax.html.twig`、`summary-total.html.twig`、`order-detail.html.twig`、`documents/includes/summary.html.twig`、`shipping_costs.html.twig`、admin `sortedShippingTaxes`）都是**遍历全部行项目累加**。

"有且仅有一行携带这个 key"是一个**隐含且无任何保障的不变量**：
- `getLineItems()->first()` 可能是促销行（负数行）；
- 后台订单编辑删掉该行 → 发票上运费税凭空消失；
- 结合 S1-5（key 永不清除），任何让"第一行"发生变化的路径都可能留下多个携带者，届时运费税会被重复累加。

**当前状态**：已查库，`order_line_item` 中带 `inoceanShippingTaxInfo` 的记录数 = **0**。POS 无运费，所以**整个运费税分支在生产中从未被执行过** —— 这是最脆弱且最未经验证的一段代码。

**修复**：改用 `$cart->addExtension()` 或订单 `customFields` 承载运费税，与行项目解耦。

---

#### S2-4 只处理第一个 delivery

**位置**：`CanadaTaxProvider.php:124` `$cart->getDeliveries()->first()`

多包裹 / 拆单发货时，其余 delivery 的运费**完全不计税**。当前 POS 场景无影响，线上零售扩展时会踩。

---

#### S2-5 非加拿大地址会退回原生 12%/13% 的"加拿大税"

**位置**：`CanadaTaxProvider.php:42-44` 提前 return 空结果 → 核心 `declaresTaxes()` 为 false → 按商品挂的 `tax` 记录原生计算。

而插件安装时创建的 tax 记录就是 12%（GST+PST/QST）、13%（HST）、5%、0%。**结果：一个美国客户买 BC 的商品，会被收 12% 税，发票上显示为通用的 "plus 12% Tax"。**

**修复**：明确策略并写进 README —— 要么建 tax rule 使非 CA 为 0%，要么 provider 对非 CA 显式返回 0 税。这是"跨境是否征税"的业务决策，需要你确认。

---

#### S2-6 发票/订单模板按"税率相等"匹配，匹配不上就整行消失

**位置**：`src/Resources/views/documents/includes/summary.html.twig:680`、`storefront/page/account/order-history/order-detail.html.twig:196`

```twig
{% if taxData.total > 0 && calculatedTax.taxRate == taxData.rate %}
```

三个问题：
1. **靠税率做主键**：若两档税率相同（管理员把某省 PST 设成 5），两条 payload 都会匹配同一个 `calculatedTax` → 重复输出。
2. **匹配不上就丢**：payload 里有、但 `order.price.calculatedTaxes` 里没有的税档，**在发票上直接不显示**。发票是合规文书，静默丢行是硬伤。
3. **`total > 0` 过滤**：整单折扣大于商品金额时税额为负，该税行被整行隐藏（`summary-tax.html.twig:35` 同样问题）。

**修复**：直接输出 payload 聚合结果，不做 rate 匹配；改用税名作 key；去掉 `> 0` 过滤改成 `!= 0`。

---

#### S2-7 `CanadaCalculatedTaxCollection` 覆写破坏核心集合语义

**位置**：`src/Core/Checkout/Cart/Tax/Struct/CanadaCalculatedTaxCollection.php:20-28`

```php
public function add($element): void { $this->elements[] = $element; }   // 核心是按税率做 key
```

覆写是**必要的**（一个购物车要同时存在 GST 和 PST 两条税），但代价是：订单持久化后从 JSON 反序列化出来的是**核心 `CalculatedTaxCollection`**（按税率 key、`merge()` 按税率合并），不是这个子类。因此**两档税率相同的税在订单读回/重算/退款时会被合并成一条**，金额被吞。

各省当前 GST/PST 税率不重合，属潜在雷。后台没有任何东西阻止管理员把两档设成相同数字。

**修复**：至少在文档中写明"同省两档税率不得相同"这一约束，理想是在配置保存时校验。

---

### S3 — 低危 / 代码质量

| 编号 | 位置 | 问题 |
|---|---|---|
| S3-1 | `CanadaTaxProvider.php:42` | `strtoupper($address->getCountry()?->getIso())` —— country 为 null 时 `strtoupper(null)` 触发 PHP 8.1+ deprecation |
| S3-2 | `CanadaTaxProvider.php:70,84` | `getPrice()` 未判空，未定价行项目会 fatal（核心 `TaxAdjustment` 本有 `missingLineItemPrice` 的规范异常，但插件先崩） |
| S3-3 | `CanadaTaxProvider.php:38,39` | `?? 1` / `?? 2` 死代码，返回类型已是 `bool`/`int`。见 S2-2，症状是"没有下限保护"而非本身有害 |
| S3-4 | `services.xml` | `PromotionTaxApportioner` 未注册为服务，靠构造函数 `new` 兜底 —— 不可替换、不可注入 mock |
| S3-5 | `CanadaTaxProvider.php:63,133` | TAX-FREE 行会产生一条 `TAX-FREE 0%` 的 `CalculatedTax` 进入购物车汇总集合（模板靠 `taxRate != 0` 过滤）。"用一条 0 税表达无税"可接受，但污染汇总 |
| S3-6 | `InoceanSalesTaxesCanada.php:117-127` | install 逐条 `create()`，无 `update()` / 无 Migration —— 后续新增税档、改税率、修 Bug **无法下发到已安装站点** |
| S3-7 | `InoceanSalesTaxesCanada.php:88` | 规则 ID 固定，商家可在后台删除该 rule；删除后 `availabilityRuleId` 悬空，核心把 `NULL` 当"全渠道可用"，provider 会对所有渠道运行（幸好有国家判断兜底） |
| S3-8 | 全插件 | **零日志**。"非 CA 提前返回""省份识别失败""某档税率为 0 被丢弃"全部静默。合规系统至少应有 warning 级审计日志 |
| S3-9 | 4 个 snippet 文件 + 全部模板 + admin | snippet key 拼写错误 `salseTaxCanada`（sales → salse），已固化，改动成本随时间递增 |
| S3-10 | 仓库 | `.DS_Store`（5 个）、`.phpunit.cache/test-results` 已被跟踪；`.gitignore` 虽列了 `.DS_Store` 但文件已入库 |
| S3-11 | `README.md` | 与实现不符：版本号 1.0.2 vs composer 1.0.6；"6.6+" vs composer 已 `~6.7.0`；安装步骤第 3 条是空的；**未说明必须为净价（net）渠道**、未说明必须给商品挂对应 tax 记录 |
| S3-15 | `composer.json:9` vs `README.md:109` | **授权条款自相矛盾**：composer 声明 `"license": "MIT"`（允许任意再分发/修改），README 写 "This is proprietary software"。这个插件是要对外提供的，两份声明必须统一 —— 建议按实际商业意图改 composer 的 license 字段（如 `proprietary`），并同步 `LICENSE.md` |
| S3-16 | `src/Twig/TaxNameExtension.php`、`TaxConfigService.php:47-58` | **整个 Twig 过滤器 `getTaxName` 是死代码** —— 已 grep 全部 `custom/` 与 `public/`，无任何模板调用。连带 `getTaxNameByProvince()` 也无人使用。建议删除（同 S3-14 的 `getTaxFieldNames()`） |
| S3-12 | `documents/includes/summary.html.twig:682` | 输出 `</tr><tr>` 破坏表格结构（外层已有 `<tr>`），无匹配时产生空 `<tr></tr>` |
| S3-13 | 模板 vs provider | 模板取国家用 `context.customer.activeShippingAddress.country.iso`，provider 用 `context.getShippingLocation()` —— 游客/未登录/渠道默认国家时二者可不一致，出现"按 CA 算税、按非 CA 显示"的组合 |
| S3-14 | `CanadianProvince.php:40-57` | `getTaxFieldNames()` 无人调用（死代码），且与 `TaxType::getConfigFieldName()` 是同一映射的两份真相。见 S1-6 |

---

## 3. 测试现状与建议

### 现状

```
docker exec shopware-app php vendor/bin/phpunit -c custom/plugins/InoceanSalesTaxesCanada/phpunit.xml.dist
→ OK (8 tests, 13 assertions)
```

**8/8 通过，但覆盖面严重失衡**：

| 类 | 测试 |
|---|---|
| `PromotionTaxApportioner` | ✅ 8 个用例，质量高（覆盖了 GST-only 折扣、混合税档、tax-free 稀释、composition 缺失回退） |
| `CanadaTaxProvider` | ❌ 0 —— **核心金额计算路径** |
| `TaxConfigService` | ❌ 0 —— 含会打挂结账的异常分支 |
| `TaxType` / `CanadianProvince` | ❌ 0 —— 21 条手写映射表 |
| `TaxNameExtension` | ❌ 0 |
| 插件生命周期（install/uninstall） | ❌ 0 |
| 模板 / admin 组件 | ❌ 0 |

**唯一被测的类不是钱路径。** 8/8 绿灯不代表这个插件被验证过。

### 建议新增测试（按价值排序）

1. **【最高价值】省份 × 配置字段一致性测试**：遍历 13 个省的每个 `getTaxTypes()`，断言 ① `getConfigFieldName()` 非 null，② 返回的字段名**确实存在于 `config.xml` 的 `<input-field><name>` 中**。这一个测试直接堵死 S1-6 整类"静默少收税"的 Bug，且是纯单测、无需容器。
2. **`CanadaTaxProvider` 无省份地址**：`country_state` 为 null 时不得抛异常（S1-1）。
3. **`CanadaTaxProvider` 未知省份短码**：`CA-XX` 不得抛 `ValueError`（S1-2）。
4. **gross vs net 税态**：同一购物车在 `TAX_STATE_GROSS` / `TAX_STATE_NET` 下的税额断言（S2-1）。
5. **`validateAndConvertTaxRate` 边界输入**：`"9,975"` / `"9.9750"` / `"-1"` / `""` / `null` / `"abc"` —— 断言**不抛异常**（改成降级后）且回退值正确（S1-3）。
6. **陈旧 payload 清理**：先以 CA 地址计算写入 payload，再以非 CA 地址计算，断言 `inoceanCanadaTaxInfo` 已被清除（S1-5）。
7. **GST-only 走配置税率**：把 `TaxGstBC` 设为 6，断言 GST-only 商品按 6% 而非 5%（S1-4）。
8. **运费税**：多 delivery、`FreightTaxable` 关闭后 payload 被清理（S2-3 / S2-4）。

前 5 项都是**纯单测**（`TestCase` + mock `SystemConfigService`），不需要 Shopware 集成测试环境，投入很小。

---

## 4. 建议修复顺序

**第一批（上线前，约半天）**
S1-1、S1-2（判空 + `tryFrom`，10 行）→ S1-3（配置读取降级不抛异常）→ S1-5（provide 开头清 payload）→ S1-4（GST-only 读配置）
配套补测试 #1 #2 #3 #5 #6 #7。

**第二批（上线后两周内）**
S1-6（映射单一数据源）→ S1-7（admin 回退原生）→ S2-1（gross 守卫）→ S2-2（decimals 下限）→ S2-6（发票模板不按税率匹配）

**第三批（技术债）**
S2-3 / S2-4（运费税重构，建议在真正上线运费业务前做）→ S2-5（跨境策略，**需要你先做业务决策**）→ S3-6（加 Migration 机制）→ S3-8（审计日志）→ README 修订。

**需要你决策的两点：**
1. **跨境订单（非加拿大地址）应该收多少税？** 现在是被动退回原生 12%/13%，几乎肯定不是你想要的（S2-5）。
2. **省份缺失时是回退到 BC 默认省，还是拒绝计算并报警？** 前者能保证收银不中断但可能收错省的税，后者更安全但会挡住结账（S1-1）。我的建议是回退 + 记 error 日志 + 后台可见告警。
