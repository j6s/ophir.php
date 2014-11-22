<pre><?php
require('vendor/autoload.php');

/**
 * Created by PhpStorm.
 * User: thephpjo
 * Date: 11/22/14
 * Time: 2:45 PM
 */

use lovasoa\ophir\Ophir;

try {
    $ophir = new Ophir();
    $ophir->setFile('Tests/test.odt');
    echo $ophir->convert();
} catch (Exception $e){
    var_dump($e);
}

