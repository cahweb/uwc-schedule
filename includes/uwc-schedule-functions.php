<?php
namespace CAH\UWC;

// Determines whether we send confirmation emails to faculty
define('IS_DEV', $_ENV['ENV'] != 'production');

require_once "includes/class.formInput.php";

/**
 * Compile an array of form values by name attribute
 *
 * Uses the existing form field information to compile a flattened array
 * of form values keyed to the fields' name attribute.
 *
 * @param array $formValues The array of form field information
 * @param array $container Reference to a container array for the new values
 *
 * @return void
 */
function parseFormVars(array $formValues, array &$container) : void
{
    if (empty($formValues)) {
        return;
    }

    foreach ($formValues as $item) {
        if (isset($item['name'])) {
            $container[$item['name']] = null;
        } else {
            switch ($item['type']) {
                // Could probably just use `default` for this, but
                // I like being explicit
                case 'multi':
                case 'fieldset':
                    parseFormVars($item['fields'], $container);
                    break;

                case 'function':
                default:
                    break; // Probably not necessary
            }
        }
    }
}

/**
 * Compile the names of all required form fields
 *
 * Puts together an array of the name attributes of all required fields,
 * based on the source form data. Used for data validation on form submission.
 * Similar structure to `CAH\UWC\parseFormVars()`, just turned to a slightly different
 * purpose.
 *
 * @param array $formValues The array of form field information
 * @param array $container Reference to a container array for the required values
 *
 * @return void
 */
function parseRequiredFields(array $formValues, array &$container) : void
{
    if (empty($formValues)) {
        return;
    }

    foreach ($formValues as $item) {
        if (isset($item['name']) &&
            isset($item['required']) &&
            $item['required']
        ) {
            $container[$item['name']] = $item['label'];
        } else {
            switch($item['type']) {
                case 'multi':
                case 'fieldset':
                    parseRequiredFields($item['fields'], $container);
                    break;
                
                case 'function':
                default:
                    break;
            }
        }
    }
}

/**
 * Dynamically generates list of graduation years
 *
 * Used so students can select their graduation year, in addition to
 * a separate `select` element for the term. Passed as a callable function
 * to the $options argument of the CAH\UWC\FormInput constructor.
 *
 * @return int[]
 */
function getGradYears() : array
{
    $currentYear = intval(date('Y'));

    $yearList = [$currentYear];

    for ($i = 1; $i <= 10; $i++) {
        $yearList[] = ++$currentYear;
    }

    return $yearList;
}

/**
 * Prints all the inputs from an array of form values.
 *
 * Loops through the $formValues array, and recurses where necessary in order
 * to print all organizational levels of inputs. Echos output rather than
 * returning it unless $shouldReturn is `true`.
 *
 * @param array $formValues   Array of associative arrays detailing item content
 *                            and properties
 * @param array $formVars     Array of current submitted user values
 * @param int $baseWidth      The default starting width of the input, in
 *                            Bootstrap grid units. Default `12`.
 * @param bool $shouldReturn  Determines whether to return or echo the output.
 *                            Default false.
 *
 * @return void|string
 */
