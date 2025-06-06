<?php
require_once '../../includes/connect_endpoint.php';

require_once '../../includes/currency_formatter.php';
require_once '../../includes/getdbkeys.php';

include_once '../../includes/list_subscriptions.php';

require_once '../../includes/getsettings.php';

$theme = "light";
if (isset($settings['theme'])) {
  $theme = $settings['theme'];
}

$colorTheme = "blue";
if (isset($settings['color_theme'])) {
  $colorTheme = $settings['color_theme'];
}

$formatter = new IntlDateFormatter(
  'en', // Force English locale
  IntlDateFormatter::SHORT,
  IntlDateFormatter::NONE,
  null,
  null,
  'MMM d, yyyy'
);

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {


  $sort = "next_payment";
  $sortOrder = $sort;
  $order = "ASC";

  $params = array();
  $sql = "SELECT * FROM subscriptions WHERE user_id = :userId";

  if (isset($_GET['categories']) && $_GET['categories'] != "") {
    $allCategories = explode(',', $_GET['categories']);
    $placeholders = array_map(function ($idx) {
      return ":categories{$idx}";
    }, array_keys($allCategories));

    $sql .= " AND (" . implode(' OR ', array_map(function ($placeholder) {
      return "category_id = {$placeholder}";
    }, $placeholders)) . ")";

    foreach ($allCategories as $idx => $category) {
      $params[":categories{$idx}"] = $category;
    }
  }

  if (isset($_GET['payments']) && $_GET['payments'] !== "") {
    $allPayments = explode(',', $_GET['payments']);
    $placeholders = array_map(function ($idx) {
      return ":payments{$idx}";
    }, array_keys($allPayments));

    $sql .= " AND (" . implode(' OR ', array_map(function ($placeholder) {
      return "payment_method_id = {$placeholder}";
    }, $placeholders)) . ")";

    foreach ($allPayments as $idx => $payment) {
      $params[":payments{$idx}"] = $payment;
    }
  }

  if (isset($_GET['members']) && $_GET['members'] != "") {
    $allMembers = explode(',', $_GET['members']);
    $placeholders = array_map(function ($idx) {
      return ":members{$idx}";
    }, array_keys($allMembers));

    $sql .= " AND (" . implode(' OR ', array_map(function ($placeholder) {
      return "payer_user_id = {$placeholder}";
    }, $placeholders)) . ")";

    foreach ($allMembers as $idx => $member) {
      $params[":members{$idx}"] = $member;
    }
  }

  if (isset($_GET['state']) && $_GET['state'] != "") {
    $sql .= " AND inactive = :inactive";
    $params[':inactive'] = $_GET['state'];
  }

  if (isset($_GET['renewalType']) && $_GET['renewalType'] != "") {
    $sql .= " AND auto_renew = :auto_renew";
    $params[':auto_renew'] = $_GET['renewalType'];
  }

  if (isset($_GET['tags']) && $_GET['tags'] != "") {
    $allTags = explode(',', $_GET['tags']);
    $placeholders = array_map(function ($idx) {
      return ":tags{$idx}";
    }, array_keys($allTags));

    $sql .= " AND id IN (SELECT subscription_id FROM subscription_tags WHERE tag_id IN (" . implode(',', $placeholders) . "))";

    foreach ($allTags as $idx => $tag) {
      $params[":tags{$idx}"] = $tag;
    }
  }

  if (isset($_COOKIE['sortOrder']) && $_COOKIE['sortOrder'] != "") {
    $sort = $_COOKIE['sortOrder'];
  }

  $sortOrder = $sort;
  $allowedSortCriteria = ['name', 'id', 'next_payment', 'price', 'payer_user_id', 'category_id', 'payment_method_id', 'inactive', 'alphanumeric', 'renewal_type'];
  $order = ($sort == "price" || $sort == "id") ? "DESC" : "ASC";

  if ($sort == "alphanumeric") {
    $sort = "name";
  }

  if (!in_array($sort, $allowedSortCriteria)) {
    $sort = "next_payment";
  }

  if ($sort == "renewal_type") {
    $sort = "auto_renew";
  }

  $orderByClauses = [];

  if ($settings['disabledToBottom'] === 'true') {
    if (in_array($sort, ["payer_user_id", "category_id", "payment_method_id"])) {
      $orderByClauses[] = "$sort $order";
      $orderByClauses[] = "inactive ASC";
    } else {
      $orderByClauses[] = "inactive ASC";
      $orderByClauses[] = "$sort $order";
    }
  } else {
    $orderByClauses[] = "$sort $order";
    if ($sort != "inactive") {
      $orderByClauses[] = "inactive ASC";
    }
  }

  if ($sort != "next_payment") {
    $orderByClauses[] = "next_payment ASC";
  }

  $sql .= " ORDER BY " . implode(", ", $orderByClauses);

  $stmt = $db->prepare($sql);
  $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

  foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
  }

  $result = $stmt->execute();
  if ($result) {
    $subscriptions = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
      $subscriptions[] = $row;
    }
  }

  foreach ($subscriptions as $subscription) {
    if ($subscription['inactive'] == 1 && isset($settings['hideDisabledSubscriptions']) && $settings['hideDisabledSubscriptions'] === 'true') {
      continue;
    }
    $id = $subscription['id'];
    $print[$id]['id'] = $id;
    $print[$id]['logo'] = $subscription['logo'] != "" ? "images/uploads/logos/" . $subscription['logo'] : "";
    $print[$id]['name'] = $subscription['name'] ?? "";
    $cycle = $subscription['cycle'];
    $frequency = $subscription['frequency'];
    $print[$id]['billing_cycle'] = getBillingCycle($cycle, $frequency, $i18n);
    $paymentMethodId = $subscription['payment_method_id'];
    $print[$id]['currency_code'] = $currencies[$subscription['currency_id']]['code'];
    $currencyId = $subscription['currency_id'];
    $next_payment_timestamp = strtotime($subscription['next_payment']);
    $formatted_date = $formatter->format($next_payment_timestamp);
    $print[$id]['next_payment'] = $formatted_date;
    $print[$id]['auto_renew'] = $subscription['auto_renew'];
    $paymentIconFolder = (strpos($payment_methods[$paymentMethodId]['icon'], 'images/uploads/icons/') !== false) ? "" : "images/uploads/logos/";
    $print[$id]['payment_method_icon'] = $paymentIconFolder . $payment_methods[$paymentMethodId]['icon'];
    $print[$id]['payment_method_name'] = $payment_methods[$paymentMethodId]['name'];
    $print[$id]['payment_method_id'] = $paymentMethodId;
    $print[$id]['category_id'] = $subscription['category_id'];
    $print[$id]['payer_user_id'] = $subscription['payer_user_id'];
    $print[$id]['price'] = floatval($subscription['price']);
    $print[$id]['progress'] = getSubscriptionProgress($cycle, $frequency, $subscription['next_payment']);
    $print[$id]['inactive'] = $subscription['inactive'];
    $print[$id]['url'] = $subscription['url'] ?? "";
    $print[$id]['notes'] = $subscription['notes'] ?? "";
    $print[$id]['replacement_subscription_id'] = $subscription['replacement_subscription_id'];

    if (isset($settings['convertCurrency']) && $settings['convertCurrency'] === 'true' && $currencyId != $mainCurrencyId) {
      $print[$id]['price'] = getPriceConverted($print[$id]['price'], $currencyId, $db);
      $print[$id]['currency_code'] = $currencies[$mainCurrencyId]['code'];
    }
    if (isset($settings['showMonthlyPrice']) && $settings['showMonthlyPrice'] === 'true') {
      $print[$id]['price'] = getPricePerMonth($cycle, $frequency, $print[$id]['price']);
    }
    if (isset($settings['showOriginalPrice']) && $settings['showOriginalPrice'] === 'true') {
      $print[$id]['original_price'] = floatval($subscription['price']);
      $print[$id]['original_currency_code'] = $currencies[$subscription['currency_id']]['code'];
    }

    // Load tags for this subscription (only if tables exist)
    $tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tags'");
    $tagsTableExists = $tableQuery->fetchArray(SQLITE3_ASSOC) !== false;
    $print[$id]['tags'] = [];
    
    if ($tagsTableExists) {
      $tagQuery = "SELECT t.* FROM tags t 
                   JOIN subscription_tags st ON t.id = st.tag_id 
                   WHERE st.subscription_id = :subscriptionId 
                   ORDER BY t.name ASC";
      $tagStmt = $db->prepare($tagQuery);
      $tagStmt->bindValue(':subscriptionId', $id, SQLITE3_INTEGER);
      $tagResult = $tagStmt->execute();
      while ($tagRow = $tagResult->fetchArray(SQLITE3_ASSOC)) {
        $print[$id]['tags'][] = $tagRow;
      }
    }
  }

  if ($sortOrder == "alphanumeric") {
    usort($print, function ($a, $b) {
      return strnatcmp(strtolower($a['name']), strtolower($b['name']));
    });
    if ($settings['disabledToBottom'] === 'true') {
      usort($print, function ($a, $b) {
        return $a['inactive'] - $b['inactive'];
      });
    }
  }

  if ($sortOrder == "category_id") {
    usort($print, function ($a, $b) use ($categories) {
      return $categories[$a['category_id']]['order'] - $categories[$b['category_id']]['order'];
    });
  }
  
  if ($sortOrder == "payment_method_id") {
    usort($print, function ($a, $b) use ($payment_methods) {
      return $payment_methods[$a['payment_method_id']]['order'] - $payment_methods[$b['payment_method_id']]['order'];
    });
  }

  if (isset($print)) {
    printSubscriptions($print, $sort, $categories, $members, $i18n, $colorTheme, "../../", $settings['disabledToBottom'], $settings['mobileNavigation'], $settings['showSubscriptionProgress'], $currencies, $lang);
  }

  if (count($subscriptions) == 0) {
    ?>
    <div class="no-matching-subscriptions">
      <p>
        <?= translate('no_matching_subscriptions', $i18n) ?>
      </p>
      <button class="button" onClick="clearFilters()">
        <span clasS="fa-solid fa-minus-circle"></span>
        <?= translate('clear_filters', $i18n) ?>
      </button>
      <img src="images/siteimages/empty.png" alt="<?= translate('empty_page', $i18n) ?>" />
    </div>
    <?php
  }
}

$db->close();
?>