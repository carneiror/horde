<?php
class Horde_Test_Case extends PHPUnit_Framework_TestCase
{
    /**
     * Useful shorthand if you are mocking a class with a private constructor
     */
    public function getMockSkipConstructor($className, array $methods = array(), array $arguments = array(), $mockClassName = '')
    {
        return $this->getMock($className, $methods, $arguments, $mockClassName, /* $callOriginalConstructor */ false);
    }

    /**
     * Helper method for loading test configuration from a file.
     *
     * The configuration can be specified by an environment variable. If the
     * variable content is a file name, the configuration is loaded from the
     * file. Otherwise it's assumed to be a json encoded configuration hash. If
     * the environment variable is not set, the method tries to load a conf.php
     * file from the same directory as the test case.
     *
     * @param string $env     An environment variable name.
     * @param array $default  Some default values that are merged into the
     *                        configuration if specified as a json hash.
     *
     * @return mixed  The value of the configuration file's $conf variable, or
     *                null.
     */
    static public function getConfig($env, $path = null, $default = array())
    {
        $config = getenv($env);
        if ($config) {
            $json = json_decode($config, true);
            if ($json) {
                return Horde_Array::replaceRecursive($default, $json);
            }
        } else {
            if (!$path) {
                $backtrace = new Horde_Support_Backtrace();
                $caller = $backtrace->getCurrentContext();
                $path = dirname($caller['file']);
            }
            $config = $path . '/conf.php';
        }

        if (file_exists($config)) {
            require $config;
            return $conf;
        }

        return null;
    }
}