function displayFormValues(
    array $formValues,
    array $formVars,
    int $baseWidth = 12,
    bool $shouldReturn = false
) {
    ob_start();

    $formTextID = 0;

    // Loop through the form values
    foreach ($formValues as $item) {
        // Get ready to strip important properties
        $objProps = [
            'name',
            'type',
            'label',
            'formText',
            'options',
        ];

        $name = "";
        $type = "";
        $label = "";
        $formText = "";
        $options = [];
        $additionalAttrs = [];

        // Add the prop values to the variables that share their names
        foreach ($item as $key => $value) {
            if (in_array($key, $objProps)) {
                $$key = $value;
            } else {
                // Cache anything else to be parsed later in the FormInput
                // object.
                $additionalAttrs[$key] = $value;
            }
        }

        switch ($item['type']) {
            // With a fieldset, we create the container and add the legend,
            // then recurse to fill in the form fields
            case 'fieldset':
                ?>
                <fieldset class="col-<?= $baseWidth ?> mb-3 border p-3 pt-1"<?= !empty($formText) ? " aria-describedby=\"form-text-$formTextID\"" : "" ?>>
                <?php if (isset($item['legend'])) : ?>
                    <legend><?= $item['legend'] ?></legend>
                <?php endif; ?>
                <?php if (!empty($formText)) : ?>
                    <p class="form-text" id="form-text-<?= $formTextID++ ?>"><?= $formText ?></p>
                <?php endif; ?>
                    <?php displayFormValues($item['fields'], $formVars); ?>
                </fieldset>
                <?php
                break;

            // With a multi input, we create the containing div and recurse
            case 'multi':
                ?>
                <div class="col-md-<?= $baseWidth / 2 ?> multi-input">
                    <label class="form-label"><?= $label ?></label>
                    <div class="d-flex flex-row align-items-center flex-wrap">
                        <?php displayFormValues($item['fields'], $formVars, 6); ?>
                    </div>
                </div>
                <?php
                break;

            // With a function, we try to call the provided callable within
            // the current namespace, and log the error if we can't find it.
            case 'function':
                try {
                    if (isset($item['args'])) {
                        call_user_func(__NAMESPACE__ . "\\" . $item['callable'], $formVars[$item['args']]);
                    } else {
                        call_user_func(__NAMESPACE__ . "\\" . $item['callable']);
                    }
                } catch (\Exception $e) {
                    error_log("[" . date(DATE_RFC3339) . "] " . $e);
                    echo "<p><strong>Error in function {$item['callable']}.</strong></p>";
                }
                break;

            // Otherwise we just print the input, using the FormInput class's
            // __toString() magic method.
            default:
                echo new FormInput($name, $type, $label, isset($formVars[$name]) ? $formVars[$name] : null, $options, $formText, $additionalAttrs, $baseWidth);
                break;
        }
    }

    $output = ob_get_clean();

    // Just in case we want to cache the output for some reason
    if ($shouldReturn) {
        return $output;
    }

    echo $output;
}

/**
 * Generates the table of select boxes for schedule prioritization.
 *
 * Constructs the weekly schedule table, with select boxes for each
 * possible hour that allows the user to select a priority level.
 * They're all stored under the key "schedule" in $_POST.
 *
 * @param array $schedule The current schedule values, if any
 *
 * @return void
 */
