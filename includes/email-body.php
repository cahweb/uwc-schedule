<?php
/* This file should only be included in index.php, not displayed on its own. */

namespace CAH\UWC;

require_once 'uwc-schedule-functions.php';
?>
<table style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #000; width: 660px; margin: 2em auto;">
    <tr>
        <th colspan="2">
            <h1 style="font-size: 18px; font-weight: bold; background-color: #BFDAFF; color: #1B579F; text-align: center; padding: 0.5em 0;">Peer Consultant Schedule</h1>
        </th>
    </tr>
    <tr>
        <td colspan="2" style="padding-bottom: 2em;">
            <p>The following Peer Consultant schedule information was submitted on <?= date_format(new \DateTime('now', new \DateTimeZone('America/New_York')), 'D, d M Y, \a\t g:i a') ?>:</p>
        </td>
    </tr>
<?php foreach ($formVars as $key => $value) : ?>
    <?php
    // If there's no value, skip it
    if (is_null($value) || empty($value)) {
        continue;
    }

    // We'll add these to their preceding entries, because they're
    // meant to be combined
    if ($key == 'commuteMin' || $key == 'gradYear') {
        continue;
    }

    $emailLabel = "";
    $emailValue = "";
    switch ($key) {
        case 'commuteHr':
            $emailLabel = "Commute Time";
            if (intval($value) > 0) {
                $emailValue = "{$value} hr" . (intval($value) > 1 ? "s" : "") . ", ";
            }
            $emailValue .= "{$formVars['commuteMin']} min";
            break;

        case 'gradTerm':
            $emailLabel = "Expected Graduation";
            $emailValue = ucfirst($value) . " " . $formVars['gradYear'];
            break;

        case 'email':
            $emailLabel = 'Email';
            $emailValue = $value;
            break;

        case 'requestReceipt':
            $emailLabel = "Return Receipt Requested";
            $emailValue = intval($value) ? "Yes" : "No";
            break;

        // Formatting the schedule as a table within a table, for ease of
        // readability (hopefully)
        case 'schedule':
            $days = [
                "Monday",
                "Tuesday",
                "Wednesday",
                "Thursday",
                "Friday",
                "Saturday",
                "Sunday",
            ];

            $hours = [
                'default' => range(9, 19),
                'Saturday' => [],
                'Sunday' => range (14, 19),
            ];

            $emailLabel = 'Selected Weekly Availability';
            ob_start();
            ?>
            <table style="padding: 0.5em; width: 90%; margin: auto;">
                <tr>
                    <td></td>
                <?php foreach (range(9, 19) as $hour) : ?>
                    <th><?= ($hour <= 12 ? $hour . ($hour < 12 ? "a" : "p") : $hour - 12 . "p") ?></th>
                <?php endforeach; ?>
                </tr>
            <?php foreach ($days as $day) : ?>
                <tr>
                    <th style="text-align: left"><?= $day ?></th>
                <?php foreach (range(9, 19) as $hour) : ?>
                    <td style="padding: 0.25em; text-align: center;">
                    <?php 
                    if (!isset($hours[$day]) ||
                        (isset($hours[$day]) && in_array($hour, $hours[$day]))
                    ) :
                    ?>
                        <?= isset($value[$day][$hour]) && is_numeric($value[$day][$hour]) ? $value[$day][$hour] : "X" ?>
                    <?php endif; ?>
                    </td>
                <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </table>
            <?php
            $emailValue = ob_get_clean();
            break;

        // Default behavior is to parse the names into full labels
        default:
            preg_match('/[A-Z]/', $key, $matches, PREG_OFFSET_CAPTURE);
            $word1 = ucfirst(substr($key, 0, $matches[0][1]));
            if ($word1 == "Perm") {
                $word1 = "Permanent";
            }
            $word2 = substr($key, $matches[0][1]);
            $emailLabel = "$word1 $word2";
            if (stripos($key, 'phone') !== false && strlen(strval($value)) == 10) {
                $value = formatPhone($value);
            }
            $emailValue = $value;
            break;
    }
    ?>
    <tr>
    <?php if ($key == 'schedule') : ?>
            <th style="text-align: left;" colspan="2"><?= $emailLabel ?>:</th>
        </tr>
        <tr>
            <td colspan="2"><?= $emailValue ?></td>
    <?php elseif (!empty($emailValue)): ?>
        <th style="text-align: left;"><?= $emailLabel ?>:</th>
        <td style="padding-left: 1em;"><?= $emailValue ?></td>
    <?php endif; ?>
    </tr>
<?php endforeach; ?>
</table>
