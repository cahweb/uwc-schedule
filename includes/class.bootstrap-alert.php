<?php
namespace CAH\UWC;

/**
 * Generates a Bootstrap alert
 *
 * Given a type and a message, will produce a Bootstrap alert box
 * for display.
 *
 * @category CAH
 * @package  UWC
 * @author   Mike W. Leavitt
 * @version  1.0.0
 */
class BootstrapAlert
{
    /**
     * @var string
     *
     * Technically a string, but will be one of the values from
     * the `\CAH\UWC\AlertTypes` pseudo-enum.
     */
    private $alertType;

    /**
     * @var string
     *
     * The message to display within the alert box.
     */
    private $message;

    /**
     * Constructor
     *
     * @param string $type    The type of alert, corresponding to values from
     *                         the `\CAH\UWC\AlertTypes` pseudo-enum
     * @param string $message The message to display in the alert.
     */
    public function __construct(string $type, string $message)
    {
        $this->alertType = $type;
        $this->message = $message;
    }

    /**
     * Generates string output for the object.
     *
     * Implements the object's __toString() magic method.
     */
    public function __toString()
    {
        // Just reproducing the standard Bootstrap alert box.
        ob_start();
        ?>
        <div class="alert alert-<?= $this->alertType ?>" role="alert">
            <p class="mx-auto"><?= $this->message ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Getter for the alert type.
     *
     * Just in case we need to check it against something.
     *
     * @return string (technically an `AlertTypes` value)
     */
    public function getType()
    {
        return $this->alertType;
    }
}