function generateScheduleTable(array $schedule) : void
{
    // Creating arrays of Days and Hours
    $weekdays = [
        "Monday",
        "Tuesday",
        "Wednesday",
        "Thursday",
        "Friday",
        "Saturday",
        "Sunday",
    ];

    $hours = [
        'default' => range(9, 19), // Why hand-jam if you don't have to?
        'Saturday' => [],
        'Sunday' => range(14, 19),
    ];

    ob_start();
    ?>
    <div class="scheduleForm">
        <h2 class="h3 mt-2">Weekly Availability</h2>
        <ol class="form-text">
            <li>Select &ldquo;X&rdquo; for any time that you have class. You will need to select an &ldquo;X&rdquo; for any time when the class extends into the next hour; for example, if your class is from 10:00&ndash;11:15, select an &ldquo;X&rdquo; for the 10:00&ndash;11:00 and the 11:00&ndash;12:00 time slot.</li>
            <li>Select an &ldquo;X&rdquo; for all hour slots <em>before</em> class. Continuing the example above, you would select an &ldquo;X&rdquo; for the 9:00&ndash;10:00 time slot. We ask that you do not give availability the hour before class begins, in order to ensure that you arrive to class on time and that your work with us does not interfere with your studies. <em>Cocoa and Daytona consultants:</em> There may be days when it's helpful to schedule your start and end times on the half hour. Please follow the instructions to allow yourself an hour between any class and <abbr title="University Writing Center">UWC</abbr> time, but if you can work any additional half-hours before or after a class, indicate this above with your class schedule.</li>
            <li>Select an &ldquo;X&rdquo; for any times and days that you absolutely cannot work; for example, not being able to work mornings because of traffic or Sundays because of family obligations.</li>
            <li>Select an &ldquo;X&rdquo; for times in which you have other obligations, such as: Swing Nights at the Big Bamboo, Society for Young Existentialists, etc. Blocking out time can have an inverse effect; extremely limited availability will result in extremely limited assigned hours.</li>
            <li>
                Assign the remaining hours available to work as:
                <dl class="mt-2">
                    <dt>1</dt><dd>Most preferable</dd><br />
                    <dt>2</dt><dd>Preferable</dd><br />
                    <dt>3</dt><dd>Least Preferable</dd>
                </dl>
                <em>Note:</em> We will try to schedule within your preferences; however, if the demand is present, we may schedule during a &ldquo;least preferable&rdquo; time.
            </li>
        </ol>
        <table id="calendar" class="table table-bordered table-responsive">
            <thead>
                <tr>
                <?php foreach ($weekdays as $weekday) : // Create the weekday headers ?>
                    <th class="header-cell">
                        <p class="text-center"><strong><?= $weekday ?></strong></p>
                    </th>
                <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php for ($i = 9; $i <= 19; $i++) : // Loop through the hours ?>
                <tr>
                <?php for ($j = 0; $j < 7; $j++) : // Loop through the days?>
                    <td class="select-cell">
                    <?php if (!isset($hours[$weekdays[$j]]) || (isset($hours[$weekdays[$j]]) && in_array($i, $hours[$weekdays[$j]]))) : ?>
                        <?= new FormInput("schedule[{$weekdays[$j]}][$i]", "select", getTimeLabel($i), isset($schedule[$weekdays[$j]][$i]) ? $schedule[$weekdays[$j]][$i] : 'X', ["no-default", "X", 1, 2, 3], null, null, 24); ?>
                    <?php else : ?>
                        <p class="text-secondary text-center">&mdash;</p>
                    <?php endif; ?>
                    </td>
                <?php endfor; ?>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <?php
    // Echo the contents of the output buffer
    echo ob_get_clean();
}

/**
 * Returns a formatted hour display for the form.
 *
 * Given an integer hour, turns that into a string representing a start
 * and end window, formatted in 12-hour am/pm format (*e.g.*, `9` should
 * become `9 am - 10 am`).
 *
 * @param int $startHour The hour that begins the window
 *
 * @return string
 */
function getTimeLabel(int $startHour) : string
{
    // Getting our JavaScript style on a little bit... :P
    $formatTime = function($i) {
        return ($i > 12 ? $i - 12 : $i) . " " . ($i < 12 ? "am" : "pm");
    };

    return $formatTime($startHour) . "&ndash;" . $formatTime($startHour + 1);
}

/**
 * Format a 10-digit phone number to be more readable.
 *
 * Only works on 10-digit U.S. phone numbers. Adds formatting to fit the
 * (123) 456-7890 schema for better readability.
 *
 * @param int|string $phone The user's input phone number
 *
 * @return string
 */
function formatPhone($phone) : string
{
    // Cast to string, just in case
    $input = strval($phone);

    // Divide into the parts we need
    $areaCode = substr($input, 0, 3);
    $beforeHyphen = substr($input, 3, 3);
    $afterHyphen = substr($input, 6);

    // Return the formatted string
    return "($areaCode) $beforeHyphen-$afterHyphen";
}

/**
 * Logs a debug message.
 *
 * Adds an entry to a local `debug.log` file. Used primarily for development
 * and debugging.
 *
 * @param mixed $msg The content to be logged. Could be anything with
 *                    a `__toString()` implementation
 *
 * @return void
 */
function log($msg) : void
{
    // Formats the message, adding a date/time stamp
    $msg = "[" .
        date_format(
            new \DateTime(
                'now',
                new \DateTimeZone('America/New_York')
            ),
            'Y-m-d H:i:s e'
        ) .
        "] " .
        $msg .
        "\n";

    // Logs the message to the local file
    error_log($msg, 3, "./debug.log");
}