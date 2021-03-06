<?php
namespace Fgsl\Groupware\Groupbase\Locale\Math;

use Fgsl\Groupware\Groupbase\Locale\Math;
use Fgsl\Groupware\Groupbase\Locale\Exception;

/**
 * @package     Groupbase
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 * @copyright   Copyright (c) 2019 FGSL (http://www.fgsl.eti.br)
 *
 */

/**
 * Utility class for proxying math function to bcmath functions, if present,
 * otherwise to PHP builtin math operators, with limited detection of overflow conditions.
 * Sampling of PHP environments and platforms suggests that at least 80% to 90% support bcmath.
 * This file should only be loaded for the 10% to 20% lacking access to the bcmath extension.
 *
 * @package    Groupbase
 * @subpackage Locale
 */
class PhpMath extends Math
{
    public static function disable()
    {
        self::$_bcmathDisabled = true;
        self::$add   = array('PhpMath', 'Add');
        self::$sub   = array('PhpMath', 'Sub');
        self::$pow   = array('PhpMath', 'Pow');
        self::$mul   = array('PhpMath', 'Mul');
        self::$div   = array('PhpMath', 'Div');
        self::$comp  = array('PhpMath', 'Comp');
        self::$sqrt  = array('PhpMath', 'Sqrt');
        self::$mod   = array('PhpMath', 'Mod');
        self::$scale = array('PhpMath', 'Scale');
        
        self::$defaultScale     = 0;
        self::$defaultPrecision = 1;
    }
    
    public static $defaultScale;
    public static $defaultPrecision;
    
    
    public static function Add($op1, $op2, $scale = null)
    {
        if ($scale === null) {
            $scale     = PhpMath::$defaultScale;
            $precision = PhpMath::$defaultPrecision;
        } else {
            $precision = pow(10, -$scale);
        }
        
        if (empty($op1)) {
            $op1 = 0;
        }
        $op1 = self::normalize($op1);
        $op2 = self::normalize($op2);
        $result = $op1 + $op2;
        if (is_infinite($result)  or  (abs($result - $op2 - $op1) > $precision)) {
            throw new Exception("addition overflow: $op1 + $op2 != $result", $op1, $op2, $result);
        }
        
        return self::round(self::normalize($result), $scale);
    }
    
    public static function Sub($op1, $op2, $scale = null)
    {
        if ($scale === null) {
            $scale     = PhpMath::$defaultScale;
            $precision = PhpMath::$defaultPrecision;
        } else {
            $precision = pow(10, -$scale);
        }
        
        if (empty($op1)) {
            $op1 = 0;
        }
        $op1  = self::normalize($op1);
        $op2  = self::normalize($op2);
        $result = $op1 - $op2;
        if (is_infinite($result)  or  (abs($result + $op2 - $op1) > $precision)) {
            throw new Exception("subtraction overflow: $op1 - $op2 != $result", $op1, $op2, $result);
        }
        
        return self::round(self::normalize($result), $scale);
    }
    
    public static function Pow($op1, $op2, $scale = null)
    {
        if ($scale === null) {
            $scale = PhpMath::$defaultScale;
        }
        
        $op1 = self::normalize($op1);
        $op2 = self::normalize($op2);
        
        // BCMath extension doesn't use decimal part of the power
        // Provide the same behavior
        $op2 = ($op2 > 0) ? floor($op2) : ceil($op2);
        
        $result = pow($op1, $op2);
        if (is_infinite($result)  or  is_nan($result)) {
            throw new Exception("power overflow: $op1 ^ $op2", $op1, $op2, $result);
        }
        
        return self::round(self::normalize($result), $scale);
    }
    
    public static function Mul($op1, $op2, $scale = null)
    {
        if ($scale === null) {
            $scale = PhpMath::$defaultScale;
        }
        
        if (empty($op1)) {
            $op1 = 0;
        }
        $op1 = self::normalize($op1);
        $op2 = self::normalize($op2);
        $result = $op1 * $op2;
        if (is_infinite($result)  or  is_nan($result)) {
            throw new Exception("multiplication overflow: $op1 * $op2 != $result", $op1, $op2, $result);
        }
        
        return self::round(self::normalize($result), $scale);
    }
    
    public static function Div($op1, $op2, $scale = null)
    {
        if ($scale === null) {
            $scale = PhpMath::$defaultScale;
        }
        
        if (empty($op2)) {
            throw new Exception("can not divide by zero", $op1, $op2, null);
        }
        if (empty($op1)) {
            $op1 = 0;
        }
        $op1 = self::normalize($op1);
        $op2 = self::normalize($op2);
        $result = $op1 / $op2;
        if (is_infinite($result)  or  is_nan($result)) {
            throw new Exception("division overflow: $op1 / $op2 != $result", $op1, $op2, $result);
        }
        
        return self::round(self::normalize($result), $scale);
    }
    
    public static function Sqrt($op1, $scale = null)
    {
        if ($scale === null) {
            $scale = PhpMath::$defaultScale;
        }
        
        if (empty($op1)) {
            $op1 = 0;
        }
        $op1 = self::normalize($op1);
        $result = sqrt($op1);
        if (is_nan($result)) {
            return NULL;
        }
        
        return self::round(self::normalize($result), $scale);
    }
    
    public static function Mod($op1, $op2)
    {
        if (empty($op1)) {
            $op1 = 0;
        }
        if (empty($op2)) {
            return NULL;
        }
        $op1 = self::normalize($op1);
        $op2 = self::normalize($op2);
        if ((int)$op2 == 0) {
            return NULL;
        }
        $result = $op1 % $op2;
        if (is_nan($result)  or  (($op1 - $result) % $op2 != 0)) {
            throw new Exception("modulus calculation error: $op1 % $op2 != $result", $op1, $op2, $result);
        }
        
        return self::normalize($result);
    }
    
    public static function Comp($op1, $op2, $scale = null)
    {
        if ($scale === null) {
            $scale     = PhpMath::$defaultScale;
        }
        
        if (empty($op1)) {
            $op1 = 0;
        }
        $op1 = self::normalize($op1);
        $op2 = self::normalize($op2);
        if ($scale <> 0) {
            $op1 = self::round($op1, $scale);
            $op2 = self::round($op2, $scale);
        } else {
            $op1 = ($op1 > 0) ? floor($op1) : ceil($op1);
            $op2 = ($op2 > 0) ? floor($op2) : ceil($op2);
        }
        if ($op1 > $op2) {
            return 1;
        } else if ($op1 < $op2) {
            return -1;
        }
        return 0;
    }
    
    public static function Scale($scale)
    {
        if ($scale > 9) {
            throw new Exception("can not scale to precision $scale", $scale, null, null);
        }
        self::$defaultScale     = $scale;
        self::$defaultPrecision = pow(10, -$scale);
        return true;
    }
}

PhpMath::disable(); // disable use of bcmath functions
